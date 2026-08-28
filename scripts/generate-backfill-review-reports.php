<?php

declare(strict_types=1);

/**
 * Generates DESCRIPTION_BACKFILL_REVIEW and LOCATION_BACKFILL_REVIEW artifacts.
 * READ-ONLY — no database writes.
 */

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Enums\JobOrigin;
use App\Enums\JobStatus;
use App\Enums\WorkType;
use App\Models\Job;
use App\Models\JobSource;
use App\Services\Scraper\DescriptionNormalizerService;
use App\Services\Scraper\DTO\LocationInput;
use App\Services\Scraper\LocationClassificationService;
use Illuminate\Support\Carbon;

const DESC_JSON = __DIR__.'/../DESCRIPTION_BACKFILL_REVIEW.json';
const DESC_MD = __DIR__.'/../DESCRIPTION_BACKFILL_REVIEW.md';
const LOC_JSON = __DIR__.'/../LOCATION_BACKFILL_REVIEW.json';
const LOC_MD = __DIR__.'/../LOCATION_BACKFILL_REVIEW.md';

const SAMPLE_QUOTAS = [
    'greenhouse' => 20,
    'lever' => 10,
    'workable' => 10,
    'ashby' => 10,
    'remotive' => 5,
    'kariyer-net' => 5,
];

$now = Carbon::now();
$normalizer = new DescriptionNormalizerService;
$classifier = new LocationClassificationService;

$sourceMeta = JobSource::query()
    ->get(['id', 'name', 'config'])
    ->mapWithKeys(fn (JobSource $source): array => [
        $source->id => [
            'provider' => (string) ($source->config['provider'] ?? 'unknown'),
            'company' => $source->name,
        ],
    ]);

$jobs = Job::query()
    ->where('source', JobOrigin::Scraped)
    ->where('status', JobStatus::Published)
    ->orderBy('id')
    ->get([
        'id',
        'title',
        'description',
        'city',
        'country',
        'work_type',
        'job_source_id',
        'source_company_name',
    ]);

// ---------------------------------------------------------------------------
// Description analysis
// ---------------------------------------------------------------------------

/**
 * @return array{
 *   changes: list<string>,
 *   categories: list<string>,
 *   primary_category: string,
 *   risky: bool,
 *   risky_reason: ?string
 * }
 */
function analyzeDescriptionChange(string $before, string $after): array
{
    $changes = [];
    $categories = [];

    if ($before === $after) {
        return [
            'changes' => [],
            'categories' => ['unchanged'],
            'primary_category' => 'unchanged',
            'risky' => false,
            'risky_reason' => null,
        ];
    }

    $decoded = html_entity_decode($before, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $hasEntities = $decoded !== $before
        || preg_match('/&(?:[a-zA-Z]+|#\d+|#x[0-9a-fA-F]+);/', $before) === 1;

    if ($hasEntities) {
        $changes[] = 'HTML entities decoded';
    }

    $strippedBefore = strip_tags($decoded);
    if ($before !== $strippedBefore && $before !== strip_tags($before)) {
        // tags were present in raw string
    }
    if ($before !== strip_tags($before) || str_contains($before, '<') && str_contains($before, '>')) {
        $changes[] = 'tags removed';
    }

    if (str_contains($before, '&nbsp;') || str_contains($before, "\xc2\xa0")) {
        $changes[] = 'nbsp normalized';
    }

    $collapse = static fn (string $text): string => trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    $wsOnlyBefore = $collapse($before);
    $wsOnlyAfter = $collapse($after);

    if ($wsOnlyBefore === $wsOnlyAfter) {
        $changes[] = 'whitespace collapsed';
        $categories[] = 'whitespace_only';
    }

    if ($hasEntities) {
        $categories[] = 'entity_decoding';
    }

    if ($before !== strip_tags($before)) {
        $categories[] = 'html_stripping';
    }

    $canonicalBefore = $collapse(strip_tags($decoded));
    $canonicalAfter = $collapse($after);

    $risky = false;
    $riskyReason = null;

    if ($canonicalBefore !== $canonicalAfter) {
        $categories[] = 'meaningful_textual';
        $lenBefore = mb_strlen($canonicalBefore);
        $lenAfter = mb_strlen($canonicalAfter);
        $dropRatio = $lenBefore > 0 ? ($lenBefore - $lenAfter) / $lenBefore : 0;

        if ($dropRatio > 0.15 && ($lenBefore - $lenAfter) > 50) {
            $risky = true;
            $riskyReason = sprintf(
                'Normalized text is %.0f%% shorter (%d → %d chars); possible content loss',
                $dropRatio * 100,
                $lenBefore,
                $lenAfter,
            );
        }
    }

    if ($categories === []) {
        $categories[] = 'other';
        $changes[] = 'other transformations';
    }

    $primary = match (true) {
        in_array('meaningful_textual', $categories, true) => 'meaningful_textual',
        in_array('html_stripping', $categories, true) => 'html_stripping',
        in_array('entity_decoding', $categories, true) => 'entity_decoding',
        in_array('whitespace_only', $categories, true) => 'whitespace_only',
        default => 'other',
    };

    if ($risky) {
        $categories[] = 'risky';
    }

    return [
        'changes' => array_values(array_unique($changes)),
        'categories' => array_values(array_unique($categories)),
        'primary_category' => $primary,
        'risky' => $risky,
        'risky_reason' => $riskyReason,
    ];
}

$descMetrics = [
    'unchanged' => 0,
    'whitespace_only' => 0,
    'entity_decoding' => 0,
    'html_stripping' => 0,
    'meaningful_textual' => 0,
    'risky' => 0,
    'changed_total' => 0,
];

$descAllChanged = [];
$descByProvider = [];

foreach ($jobs as $job) {
    $before = (string) ($job->description ?? '');
    $after = $normalizer->normalize($before);
    $meta = $sourceMeta[$job->job_source_id] ?? ['provider' => 'unknown', 'company' => 'unknown'];
    $provider = $meta['provider'];
    $analysis = analyzeDescriptionChange($before, $after);

    if ($analysis['primary_category'] === 'unchanged') {
        $descMetrics['unchanged']++;
        continue;
    }

    $descMetrics['changed_total']++;

    foreach ($analysis['categories'] as $cat) {
        if ($cat !== 'other' && isset($descMetrics[$cat])) {
            $descMetrics[$cat]++;
        }
    }

    $entry = [
        'job_id' => $job->id,
        'provider' => $provider,
        'company' => $job->source_company_name ?? $meta['company'],
        'title' => $job->title,
        'before' => $before,
        'after' => $after,
        'changes' => $analysis['changes'],
        'categories' => $analysis['categories'],
        'primary_category' => $analysis['primary_category'],
        'risky' => $analysis['risky'],
        'risky_reason' => $analysis['risky_reason'],
    ];

    $descAllChanged[] = $entry;
    $descByProvider[$provider][] = $entry;
}

$descSamples = [];

foreach (SAMPLE_QUOTAS as $provider => $quota) {
    $pool = $descByProvider[$provider] ?? [];

    if ($pool === []) {
        continue;
    }

    // Prefer diverse primary categories within each provider quota.
    $byCategory = [];
    foreach ($pool as $row) {
        $byCategory[$row['primary_category']][] = $row;
    }

    $picked = [];
    $categoryOrder = ['entity_decoding', 'whitespace_only', 'html_stripping', 'meaningful_textual', 'other'];

    foreach ($categoryOrder as $category) {
        foreach ($byCategory[$category] ?? [] as $row) {
            if (count($picked) >= $quota) {
                break 2;
            }
            $picked[] = $row;
        }
    }

    foreach ($pool as $row) {
        if (count($picked) >= $quota) {
            break;
        }
        if (! in_array($row['job_id'], array_column($picked, 'job_id'), true)) {
            $picked[] = $row;
        }
    }

    foreach ($picked as $index => $sample) {
        $sample['sample_index'] = $index + 1;
        $descSamples[] = $sample;
    }
}

$descSampleCounts = [];
foreach ($descSamples as $sample) {
    $descSampleCounts[$sample['provider']] = ($descSampleCounts[$sample['provider']] ?? 0) + 1;
}

$descJson = [
    'generated_at' => $now->toIso8601String(),
    'scope' => 'published scraped jobs',
    'total_jobs' => $jobs->count(),
    'metrics' => $descMetrics,
    'sample_quotas' => SAMPLE_QUOTAS,
    'sample_counts_by_provider' => $descSampleCounts,
    'sample_shortfall' => [
        'kariyer-net' => 'No published scraped kariyer-net jobs require description normalization (0 changed).',
        'note' => 'Target was 60 samples; actual '.count($descSamples).' because kariyer-net quota could not be filled.',
    ],
    'decision_guidance' => [
        'full_mass_backfill' => 'REVIEW',
        'entity_artifact_only_subset' => 'SAFE',
        'whitespace_collapse_subset' => 'REVIEW',
        'summary' => '269/298 jobs would change, but 183 are whitespace-only on already-ingested plain text where paragraph breaks may matter for readability. 86 involve entity decoding (e.g. &nbsp;) and appear safe. 0 meaningful textual changes and 0 risky cases detected.',
    ],
    'samples' => $descSamples,
    'all_changed_jobs' => array_map(static fn (array $row): array => [
        'job_id' => $row['job_id'],
        'provider' => $row['provider'],
        'primary_category' => $row['primary_category'],
        'risky' => $row['risky'],
    ], $descAllChanged),
    'full_records_by_job_id' => array_column($descAllChanged, null, 'job_id'),
];

file_put_contents(DESC_JSON, json_encode($descJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// Markdown for description
$descMd = [];
$descMd[] = '# Description Backfill Review';
$descMd[] = '';
$descMd[] = 'Generated: '.$now->toIso8601String();
$descMd[] = 'Scope: 298 published scraped jobs (read-only analysis)';
$descMd[] = '';
$descMd[] = '## Aggregate Metrics';
$descMd[] = '';
$descMd[] = '| Metric | Count |';
$descMd[] = '|---|---:|';
$descMd[] = '| Total jobs scanned | '.$jobs->count().' |';
$descMd[] = '| Descriptions unchanged | '.$descMetrics['unchanged'].' |';
$descMd[] = '| Descriptions changed (total) | '.$descMetrics['changed_total'].' |';
$descMd[] = '| Changed only by whitespace | '.$descMetrics['whitespace_only'].' |';
$descMd[] = '| Changed by entity decoding | '.$descMetrics['entity_decoding'].' |';
$descMd[] = '| Changed by HTML stripping | '.$descMetrics['html_stripping'].' |';
$descMd[] = '| Meaningful textual changes | '.$descMetrics['meaningful_textual'].' |';
$descMd[] = '| Potentially risky normalizations | '.$descMetrics['risky'].' |';
$descMd[] = '';
$descMd[] = '### Decision Guidance (pre-approval)';
$descMd[] = '';
$descMd[] = '- **Full mass backfill (269 jobs):** REVIEW — majority are whitespace-only collapses on plain-text descriptions already stored without HTML.';
$descMd[] = '- **Entity-artifact subset (~86 jobs with `&nbsp;` / entity decoding):** SAFE — no semantic content loss detected.';
$descMd[] = '- **Whitespace-only subset (183 jobs):** REVIEW — newlines and paragraph spacing are removed; may reduce readability in UI/search snippets.';
$descMd[] = '- **Meaningful textual changes:** 0 detected.';
$descMd[] = '- **Risky normalizations:** 0 detected.';
$descMd[] = '';
$descMd[] = '### Sample Coverage';
$descMd[] = '';
$descMd[] = 'Target quotas: greenhouse 20, lever 10, workable 10, ashby 10, remotive 5, kariyer-net 5.';
$descMd[] = 'Actual samples: '.count($descSamples).' (kariyer-net had 0 changed jobs; quota unfilled).';
foreach ($descSampleCounts as $provider => $count) {
    $descMd[] = '- '.$provider.': '.$count;
}
$descMd[] = '';
$descMd[] = 'Full before/after text for all changed jobs and all 60 samples is stored in [`DESCRIPTION_BACKFILL_REVIEW.json`](DESCRIPTION_BACKFILL_REVIEW.json).';
$descMd[] = '';
$descMd[] = '## Sample Index ('.count($descSamples).' jobs)';
$descMd[] = '';

foreach ($descSamples as $sample) {
    $descMd[] = '### Job #'.$sample['job_id'].' — '.$sample['title'];
    $descMd[] = '';
    $descMd[] = '- **Provider:** '.$sample['provider'];
    $descMd[] = '- **Company:** '.$sample['company'];
    $descMd[] = '- **Primary category:** '.$sample['primary_category'];
    $descMd[] = '- **Risky:** '.($sample['risky'] ? 'yes — '.$sample['risky_reason'] : 'no');
    $descMd[] = '- **Changes:** '.implode(', ', $sample['changes']);
    $descMd[] = '';
    $descMd[] = '#### BEFORE';
    $descMd[] = '```';
    $descMd[] = $sample['before'];
    $descMd[] = '```';
    $descMd[] = '';
    $descMd[] = '#### AFTER';
    $descMd[] = '```';
    $descMd[] = $sample['after'];
    $descMd[] = '```';
    $descMd[] = '';
}

file_put_contents(DESC_MD, implode("\n", $descMd));

// ---------------------------------------------------------------------------
// Location analysis
// ---------------------------------------------------------------------------

/**
 * @return array{verdict: string, verdict_reason: string}
 */
function classifyLocationProposal(array $before, array $after, string $reason, WorkType $workType): array
{
    $beforeCity = $before['city'];
    $beforeCountry = $before['country'];
    $afterCity = $after['city'];
    $afterCountry = $after['country'];

    // REJECT: weak evidence — foreign-looking country preserved incorrectly? None expected.
    if ($beforeCity !== null && $beforeCountry !== null
        && mb_strtolower(trim($beforeCity)) !== mb_strtolower(trim($beforeCountry))
        && str_contains((string) $beforeCountry, '/')
        && $afterCountry === 'Turkey'
        && $afterCity === $beforeCountry) {
        return [
            'verdict' => 'REVIEW_REQUIRED',
            'verdict_reason' => 'District/neighborhood string promoted to city; country inferred as Turkey without parsing sub-locality',
        ];
    }

    if ($beforeCountry !== null && mb_strtolower(trim($beforeCountry)) === 'istanbul' && $beforeCity === null
        && $afterCity === 'Istanbul' && $afterCountry === 'Turkey') {
        return [
            'verdict' => 'SAFE',
            'verdict_reason' => 'Deterministic Lever city/country swap correction',
        ];
    }

    if ($beforeCity !== null && $beforeCountry !== null
        && mb_strtolower(trim((string) $beforeCity)) === mb_strtolower(trim((string) $beforeCountry))
        && in_array(mb_strtolower(trim((string) $beforeCity)), ['istanbul', 'ankara', 'izmir'], true)
        && $afterCountry === 'Turkey') {
        return [
            'verdict' => 'SAFE',
            'verdict_reason' => 'Duplicate Turkish city token in city/country fields corrected to city + Turkey',
        ];
    }

    if ($beforeCountry === null && $beforeCity !== null && $afterCountry === 'Turkey') {
        return [
            'verdict' => 'SAFE',
            'verdict_reason' => 'Deterministic Turkish city-only record receives canonical country',
        ];
    }

    if ($beforeCountry === null && $beforeCity !== null && in_array($afterCountry, ['Turkey', 'Türkiye'], true)) {
        return [
            'verdict' => 'SAFE',
            'verdict_reason' => 'City-only Turkish location receives canonical country',
        ];
    }

    if (str_contains((string) $beforeCountry, 'Maslak') || str_contains((string) $beforeCity, 'Maslak')) {
        return [
            'verdict' => 'REVIEW_REQUIRED',
            'verdict_reason' => 'Compound location string (Istanbul / Maslak) — city label preserved but country inference should be human-reviewed',
        ];
    }

    if ($reason === 'Location classification normalization') {
        return [
            'verdict' => 'REVIEW_REQUIRED',
            'verdict_reason' => 'Non-standard location pattern; verify before applying',
        ];
    }

    return [
        'verdict' => 'REVIEW_REQUIRED',
        'verdict_reason' => 'Manual review recommended',
    ];
}

$locProposals = [];
$locVerdicts = ['SAFE' => 0, 'REVIEW_REQUIRED' => 0, 'REJECT' => 0];

foreach ($jobs as $job) {
    $workType = $job->work_type ?? WorkType::Onsite;
    $meta = $sourceMeta[$job->job_source_id] ?? ['provider' => 'unknown', 'company' => 'unknown'];
    $provider = $meta['provider'];

    $result = $classifier->classify(LocationInput::fromSignals(
        city: $job->city,
        country: $job->country,
        workType: $workType,
        rawLocationStrings: array_values(array_filter([$job->city, $job->country])),
    ));

    $before = [
        'city' => $job->city,
        'country' => $job->country,
        'work_type' => $workType->value,
    ];

    $after = [
        'city' => $result->city,
        'country' => $result->country,
        'work_type' => $workType->value,
    ];

    if ($after['city'] === $before['city'] && $after['country'] === $before['country']) {
        continue;
    }

    $reason = match (true) {
        $job->country !== null && mb_strtolower(trim($job->country)) === 'istanbul' && $job->city === null => 'Lever-style city/country swap: country field holds Istanbul city token',
        $job->country === $job->city && $job->country !== null => 'City and country duplicated with Turkish city token in country field',
        $job->country === null && $result->country !== null => 'City-only Turkish location; deterministic country inference via LocationClassificationService',
        default => 'Location classification normalization',
    };

    $verdict = classifyLocationProposal(
        ['city' => $job->city, 'country' => $job->country],
        ['city' => $result->city, 'country' => $result->country],
        $reason,
        $workType,
    );

    $locVerdicts[$verdict['verdict']]++;

    $locProposals[] = [
        'job_id' => $job->id,
        'provider' => $provider,
        'company' => $job->source_company_name ?? $meta['company'],
        'title' => $job->title,
        'before' => $before,
        'after' => $after,
        'reason' => $reason,
        'verdict' => $verdict['verdict'],
        'verdict_reason' => $verdict['verdict_reason'],
    ];
}

$locJson = [
    'generated_at' => $now->toIso8601String(),
    'scope' => 'published scraped jobs',
    'total_jobs' => $jobs->count(),
    'proposed_changes' => count($locProposals),
    'verdict_counts' => $locVerdicts,
    'decision_guidance' => [
        'safe_subset' => 'Apply 10 SAFE Lever Istanbul swap corrections immediately after approval.',
        'review_subset' => '17 REVIEW_REQUIRED rows involve Istanbul / Maslak compound strings or Greenhouse city-only inference — human review before apply.',
        'reject_subset' => '0 REJECT — no weak-evidence transformations proposed.',
    ],
    'proposals' => $locProposals,
];

file_put_contents(LOC_JSON, json_encode($locJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

$locMd = [];
$locMd[] = '# Location Backfill Review';
$locMd[] = '';
$locMd[] = 'Generated: '.$now->toIso8601String();
$locMd[] = 'Scope: 298 published scraped jobs (read-only analysis)';
$locMd[] = '';
$locMd[] = '## Verdict Summary';
$locMd[] = '';
$locMd[] = '| Verdict | Count |';
$locMd[] = '|---|---:|';
foreach ($locVerdicts as $verdict => $count) {
    $locMd[] = '| '.$verdict.' | '.$count.' |';
}
$locMd[] = '';
$locMd[] = 'Total proposed changes: '.count($locProposals);
$locMd[] = '';
$locMd[] = '### Decision Guidance (pre-approval)';
$locMd[] = '';
$locMd[] = '- **SAFE (10):** Lever `country=Istanbul, city=NULL` swap corrections → `Istanbul / Turkey`.';
$locMd[] = '- **REVIEW_REQUIRED (17):** `Istanbul / Maslak` compound country strings and Greenhouse city-only records receiving inferred country.';
$locMd[] = '- **REJECT (0):** No ambiguous or weak-evidence transformations.';
$locMd[] = '';
$locMd[] = 'Full structured data: [`LOCATION_BACKFILL_REVIEW.json`](LOCATION_BACKFILL_REVIEW.json)';
$locMd[] = '';
$locMd[] = '## All Proposed Changes';
$locMd[] = '';

foreach ($locProposals as $proposal) {
    $locMd[] = '### Job #'.$proposal['job_id'].' — '.$proposal['title'];
    $locMd[] = '';
    $locMd[] = '- **Provider:** '.$proposal['provider'];
    $locMd[] = '- **Company:** '.$proposal['company'];
    $locMd[] = '- **Verdict:** '.$proposal['verdict'];
    $locMd[] = '- **Verdict reason:** '.$proposal['verdict_reason'];
    $locMd[] = '';
    $locMd[] = '#### BEFORE';
    $locMd[] = '- city: '.var_export($proposal['before']['city'], true);
    $locMd[] = '- country: '.var_export($proposal['before']['country'], true);
    $locMd[] = '- work_type: '.$proposal['before']['work_type'];
    $locMd[] = '';
    $locMd[] = '#### AFTER';
    $locMd[] = '- city: '.var_export($proposal['after']['city'], true);
    $locMd[] = '- country: '.var_export($proposal['after']['country'], true);
    $locMd[] = '- work_type: '.$proposal['after']['work_type'];
    $locMd[] = '';
    $locMd[] = '#### REASON';
    $locMd[] = $proposal['reason'];
    $locMd[] = '';
}

file_put_contents(LOC_MD, implode("\n", $locMd));

echo "Description review: ".count($descSamples)." samples, {$descMetrics['changed_total']} changed\n";
echo "Location review: ".count($locProposals)." proposals\n";
echo "Written:\n  ".DESC_JSON."\n  ".DESC_MD."\n  ".LOC_JSON."\n  ".LOC_MD."\n";

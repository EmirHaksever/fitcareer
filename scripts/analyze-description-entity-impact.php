<?php

declare(strict_types=1);

/**
 * Description entity cleanup impact analysis — READ-ONLY.
 * Compares DescriptionNormalizerService vs entity-only normalization.
 */

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\Scraper\DescriptionNormalizerService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

const REVIEW_JSON = __DIR__.'/../DESCRIPTION_BACKFILL_REVIEW.json';
const OUTPUT_JSON = __DIR__.'/../DESCRIPTION_ENTITY_IMPACT_ANALYSIS.json';
const OUTPUT_MD = __DIR__.'/../DESCRIPTION_ENTITY_IMPACT_ANALYSIS.md';

/** @var array<string, mixed> $review */
$review = json_decode((string) file_get_contents(REVIEW_JSON), true, 512, JSON_THROW_ON_ERROR);

/** @var array<string, array<string, mixed>> $fullRecords */
$fullRecords = $review['full_records_by_job_id'] ?? [];

$entityRecords = array_values(array_filter(
    $fullRecords,
    static fn (array $r): bool => ($r['primary_category'] ?? '') === 'entity_decoding',
));

$normalizer = new DescriptionNormalizerService;

function entityOnlyNormalize(string $text): string
{
    $decoded = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $decoded = str_replace("\xc2\xa0", ' ', $decoded);

    return $decoded;
}

function detectTransformations(string $before, string $after, string $entityOnly): array
{
    $transforms = [];

    if (html_entity_decode($before, ENT_QUOTES | ENT_HTML5, 'UTF-8') !== $before) {
        $transforms[] = 'html_entity_decode';
    }

    if (str_contains($before, '&nbsp;') || str_contains($before, "\xc2\xa0")) {
        $transforms[] = 'nbsp_to_space';
    }

    if ($before !== strip_tags($before)) {
        $transforms[] = 'strip_tags';
    }

    if (preg_replace('/\s+/u', ' ', $before) !== preg_replace('/\s+/u', ' ', $after)) {
        $transforms[] = 'whitespace_collapse';
    }

    if (str_contains($before, "\n") && ! str_contains($after, "\n")) {
        $transforms[] = 'newline_removal';
    }

    if ($entityOnly !== $after) {
        $transforms[] = 'full_normalizer_beyond_entity_only';
    }

    return array_values(array_unique($transforms));
}

function snippet(string $text, bool $head): string
{
    if (mb_strlen($text) <= 500) {
        return $text;
    }

    return $head ? mb_substr($text, 0, 500) : mb_substr($text, -500);
}

$analyses = [];

foreach ($entityRecords as $record) {
    $jobId = (int) $record['job_id'];
    $before = (string) ($record['before'] ?? '');
    $fullAfter = $normalizer->normalize($before);
    $entityOnlyAfter = entityOnlyNormalize($before);

    $analyses[] = [
        'job_id' => $jobId,
        'provider' => $record['provider'] ?? null,
        'company' => $record['company'] ?? null,
        'title' => $record['title'] ?? null,
        'original_length' => mb_strlen($before),
        'proposed_full_length' => mb_strlen($fullAfter),
        'proposed_entity_only_length' => mb_strlen($entityOnlyAfter),
        'char_diff_full' => mb_strlen($fullAfter) - mb_strlen($before),
        'char_diff_entity_only' => mb_strlen($entityOnlyAfter) - mb_strlen($before),
        'newline_count_before' => substr_count($before, "\n"),
        'newline_count_after_full' => substr_count($fullAfter, "\n"),
        'transformations' => detectTransformations($before, $fullAfter, $entityOnlyAfter),
        'entity_only_matches_full' => $entityOnlyAfter === $fullAfter,
        'head_before' => snippet($before, true),
        'tail_before' => snippet($before, false),
        'head_after_full' => snippet($fullAfter, true),
        'tail_after_full' => snippet($fullAfter, false),
        'head_after_entity_only' => snippet($entityOnlyAfter, true),
        'tail_after_entity_only' => snippet($entityOnlyAfter, false),
    ];
}

usort($analyses, static fn (array $a, array $b): int => abs($b['char_diff_full']) <=> abs($a['char_diff_full']));

$top10 = array_slice($analyses, 0, 10);
$job34 = null;

foreach ($analyses as $row) {
    if ($row['job_id'] === 34) {
        $job34 = $row;
        break;
    }
}

$payload = [
    'generated_at' => Carbon::now()->toIso8601String(),
    'scope' => 'entity_decoding jobs from DESCRIPTION_BACKFILL_REVIEW.json',
    'total_entity_jobs' => count($analyses),
    'entity_only_would_differ_from_full' => count(array_filter($analyses, static fn (array $r): bool => ! $r['entity_only_matches_full'])),
    'job_34' => $job34,
    'top_10_largest_char_diff' => $top10,
    'all_analyses' => $analyses,
];

file_put_contents(OUTPUT_JSON, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

$md = [];
$md[] = '# Description Entity Impact Analysis';
$md[] = '';
$md[] = 'Generated: '.$payload['generated_at'];
$md[] = 'READ-ONLY — no production DB changes.';
$md[] = '';
$md[] = '## Summary';
$md[] = '';
$md[] = '- Entity jobs analyzed: '.count($analyses);
$md[] = '- Full normalizer differs from entity-only: '.$payload['entity_only_would_differ_from_full'].' / '.count($analyses);
$md[] = '';
$md[] = '## Recommendation: EntityOnlyDescriptionCleanupService';
$md[] = '';
$md[] = 'Entity cleanup can be separated from whitespace collapse. A dedicated service should apply only:';
$md[] = '- `html_entity_decode(ENT_QUOTES | ENT_HTML5)`';
$md[] = '- NBSP (`&nbsp;`, UTF-8 `\\xc2\\xa0`) → normal space';
$md[] = '';
$md[] = 'It should NOT apply: whitespace collapse, newline removal, strip_tags (unless tags present).';
$md[] = '';

foreach (array_filter([$job34, ...$top10]) as $row) {
    if ($row === null) {
        continue;
    }

    $md[] = '### Job #'.$row['job_id'].' — '.$row['title'];
    $md[] = '';
    $md[] = '- Provider: '.$row['provider'];
    $md[] = '- Original length: '.$row['original_length'];
    $md[] = '- Proposed (full normalizer): '.$row['proposed_full_length'].' ('.$row['char_diff_full'].')';
    $md[] = '- Proposed (entity-only): '.$row['proposed_entity_only_length'].' ('.$row['char_diff_entity_only'].')';
    $md[] = '- Newlines before/after full: '.$row['newline_count_before'].' → '.$row['newline_count_after_full'];
    $md[] = '- Transformations: '.implode(', ', $row['transformations']);
    $md[] = '- Entity-only equals full: '.($row['entity_only_matches_full'] ? 'yes' : 'no');
    $md[] = '';
    $md[] = '#### First 500 chars BEFORE';
    $md[] = '```';
    $md[] = $row['head_before'];
    $md[] = '```';
    $md[] = '';
    $md[] = '#### First 500 chars AFTER (full normalizer)';
    $md[] = '```';
    $md[] = $row['head_after_full'];
    $md[] = '```';
    $md[] = '';
    $md[] = '#### Last 500 chars BEFORE';
    $md[] = '```';
    $md[] = $row['tail_before'];
    $md[] = '```';
    $md[] = '';
    $md[] = '#### Last 500 chars AFTER (full normalizer)';
    $md[] = '```';
    $md[] = $row['tail_after_full'];
    $md[] = '```';
    $md[] = '';
}

file_put_contents(OUTPUT_MD, implode("\n", $md));

echo "Written:\n  ".OUTPUT_JSON."\n  ".OUTPUT_MD."\n";
echo 'Job #34 char diff (full): '.($job34['char_diff_full'] ?? 'N/A')."\n";
echo 'Top char diff job: #'.($top10[0]['job_id'] ?? '?').' diff='.($top10[0]['char_diff_full'] ?? '?')."\n";

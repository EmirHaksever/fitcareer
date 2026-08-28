<?php

declare(strict_types=1);

/**
 * Production data quality audit — READ-ONLY diagnostic.
 * Does NOT modify DB, cache, queue, or external APIs.
 */

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Enums\JobOrigin;
use App\Enums\JobStatus;
use App\Enums\ScrapeStatus;
use App\Enums\TurkeyLocationCategory;
use App\Models\Job;
use App\Models\JobSource;
use App\Services\Scraper\DTO\LocationInput;
use App\Services\Scraper\LocationClassificationService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

const OUTPUT_JSON = __DIR__.'/../PRODUCTION_DATA_QUALITY_AUDIT.json';
const OUTPUT_MD = __DIR__.'/../PRODUCTION_DATA_QUALITY_AUDIT.md';
const CHUNK_SIZE = 500;
const SAMPLE_LIMIT = 10;
const MAX_POSTING_AGE_DAYS = 365;

$now = Carbon::now();
$classifier = new LocationClassificationService;

$sources = JobSource::query()->orderBy('name')->get();
$sourceMap = [];
$providerSources = [];

foreach ($sources as $source) {
    $provider = (string) ($source->config['provider'] ?? 'unknown');
    $sourceMap[$source->id] = [
        'id' => $source->id,
        'name' => $source->name,
        'provider' => $provider,
        'is_active' => (bool) $source->is_active,
        'last_success_at' => $source->last_success_at?->toIso8601String(),
        'last_failure_at' => $source->last_failure_at?->toIso8601String(),
        'consecutive_failures' => $source->consecutive_failures,
        'last_items_found' => $source->last_items_found,
    ];
    $providerSources[$provider][] = $source->id;
}

$scrapedBase = static fn () => Job::query()->where('source', JobOrigin::Scraped);

$dataset = [
    'generated_at' => $now->toIso8601String(),
    'scope' => 'published scraped jobs (+ lifecycle counts for all scraped)',
    'total_published_scraped' => (clone $scrapedBase())->where('status', JobStatus::Published)->count(),
    'total_active_scraped' => (clone $scrapedBase())
        ->where('status', JobStatus::Published)
        ->where(function ($q): void {
            $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
        })
        ->count(),
    'total_inactive_scraped' => (clone $scrapedBase())
        ->where('status', '!=', JobStatus::Published)
        ->count(),
    'total_deleted_scraped' => (clone $scrapedBase())->onlyTrashed()->count(),
    'source_count' => $sources->count(),
    'active_source_count' => $sources->where('is_active', true)->count(),
    'provider_count' => count($providerSources),
    'unique_companies_raw' => (clone $scrapedBase())
        ->where('status', JobStatus::Published)
        ->whereNotNull('source_company_name')
        ->where('source_company_name', '!=', '')
        ->distinct()
        ->count('source_company_name'),
];

$ageBuckets = [
    '0_7' => 0,
    '8_30' => 0,
    '31_60' => 0,
    '61_90' => 0,
    '91_180' => 0,
    '181_365' => 0,
    '366_plus' => 0,
];

$locationGlobal = initLocationBreakdown();
$locationByProvider = [];
$locationBySource = [];
$locationQuality = initLocationQuality();
$locationQualityByProvider = [];

$workTypeGlobal = ['onsite' => 0, 'hybrid' => 0, 'remote' => 0, 'null' => 0];
$workTypeByProvider = [];
$workTypeAnomalies = [
    'turkey_relevant_remote' => 0,
    'foreign_remote' => 0,
    'istanbul_remote' => 0,
    'istanbul_onsite' => 0,
    'istanbul_hybrid' => 0,
    'remote_tr_remote' => 0,
];

$fieldCompleteness = initFieldCompleteness();
$fieldCompletenessByProvider = [];

$descriptionQuality = [
    'null' => 0,
    'empty' => 0,
    'lt_100' => 0,
    '100_300' => 0,
    '300_1000' => 0,
    'gt_1000' => 0,
    'html_artifacts' => [
        '<script' => 0,
        '<style' => 0,
        '<div' => 0,
        '<p>' => 0,
        '&nbsp;' => 0,
    ],
];

$sourceMetrics = [];
foreach ($sourceMap as $sid => $meta) {
    $sourceMetrics[$sid] = initSourceMetrics($meta);
    $locationBySource[$sid] = initLocationBreakdown();
    $locationQualityByProvider[$meta['provider']] ??= initLocationQuality();
    $workTypeByProvider[$meta['provider']] ??= ['onsite' => 0, 'hybrid' => 0, 'remote' => 0, 'null' => 0];
    $fieldCompletenessByProvider[$meta['provider']] ??= initFieldCompleteness();
}

$knownPatterns = [
    'country_is_istanbul' => 0,
    'country_is_istanbul_ascii' => 0,
    'city_is_turkey' => 0,
    'city_is_turkiye' => 0,
    'country_is_tr' => 0,
    'remote_only' => 0,
    'remote_europe' => 0,
    'remote_worldwide' => 0,
    'remote_emea' => 0,
    'europe' => 0,
    'worldwide' => 0,
    'americas' => 0,
];

$patternSamples = array_fill_keys(array_keys($knownPatterns), []);
$turkeyCompanies = [];
$istanbulCompanies = [];
$agesForMedian = [];
$publishedScrapedCount = 0;

$jobRowsForDupes = [];

Job::query()
    ->select([
        'id',
        'job_source_id',
        'title',
        'description',
        'source_company_name',
        'external_id',
        'external_url',
        'published_at',
        'city',
        'country',
        'employment_type',
        'work_type',
        'scrape_status',
        'last_seen_at',
        'last_scraped_at',
        'status',
        'expires_at',
    ])
    ->where('source', JobOrigin::Scraped)
    ->where('status', JobStatus::Published)
    ->orderBy('id')
    ->chunkById(CHUNK_SIZE, function ($jobs) use (
        &$locationGlobal,
        &$locationByProvider,
        &$locationBySource,
        &$locationQuality,
        &$locationQualityByProvider,
        &$workTypeGlobal,
        &$workTypeByProvider,
        &$workTypeAnomalies,
        &$fieldCompleteness,
        &$fieldCompletenessByProvider,
        &$descriptionQuality,
        &$sourceMetrics,
        &$knownPatterns,
        &$patternSamples,
        &$turkeyCompanies,
        &$istanbulCompanies,
        &$agesForMedian,
        &$ageBuckets,
        &$publishedScrapedCount,
        &$jobRowsForDupes,
        $sourceMap,
        $classifier,
        $now,
    ): void {
        foreach ($jobs as $job) {
            $publishedScrapedCount++;
            $meta = $sourceMap[$job->job_source_id] ?? ['provider' => 'unknown', 'name' => 'unknown', 'id' => $job->job_source_id];
            $provider = $meta['provider'];
            $sid = $job->job_source_id;

            $jobRowsForDupes[] = [
                'id' => $job->id,
                'job_source_id' => $sid,
                'provider' => $provider,
                'source_name' => $meta['name'],
                'title' => $job->title,
                'source_company_name' => $job->source_company_name,
                'external_id' => $job->external_id,
                'external_url' => $job->external_url,
                'city' => $job->city,
                'country' => $job->country,
            ];

            $result = $classifier->classify(LocationInput::fromSignals(
                $job->city,
                $job->country,
                $job->work_type,
            ));

            incrementLocationBreakdown($locationGlobal, $result);
            if (! isset($locationByProvider[$provider])) {
                $locationByProvider[$provider] = initLocationBreakdown();
            }
            incrementLocationBreakdown($locationByProvider[$provider], $result);
            incrementLocationBreakdown($locationBySource[$sid], $result);

            analyzeLocationQuality($job, $locationQuality, $knownPatterns, $patternSamples);
            if (! isset($locationQualityByProvider[$provider])) {
                $locationQualityByProvider[$provider] = initLocationQuality();
            }
            analyzeLocationQuality($job, $locationQualityByProvider[$provider], $knownPatterns, $patternSamples, false);

            $wt = $job->work_type?->value ?? 'null';
            if (! isset($workTypeByProvider[$provider])) {
                $workTypeByProvider[$provider] = ['onsite' => 0, 'hybrid' => 0, 'remote' => 0, 'null' => 0];
            }
            $workTypeGlobal[$wt === 'null' ? 'null' : $wt]++;
            $workTypeByProvider[$provider][$wt === 'null' ? 'null' : $wt]++;

            analyzeWorkTypeAnomalies($job, $result, $workTypeAnomalies);

            analyzeFieldCompleteness($job, $fieldCompleteness);
            if (! isset($fieldCompletenessByProvider[$provider])) {
                $fieldCompletenessByProvider[$provider] = initFieldCompleteness();
            }
            analyzeFieldCompleteness($job, $fieldCompletenessByProvider[$provider]);

            analyzeDescription($job->description, $descriptionQuality);

            updateSourceMetrics($sourceMetrics[$sid], $job, $result, $now);

            if ($result->isTurkeyRelevant && $job->source_company_name) {
                $turkeyCompanies[normalizeCompanyKey($job->source_company_name)] ??= $job->source_company_name;
            }
            if ($result->category === TurkeyLocationCategory::Istanbul && $job->source_company_name) {
                $istanbulCompanies[normalizeCompanyKey($job->source_company_name)] ??= $job->source_company_name;
            }

            if ($job->published_at) {
                $days = (int) $job->published_at->diffInDays($now);
                $agesForMedian[] = $days;
                bucketAge($ageBuckets, $days);
            }
        }
    });

$dataset['total_published_scraped'] = $publishedScrapedCount;

$freshnessByProvider = buildFreshnessByProvider($sourceMetrics, $locationByProvider);

foreach ($sourceMetrics as $sid => &$sm) {
    $total = max(1, $sm['total_jobs']);
    $sm['median_age_days'] = median($sm['ages']);
    $sm['average_age_days'] = $sm['ages'] !== [] ? round(array_sum($sm['ages']) / count($sm['ages']), 1) : null;
    unset($sm['ages']);
    $sm['turkey_relevance_pct'] = round(($sm['turkey_jobs'] / $total) * 100, 2);
    $sm['freshness_lte_30d_pct'] = round(($sm['jobs_lte_30_days'] / $total) * 100, 2);
    $sm['freshness_lte_90d_pct'] = round(($sm['jobs_lte_90_days'] / $total) * 100, 2);
    $sm['description_completeness_pct'] = fieldPct($sm['field_populated']['description'], $total);
    $sm['location_completeness_pct'] = round((($sm['field_populated']['city'] + $sm['field_populated']['country']) / ($total * 2)) * 100, 2);
    $sm['work_type_completeness_pct'] = round(($sm['field_populated']['work_type'] / $total) * 100, 2);
    $sm['foreign_noise_pct'] = round(($sm['foreign_jobs'] / $total) * 100, 2);
    $sm['unknown_location_pct'] = round(($sm['unknown_jobs'] / $total) * 100, 2);
}
unset($sm);

$duplicates = analyzeDuplicates($jobRowsForDupes);
$companyCandidates = analyzeCompanyCandidates($jobRowsForDupes);
$crossSourceOverlap = analyzeCrossSourceCompanyOverlap($jobRowsForDupes, $sourceMap);

$ghostJobReadiness = buildGhostJobReadiness($sourceMap, $scrapedBase);

$problems = classifyProblems(
    $dataset,
    $locationGlobal,
    $locationByProvider,
    $locationQuality,
    $locationQualityByProvider,
    $duplicates,
    $descriptionQuality,
    $sourceMetrics,
    $ghostJobReadiness,
);

$recommendations = buildRecommendations($problems, $sourceMetrics, $locationByProvider, $duplicates, $ghostJobReadiness);

$providerComparison = rankProviders($sourceMetrics, $locationByProvider);

$report = [
    'meta' => [
        'generated_at' => $now->toIso8601String(),
        'mode' => 'read_only',
        'db_writes' => false,
        'classifier' => LocationClassificationService::class,
        'max_posting_age_days_reference' => MAX_POSTING_AGE_DAYS,
        'freshness_service_reference' => 'ScrapedJobFreshnessService uses stale_after_hours / expire_after_hours on last_seen_at',
    ],
    'dataset' => $dataset,
    'coverage' => [
        'location' => $locationGlobal,
        'unique_turkey_companies' => count($turkeyCompanies),
        'unique_istanbul_companies' => count($istanbulCompanies),
        'unique_turkey_company_names' => array_values($turkeyCompanies),
    ],
    'provider_comparison' => $providerComparison,
    'location_by_provider' => $locationByProvider,
    'location_by_source' => (function () use ($locationBySource, $sourceMap): array {
        $out = [];
        foreach ($locationBySource as $sid => $row) {
            $out[] = [
                'source_id' => $sid,
                'source' => $sourceMap[$sid]['name'] ?? (string) $sid,
                'provider' => $sourceMap[$sid]['provider'] ?? 'unknown',
                ...$row,
            ];
        }

        return $out;
    })(),
    'location_quality' => [
        'global' => $locationQuality,
        'by_provider' => $locationQualityByProvider,
        'known_patterns' => $knownPatterns,
        'pattern_samples' => array_map(fn (array $s) => array_slice($s, 0, SAMPLE_LIMIT), $patternSamples),
    ],
    'work_type' => [
        'global' => $workTypeGlobal,
        'by_provider' => $workTypeByProvider,
        'anomalies' => $workTypeAnomalies,
    ],
    'field_completeness' => [
        'global' => formatCompleteness($fieldCompleteness, $publishedScrapedCount),
        'by_provider' => (function () use ($fieldCompletenessByProvider, $locationByProvider): array {
            $out = [];
            foreach ($fieldCompletenessByProvider as $provider => $fc) {
                $out[$provider] = formatCompleteness($fc, $locationByProvider[$provider]['total'] ?? 0);
            }

            return $out;
        })(),
    ],
    'description_quality' => $descriptionQuality,
    'duplicates' => $duplicates,
    'company_quality' => $companyCandidates,
    'cross_source_company_overlap' => $crossSourceOverlap,
    'freshness' => [
        'global_age_buckets_days' => $ageBuckets,
        'median_age_days' => median($agesForMedian),
        'average_age_days' => $agesForMedian !== [] ? round(array_sum($agesForMedian) / count($agesForMedian), 1) : null,
        'by_provider' => $freshnessByProvider,
        'by_source' => array_values($sourceMetrics),
    ],
    'ghost_job_readiness' => $ghostJobReadiness,
    'problems' => $problems,
    'recommendations' => $recommendations,
];

file_put_contents(OUTPUT_JSON, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
file_put_contents(OUTPUT_MD, renderMarkdown($report, $sourceMap));

echo 'Audit complete.'.PHP_EOL;
echo 'JSON: '.OUTPUT_JSON.PHP_EOL;
echo 'MD:   '.OUTPUT_MD.PHP_EOL;
echo 'Published scraped jobs analyzed: '.$publishedScrapedCount.PHP_EOL;

// --- helpers ---

function initLocationBreakdown(): array
{
    return [
        'total' => 0,
        'turkey_relevant' => 0,
        'istanbul' => 0,
        'other_turkey' => 0,
        'remote_tr' => 0,
        'foreign' => 0,
        'unknown' => 0,
    ];
}

function incrementLocationBreakdown(array &$b, App\Services\Scraper\DTO\LocationClassificationResult $r): void
{
    $b['total']++;
    if ($r->isTurkeyRelevant) {
        $b['turkey_relevant']++;
    }
    match ($r->category) {
        TurkeyLocationCategory::Istanbul => $b['istanbul']++,
        TurkeyLocationCategory::OtherTurkey => $b['other_turkey']++,
        TurkeyLocationCategory::RemoteTurkey => $b['remote_tr']++,
        TurkeyLocationCategory::Foreign => $b['foreign']++,
        TurkeyLocationCategory::Unknown => $b['unknown']++,
    };
}

function initLocationQuality(): array
{
    return [
        'city_null' => 0,
        'country_null' => 0,
        'both_null' => 0,
        'city_only' => 0,
        'country_only' => 0,
        'empty_city' => 0,
        'empty_country' => 0,
        'whitespace_city' => 0,
        'whitespace_country' => 0,
    ];
}

function initFieldCompleteness(): array
{
    return [
        'title' => ['populated' => 0, 'null' => 0, 'empty' => 0],
        'description' => ['populated' => 0, 'null' => 0, 'empty' => 0],
        'source_company_name' => ['populated' => 0, 'null' => 0, 'empty' => 0],
        'external_id' => ['populated' => 0, 'null' => 0, 'empty' => 0],
        'external_url' => ['populated' => 0, 'null' => 0, 'empty' => 0],
        'published_at' => ['populated' => 0, 'null' => 0, 'empty' => 0],
        'city' => ['populated' => 0, 'null' => 0, 'empty' => 0],
        'country' => ['populated' => 0, 'null' => 0, 'empty' => 0],
        'employment_type' => ['populated' => 0, 'null' => 0, 'empty' => 0],
        'work_type' => ['populated' => 0, 'null' => 0, 'empty' => 0],
    ];
}

function initSourceMetrics(array $meta): array
{
    return [
        'source_id' => $meta['id'],
        'source_name' => $meta['name'],
        'provider' => $meta['provider'],
        'total_jobs' => 0,
        'turkey_jobs' => 0,
        'istanbul_jobs' => 0,
        'foreign_jobs' => 0,
        'unknown_jobs' => 0,
        'jobs_lte_7_days' => 0,
        'jobs_lte_30_days' => 0,
        'jobs_lte_60_days' => 0,
        'jobs_lte_90_days' => 0,
        'jobs_lte_180_days' => 0,
        'jobs_lte_365_days' => 0,
        'jobs_gt_365_days' => 0,
        'latest_published_at' => null,
        'oldest_published_at' => null,
        'median_age_days' => null,
        'average_age_days' => null,
        'ages' => [],
        'field_populated' => [
            'description' => 0,
            'city' => 0,
            'country' => 0,
            'work_type' => 0,
        ],
        'duplicate_url_count' => 0,
        'scrape_status' => ['success' => 0, 'stale' => 0, 'failed' => 0, 'other' => 0],
    ];
}

function analyzeLocationQuality(Job $job, array &$q, array &$patterns, array &$samples, bool $collectSamples = true): void
{
    $city = $job->city;
    $country = $job->country;

    if ($city === null) {
        $q['city_null']++;
    } elseif (trim($city) === '') {
        $q['empty_city']++;
    } elseif (trim($city) !== $city) {
        $q['whitespace_city']++;
    }

    if ($country === null) {
        $q['country_null']++;
    } elseif (trim($country) === '') {
        $q['empty_country']++;
    } elseif (trim($country) !== $country) {
        $q['whitespace_country']++;
    }

    if (($city === null || trim((string) $city) === '') && ($country === null || trim((string) $country) === '')) {
        $q['both_null']++;
    } elseif (($city !== null && trim($city) !== '') && ($country === null || trim((string) $country) === '')) {
        $q['city_only']++;
    } elseif (($country !== null && trim($country) !== '') && ($city === null || trim((string) $city) === '')) {
        $q['country_only']++;
    }

    $blob = mb_strtolower(trim(implode(' | ', array_filter([$city, $country]))));

    $checks = [
        'country_is_istanbul' => fn () => mb_strtolower(trim((string) $country)) === 'istanbul' || mb_strtolower(trim((string) $country)) === 'i̇stanbul',
        'country_is_istanbul_ascii' => fn () => mb_strtolower(trim((string) $country)) === 'istanbul',
        'city_is_turkey' => fn () => in_array(mb_strtolower(trim((string) $city)), ['turkey', 'turkiye', 'türkiye'], true),
        'city_is_turkiye' => fn () => mb_strtolower(trim((string) $city)) === 'türkiye',
        'country_is_tr' => fn () => mb_strtolower(trim((string) $country)) === 'tr',
        'remote_only' => fn () => $blob === 'remote',
        'remote_europe' => fn () => str_contains($blob, 'remote') && str_contains($blob, 'europe'),
        'remote_worldwide' => fn () => str_contains($blob, 'remote') && str_contains($blob, 'worldwide'),
        'remote_emea' => fn () => str_contains($blob, 'remote') && str_contains($blob, 'emea'),
        'europe' => fn () => $blob === 'europe' || str_contains($blob, ' europe'),
        'worldwide' => fn () => str_contains($blob, 'worldwide'),
        'americas' => fn () => str_contains($blob, 'americas'),
    ];

    foreach ($checks as $key => $fn) {
        if ($fn()) {
            $patterns[$key]++;
            if ($collectSamples && count($samples[$key]) < SAMPLE_LIMIT) {
                $samples[$key][] = ['id' => $job->id, 'city' => $city, 'country' => $country, 'title' => $job->title];
            }
        }
    }
}

function analyzeWorkTypeAnomalies(Job $job, App\Services\Scraper\DTO\LocationClassificationResult $r, array &$a): void
{
    $wt = $job->work_type?->value;
    if ($r->isTurkeyRelevant && $wt === 'remote') {
        $a['turkey_relevant_remote']++;
    }
    if ($r->category === TurkeyLocationCategory::Foreign && $wt === 'remote') {
        $a['foreign_remote']++;
    }
    if ($r->category === TurkeyLocationCategory::Istanbul && $wt === 'remote') {
        $a['istanbul_remote']++;
    }
    if ($r->category === TurkeyLocationCategory::Istanbul && $wt === 'onsite') {
        $a['istanbul_onsite']++;
    }
    if ($r->category === TurkeyLocationCategory::Istanbul && $wt === 'hybrid') {
        $a['istanbul_hybrid']++;
    }
    if ($r->category === TurkeyLocationCategory::RemoteTurkey && $wt === 'remote') {
        $a['remote_tr_remote']++;
    }
}

function analyzeFieldCompleteness(Job $job, array &$fc): void
{
    foreach (array_keys($fc) as $field) {
        $val = $job->{$field};
        if ($val === null) {
            $fc[$field]['null']++;
        } elseif (is_string($val) && trim($val) === '') {
            $fc[$field]['empty']++;
        } else {
            $fc[$field]['populated']++;
        }
    }
}

function analyzeDescription(?string $description, array &$dq): void
{
    if ($description === null) {
        $dq['null']++;

        return;
    }
    $trimmed = trim($description);
    if ($trimmed === '') {
        $dq['empty']++;

        return;
    }
    $len = mb_strlen($trimmed);
    if ($len < 100) {
        $dq['lt_100']++;
    } elseif ($len <= 300) {
        $dq['100_300']++;
    } elseif ($len <= 1000) {
        $dq['300_1000']++;
    } else {
        $dq['gt_1000']++;
    }

    foreach (array_keys($dq['html_artifacts']) as $pattern) {
        if (stripos($trimmed, $pattern) !== false) {
            $dq['html_artifacts'][$pattern]++;
        }
    }
}

function updateSourceMetrics(array &$sm, Job $job, App\Services\Scraper\DTO\LocationClassificationResult $r, Carbon $now): void
{
    $sm['total_jobs']++;
    if ($r->isTurkeyRelevant) {
        $sm['turkey_jobs']++;
    }
    if ($r->category === TurkeyLocationCategory::Istanbul) {
        $sm['istanbul_jobs']++;
    }
    if ($r->category === TurkeyLocationCategory::Foreign) {
        $sm['foreign_jobs']++;
    }
    if ($r->category === TurkeyLocationCategory::Unknown) {
        $sm['unknown_jobs']++;
    }

    if ($job->published_at) {
        $iso = $job->published_at->toIso8601String();
        $sm['latest_published_at'] = $sm['latest_published_at'] === null || $iso > $sm['latest_published_at'] ? $iso : $sm['latest_published_at'];
        $sm['oldest_published_at'] = $sm['oldest_published_at'] === null || $iso < $sm['oldest_published_at'] ? $iso : $sm['oldest_published_at'];
        $days = (int) $job->published_at->diffInDays($now);
        $sm['ages'][] = $days;
        if ($days <= 7) {
            $sm['jobs_lte_7_days']++;
        }
        if ($days <= 30) {
            $sm['jobs_lte_30_days']++;
        }
        if ($days <= 60) {
            $sm['jobs_lte_60_days']++;
        }
        if ($days <= 90) {
            $sm['jobs_lte_90_days']++;
        }
        if ($days <= 180) {
            $sm['jobs_lte_180_days']++;
        }
        if ($days <= 365) {
            $sm['jobs_lte_365_days']++;
        } else {
            $sm['jobs_gt_365_days']++;
        }
    }

    if ($job->description !== null && trim($job->description) !== '') {
        $sm['field_populated']['description']++;
    }
    if ($job->city !== null && trim($job->city) !== '') {
        $sm['field_populated']['city']++;
    }
    if ($job->country !== null && trim($job->country) !== '') {
        $sm['field_populated']['country']++;
    }
    if ($job->work_type !== null) {
        $sm['field_populated']['work_type']++;
    }

    $ss = $job->scrape_status?->value ?? 'other';
    if (isset($sm['scrape_status'][$ss])) {
        $sm['scrape_status'][$ss]++;
    } else {
        $sm['scrape_status']['other']++;
    }
}

function bucketAge(array &$buckets, int $days): void
{
    if ($days <= 7) {
        $buckets['0_7']++;
    } elseif ($days <= 30) {
        $buckets['8_30']++;
    } elseif ($days <= 60) {
        $buckets['31_60']++;
    } elseif ($days <= 90) {
        $buckets['61_90']++;
    } elseif ($days <= 180) {
        $buckets['91_180']++;
    } elseif ($days <= 365) {
        $buckets['181_365']++;
    } else {
        $buckets['366_plus']++;
    }
}

function formatCompleteness(array $fc, int $total): array
{
    $total = max(1, $total);
    $out = [];
    foreach ($fc as $field => $counts) {
        $out[$field] = [
            'populated' => $counts['populated'],
            'null' => $counts['null'],
            'empty' => $counts['empty'],
            'completeness_pct' => round(($counts['populated'] / $total) * 100, 2),
        ];
    }

    return $out;
}

function fieldPct(int $populated, int $total): float
{
    return round(($populated / max(1, $total)) * 100, 2);
}

function median(array $values): ?float
{
    if ($values === []) {
        return null;
    }
    sort($values);
    $c = count($values);
    $mid = (int) floor($c / 2);

    return $c % 2 === 0 ? ($values[$mid - 1] + $values[$mid]) / 2 : (float) $values[$mid];
}

function normalizeCompanyKey(?string $name): string
{
    $n = mb_strtolower(trim((string) $name));
    $n = preg_replace('/[^\p{L}\p{N}\s]+/u', '', $n) ?? $n;
    $n = preg_replace('/\s+/', ' ', $n) ?? $n;

    return $n;
}

function normalizeTitleKey(?string $title): string
{
    $t = mb_strtolower(trim((string) $title));
    $t = preg_replace('/\s+/', ' ', $t) ?? $t;

    return $t;
}

function analyzeDuplicates(array $rows): array
{
    $types = [
        'A_exact_external_url' => [],
        'B_exact_external_id_source' => [],
        'C_external_id_cross_source' => [],
        'D_title_company' => [],
        'E_normalized_title_company' => [],
        'F_title_company_city' => [],
        'G_title_company_location' => [],
    ];

    $group = static function (array $rows, callable $keyFn): array {
        $groups = [];
        foreach ($rows as $row) {
            $key = $keyFn($row);
            if ($key === '' || $key === '|') {
                continue;
            }
            $groups[$key][] = $row;
        }

        return array_filter($groups, static fn (array $g): bool => count($g) > 1);
    };

    $types['A_exact_external_url'] = $group($rows, fn ($r) => trim((string) ($r['external_url'] ?? '')));
    $types['B_exact_external_id_source'] = $group($rows, fn ($r) => ($r['job_source_id'] ?? '').'|'.($r['external_id'] ?? ''));
    $types['C_external_id_cross_source'] = array_filter(
        $group($rows, fn ($r) => (string) ($r['external_id'] ?? '')),
        static function (array $g): bool {
            $sources = array_unique(array_column($g, 'job_source_id'));

            return count($sources) > 1;
        },
    );
    $types['D_title_company'] = $group($rows, fn ($r) => ($r['title'] ?? '').'|'.($r['source_company_name'] ?? ''));
    $types['E_normalized_title_company'] = $group($rows, fn ($r) => normalizeTitleKey($r['title'] ?? '').'|'.normalizeCompanyKey($r['source_company_name'] ?? ''));
    $types['F_title_company_city'] = $group($rows, fn ($r) => ($r['title'] ?? '').'|'.($r['source_company_name'] ?? '').'|'.($r['city'] ?? ''));
    $types['G_title_company_location'] = $group($rows, fn ($r) => ($r['title'] ?? '').'|'.($r['source_company_name'] ?? '').'|'.($r['city'] ?? '').'|'.($r['country'] ?? ''));

    $out = [];
    foreach ($types as $type => $groups) {
        $affected = array_sum(array_map('count', $groups));
        $crossSource = 0;
        foreach ($groups as $groupRows) {
            if (count(array_unique(array_column($groupRows, 'job_source_id'))) > 1) {
                $crossSource++;
            }
        }
        $samples = [];
        foreach (array_slice($groups, 0, SAMPLE_LIMIT, true) as $key => $groupRows) {
            $samples[] = [
                'key' => $key,
                'count' => count($groupRows),
                'providers' => array_values(array_unique(array_column($groupRows, 'provider'))),
                'jobs' => array_slice($groupRows, 0, 5),
            ];
        }
        $out[$type] = [
            'duplicate_group_count' => count($groups),
            'affected_job_count' => $affected,
            'cross_source_group_count' => $crossSource,
            'sample_groups' => $samples,
        ];
    }

    return $out;
}

function analyzeCompanyCandidates(array $rows): array
{
    $byKey = [];
    foreach ($rows as $row) {
        if (! $row['source_company_name']) {
            continue;
        }
        $key = normalizeCompanyKey($row['source_company_name']);
        $byKey[$key]['variants'][$row['source_company_name']] = ($byKey[$key]['variants'][$row['source_company_name']] ?? 0) + 1;
        $byKey[$key]['job_count'] = ($byKey[$key]['job_count'] ?? 0) + 1;
    }

    $candidates = [];
    foreach ($byKey as $key => $data) {
        if (count($data['variants']) <= 1) {
            continue;
        }
        $candidates[] = [
            'normalized_key' => $key,
            'variants' => $data['variants'],
            'job_count' => $data['job_count'],
        ];
    }

    usort($candidates, fn ($a, $b) => $b['job_count'] <=> $a['job_count']);

    return [
        'null_company_jobs' => count(array_filter($rows, fn ($r) => $r['source_company_name'] === null || trim((string) $r['source_company_name']) === '')),
        'normalization_candidates' => array_slice($candidates, 0, 50),
    ];
}

function analyzeCrossSourceCompanyOverlap(array $rows, array $sourceMap): array
{
    $byCompany = [];
    foreach ($rows as $row) {
        $key = normalizeCompanyKey($row['source_company_name'] ?? '');
        if ($key === '') {
            continue;
        }
        $byCompany[$key]['display'] ??= $row['source_company_name'];
        $byCompany[$key]['sources'][$row['job_source_id']]['provider'] = $row['provider'];
        $byCompany[$key]['sources'][$row['job_source_id']]['source_name'] = $row['source_name'];
        $byCompany[$key]['sources'][$row['job_source_id']]['job_count'] = ($byCompany[$key]['sources'][$row['job_source_id']]['job_count'] ?? 0) + 1;
    }

    $candidates = [];
    foreach ($byCompany as $key => $data) {
        $providers = array_unique(array_column($data['sources'], 'provider'));
        if (count($providers) < 2) {
            continue;
        }
        $candidates[] = [
            'company' => $data['display'],
            'normalized_key' => $key,
            'providers' => array_values($providers),
            'sources' => array_values($data['sources']),
            'total_jobs' => array_sum(array_column($data['sources'], 'job_count')),
            'matching_confidence' => 'normalized lowercase company name match',
        ];
    }

    usort($candidates, fn ($a, $b) => $b['total_jobs'] <=> $a['total_jobs']);

    return array_slice($candidates, 0, 50);
}

function buildFreshnessByProvider(array $sourceMetrics, array $locationByProvider): array
{
    $byProvider = [];
    foreach ($sourceMetrics as $sm) {
        $p = $sm['provider'];
        $byProvider[$p] ??= [
            'provider' => $p,
            'total_jobs' => 0,
            'jobs_gt_365_days' => 0,
            'jobs_lte_30_days' => 0,
            'oldest_published_at' => null,
            'latest_published_at' => null,
            'ages' => [],
        ];
        $byProvider[$p]['total_jobs'] += $sm['total_jobs'];
        $byProvider[$p]['jobs_gt_365_days'] += $sm['jobs_gt_365_days'];
        $byProvider[$p]['jobs_lte_30_days'] += $sm['jobs_lte_30_days'];
        $byProvider[$p]['ages'] = array_merge($byProvider[$p]['ages'], $sm['ages']);
        if ($sm['oldest_published_at'] && ($byProvider[$p]['oldest_published_at'] === null || $sm['oldest_published_at'] < $byProvider[$p]['oldest_published_at'])) {
            $byProvider[$p]['oldest_published_at'] = $sm['oldest_published_at'];
        }
        if ($sm['latest_published_at'] && ($byProvider[$p]['latest_published_at'] === null || $sm['latest_published_at'] > $byProvider[$p]['latest_published_at'])) {
            $byProvider[$p]['latest_published_at'] = $sm['latest_published_at'];
        }
    }

    foreach ($byProvider as &$row) {
        $row['median_age_days'] = median($row['ages']);
        $row['average_age_days'] = $row['ages'] !== [] ? round(array_sum($row['ages']) / count($row['ages']), 1) : null;
        $row['stale_rate_pct'] = $row['total_jobs'] > 0 ? round(($row['jobs_gt_365_days'] / $row['total_jobs']) * 100, 2) : 0;
        unset($row['ages']);
    }
    unset($row);

    return array_values($byProvider);
}

function buildGhostJobReadiness(array $sourceMap, callable $scrapedBase): array
{
    $signals = [
        'published_at' => ['available' => true, 'column' => 'published_at', 'coverage' => 'present on all scraped jobs analyzed'],
        'last_seen_at' => ['available' => true, 'column' => 'last_seen_at', 'coverage' => null],
        'last_scraped_at' => ['available' => true, 'column' => 'last_scraped_at', 'coverage' => null],
        'scrape_status' => ['available' => true, 'column' => 'scrape_status', 'values' => array_column(ScrapeStatus::cases(), 'value')],
        'updated_at_job_row' => ['available' => true, 'column' => 'updated_at', 'note' => 'Laravel timestamps on jobs table'],
        'provider_updated_at' => ['available' => false, 'note' => 'Provider-specific updated_at not stored separately from published_at'],
        'first_seen_at' => ['available' => false, 'note' => 'No first_seen_at column on jobs table'],
        'ghost_job_score' => ['available' => false, 'note' => 'Not implemented'],
    ];

    $counts = (clone $scrapedBase())
        ->where('status', JobStatus::Published)
        ->selectRaw('
            SUM(CASE WHEN last_seen_at IS NOT NULL THEN 1 ELSE 0 END) as with_last_seen,
            SUM(CASE WHEN scrape_status = ? THEN 1 ELSE 0 END) as stale_count,
            SUM(CASE WHEN scrape_status = ? THEN 1 ELSE 0 END) as success_count,
            COUNT(*) as total
        ', [ScrapeStatus::Stale->value, ScrapeStatus::Success->value])
        ->first();

    $signals['last_seen_at']['coverage'] = pct((int) ($counts->with_last_seen ?? 0), (int) ($counts->total ?? 1)).'%';
    $signals['scrape_status']['stale_jobs'] = (int) ($counts->stale_count ?? 0);
    $signals['scrape_status']['success_jobs'] = (int) ($counts->success_count ?? 0);

    $health = [];
    foreach ($sourceMap as $meta) {
        $health[] = [
            'source' => $meta['name'],
            'provider' => $meta['provider'],
            'last_success_at' => $meta['last_success_at'],
            'consecutive_failures' => $meta['consecutive_failures'],
        ];
    }

    return [
        'signals' => $signals,
        'source_health_snapshot' => $health,
        'duplicate_repost_proxy' => 'duplicate groups in audit duplicates section',
        'sufficient_for_v1_ghost_detection' => 'partial — published_at + last_seen_at + scrape_status available; provider updated_at and first_seen_at missing',
    ];
}

function pct(int|float $num, int|float $den): float
{
    return round(($num / max(1, $den)) * 100, 2);
}

function rankProviders(array $sourceMetrics, array $locationByProvider): array
{
    $byProvider = [];
    foreach ($sourceMetrics as $sm) {
        $p = $sm['provider'];
        $byProvider[$p] ??= [
            'provider' => $p,
            'total_jobs' => 0,
            'turkey_jobs' => 0,
            'istanbul_jobs' => 0,
            'foreign_jobs' => 0,
            'unknown_jobs' => 0,
            'jobs_lte_30_days' => 0,
            'jobs_gt_365_days' => 0,
            'description_populated' => 0,
            'city_populated' => 0,
            'country_populated' => 0,
        ];
        $byProvider[$p]['total_jobs'] += $sm['total_jobs'];
        $byProvider[$p]['turkey_jobs'] += $sm['turkey_jobs'];
        $byProvider[$p]['istanbul_jobs'] += $sm['istanbul_jobs'];
        $byProvider[$p]['foreign_jobs'] += $sm['foreign_jobs'];
        $byProvider[$p]['unknown_jobs'] += $sm['unknown_jobs'];
        $byProvider[$p]['jobs_lte_30_days'] += $sm['jobs_lte_30_days'];
        $byProvider[$p]['jobs_gt_365_days'] += $sm['jobs_gt_365_days'];
        $byProvider[$p]['description_populated'] += $sm['field_populated']['description'];
        $byProvider[$p]['city_populated'] += $sm['field_populated']['city'];
        $byProvider[$p]['country_populated'] += $sm['field_populated']['country'];
    }

    foreach ($byProvider as &$row) {
        $t = max(1, $row['total_jobs']);
        $row['turkey_relevance_pct'] = round(($row['turkey_jobs'] / $t) * 100, 2);
        $row['foreign_noise_pct'] = round(($row['foreign_jobs'] / $t) * 100, 2);
        $row['freshness_lte_30d_pct'] = round(($row['jobs_lte_30_days'] / $t) * 100, 2);
        $row['stale_gt_365d_pct'] = round(($row['jobs_gt_365_days'] / $t) * 100, 2);
        $row['description_completeness_pct'] = round(($row['description_populated'] / $t) * 100, 2);
        $row['location_completeness_pct'] = round((($row['city_populated'] + $row['country_populated']) / ($t * 2)) * 100, 2);
    }
    unset($row);

    $rows = array_values($byProvider);
    usort($rows, fn ($a, $b) => $b['turkey_jobs'] <=> $a['turkey_jobs']);

    return $rows;
}

function classifyProblems(array $dataset, array $loc, array $locByProvider, array $locQ, array $locQByProvider, array $dupes, array $desc, array $sourceMetrics, array $ghost): array
{
    $p = ['P0' => [], 'P1' => [], 'P2' => [], 'P3' => []];

    if (($loc['unknown'] ?? 0) > 0) {
        $p['P1'][] = 'Unknown location jobs exist ('.$loc['unknown'].') — hidden from default search but indicate normalization gaps.';
    }

    foreach ($locByProvider as $provider => $row) {
        if ($row['total'] > 0 && ($row['foreign'] / $row['total']) >= 0.3 && $provider !== 'remotive') {
            $p['P1'][] = "Provider {$provider} has high foreign noise: {$row['foreign']}/{$row['total']} (".round($row['foreign'] / $row['total'] * 100, 1).'%)';
        }
    }

    foreach ($locQByProvider as $provider => $q) {
        $total = $locByProvider[$provider]['total'] ?? 1;
        if (($q['country_null'] / max(1, $total)) >= 0.2) {
            $p['P1'][] = "Provider {$provider} missing country on ".round($q['country_null'] / $total * 100, 1).'% of jobs';
        }
    }

    if (($dupes['A_exact_external_url']['duplicate_group_count'] ?? 0) > 0) {
        $p['P1'][] = 'Exact external_url duplicate groups found: '.$dupes['A_exact_external_url']['duplicate_group_count'];
    }
    if (($dupes['C_external_id_cross_source']['duplicate_group_count'] ?? 0) > 0) {
        $p['P2'][] = 'Cross-source external_id collisions: '.$dupes['C_external_id_cross_source']['duplicate_group_count'].' groups';
    }

    $htmlTotal = array_sum($desc['html_artifacts'] ?? []);
    if ($htmlTotal > 0) {
        $p['P2'][] = "Description HTML artifact detections: {$htmlTotal} jobs";
    }

    foreach ($sourceMetrics as $sm) {
        if ($sm['jobs_gt_365_days'] > 0) {
            $p['P2'][] = "Source {$sm['source_name']} has {$sm['jobs_gt_365_days']} published jobs older than 365 days (pre-ingest guard bypass or legacy data)";
        }
    }

    if (($ghost['signals']['first_seen_at']['available'] ?? false) === false) {
        $p['P2'][] = 'Ghost Job detection missing first_seen_at signal in DB';
    }

    $p['P3'][] = 'Company name normalization candidates exist — see company_quality.normalization_candidates';

    return $p;
}

function buildRecommendations(array $problems, array $sourceMetrics, array $locByProvider, array $dupes, array $ghost): array
{
    return [
        'A_fix_now' => array_merge($problems['P0'] ?? [], $problems['P1'] ?? []),
        'B_before_ghost_jobs' => [
            'Persist provider updated_at or first_seen_at for repost detection',
            'Reduce foreign noise on pilot boards (Greenhouse Medsien) if not intended',
            'Address country-null location rows for SQL/search parity',
            'Review jobs published_at > 365d still published',
        ],
        'C_next_sprint' => [
            'Ghost Job Score v1 using published_at + last_seen_at + scrape_status',
            'Company normalization layer (read-only candidates already identified)',
            'Description HTML cleanup on ingest for entity-encoded content',
        ],
        'D_do_not_touch_yet' => [
            'Ingestion pipeline architecture',
            'LocationClassificationService rules (unless P0 found)',
            'Scheduler/health services',
            'Frontend',
        ],
        'answers' => buildRecommendationAnswers($sourceMetrics, $locByProvider, $dupes, $ghost),
    ];
}

function buildRecommendationAnswers(array $sourceMetrics, array $locByProvider, array $dupes, array $ghost): array
{
    $providers = rankProviders($sourceMetrics, $locByProvider);
    $bestQuality = null;
    $bestScore = -1;
    foreach ($providers as $p) {
        $score = $p['description_completeness_pct'] + $p['location_completeness_pct'] + $p['turkey_relevance_pct'] - $p['foreign_noise_pct'];
        if ($score > $bestScore) {
            $bestScore = $score;
            $bestQuality = $p['provider'];
        }
    }

    $mostTr = $providers[0]['provider'] ?? 'n/a';
    $mostNoise = null;
    $maxNoise = -1;
    foreach ($providers as $p) {
        if ($p['foreign_noise_pct'] > $maxNoise) {
            $maxNoise = $p['foreign_noise_pct'];
            $mostNoise = $p['provider'];
        }
    }

    $hasDupes = ($dupes['D_title_company']['cross_source_group_count'] ?? 0) > 0
        || ($dupes['A_exact_external_url']['duplicate_group_count'] ?? 0) > 0;

    return [
        '1_best_quality_provider' => $bestQuality,
        '2_most_turkey_coverage_provider' => $mostTr,
        '3_most_global_noise_provider' => $mostNoise,
        '4_location_problem_providers' => array_keys(array_filter($locByProvider, fn ($r) => ($r['unknown'] ?? 0) > 0 || ($r['foreign'] ?? 0) / max(1, $r['total']) > 0.25)),
        '5_description_problem' => 'Check html_artifacts counts in description_quality',
        '6_duplicate_problem' => $hasDupes ? 'yes — see duplicates section' : 'minimal — no major cross-source URL/title overlap',
        '7_ghost_job_data_sufficient' => $ghost['sufficient_for_v1_ghost_detection'] ?? 'partial',
        '8_missing_signals' => ['first_seen_at', 'provider_updated_at', 'ghost_job_score'],
        '9_new_ats_still_worth_it' => 'yes for Turkey-dense boards; avoid high-global-noise boards without include_global UX',
        '10_next_technical_task' => 'Ghost Job Score v1 design using existing published_at + last_seen_at + scrape_status',
    ];
}

function renderMarkdown(array $report, array $sourceMap): string
{
    $loc = $report['coverage']['location'];
    $ds = $report['dataset'];
    $md = "# PRODUCTION DATA QUALITY AUDIT\n\n";
    $md .= 'Generated: '.$report['meta']['generated_at']."  \n";
    $md .= "**Mode:** READ-ONLY — no DB writes\n\n";

    $md .= "## Dataset\n\n";
    $md .= "| Metric | Value |\n|---|---:|\n";
    $md .= '| Published scraped jobs | '.$ds['total_published_scraped']." |\n";
    $md .= '| Active scraped (published + not expired) | '.$ds['total_active_scraped']." |\n";
    $md .= '| Inactive scraped (non-published status) | '.$ds['total_inactive_scraped']." |\n";
    $md .= '| Soft-deleted scraped | '.$ds['total_deleted_scraped']." |\n";
    $md .= '| Job sources | '.$ds['source_count']." |\n";
    $md .= '| Providers | '.$ds['provider_count']." |\n";
    $md .= '| Unique companies (raw) | '.$ds['unique_companies_raw']." |\n\n";

    $md .= "## Coverage\n\n";
    $md .= "| Category | Count |\n|---|---:|\n";
    foreach ($loc as $k => $v) {
        $md .= '| '.str_replace('_', ' ', ucfirst($k)).' | '.$v." |\n";
    }
    $md .= '| Unique Turkey companies | '.$report['coverage']['unique_turkey_companies']." |\n";
    $md .= '| Unique Istanbul companies | '.$report['coverage']['unique_istanbul_companies']." |\n\n";

    $md .= "## Provider Comparison\n\n";
    $md .= "| Provider | Total | TR | Istanbul | TR% | Foreign% | ≤30d% | >365d% | Desc% | Loc% |\n";
    $md .= "|---|---:|---:|---:|---:|---:|---:|---:|---:|---:|\n";
    foreach ($report['provider_comparison'] as $p) {
        $md .= sprintf(
            "| %s | %d | %d | %d | %.1f | %.1f | %.1f | %.1f | %.1f | %.1f |\n",
            $p['provider'],
            $p['total_jobs'],
            $p['turkey_jobs'],
            $p['istanbul_jobs'],
            $p['turkey_relevance_pct'],
            $p['foreign_noise_pct'],
            $p['freshness_lte_30d_pct'],
            $p['stale_gt_365d_pct'],
            $p['description_completeness_pct'],
            $p['location_completeness_pct'],
        );
    }
    $md .= "\n";

    $md .= "## Location Quality by Provider\n\n";
    $md .= "| Provider | Total | TR | Istanbul | Other TR | Remote TR | Foreign | Unknown |\n";
    $md .= "|---|---:|---:|---:|---:|---:|---:|---:|\n";
    foreach ($report['location_by_provider'] as $provider => $row) {
        $md .= sprintf(
            "| %s | %d | %d | %d | %d | %d | %d | %d |\n",
            $provider,
            $row['total'],
            $row['turkey_relevant'],
            $row['istanbul'],
            $row['other_turkey'],
            $row['remote_tr'],
            $row['foreign'],
            $row['unknown'],
        );
    }
    $md .= "\n";

    $md .= "## Field Completeness (global)\n\n";
    $md .= "| Field | Populated | Missing | Completeness |\n|---|---:|---:|---:|\n";
    foreach ($report['field_completeness']['global'] as $field => $row) {
        $missing = $row['null'] + $row['empty'];
        $md .= "| {$field} | {$row['populated']} | {$missing} | {$row['completeness_pct']}% |\n";
    }
    $md .= "\n";

    $md .= "## Description Quality\n\n";
    $dq = $report['description_quality'];
    $md .= "- null: {$dq['null']}\n";
    $md .= "- empty: {$dq['empty']}\n";
    $md .= "- <100 chars: {$dq['lt_100']}\n";
    $md .= "- 100-300 chars: {$dq['100_300']}\n";
    $md .= "- 300-1000 chars: {$dq['300_1000']}\n";
    $md .= "- >1000 chars: {$dq['gt_1000']}\n";
    $md .= "- HTML artifacts: ".json_encode($dq['html_artifacts'])."\n\n";

    $md .= "## Duplicate Analysis\n\n";
    foreach ($report['duplicates'] as $type => $d) {
        $md .= "### {$type}\n";
        $md .= "- Groups: {$d['duplicate_group_count']}\n";
        $md .= "- Affected jobs: {$d['affected_job_count']}\n";
        $md .= "- Cross-source groups: {$d['cross_source_group_count']}\n\n";
    }

    $md .= "## Company Overlap\n\n";
    $md .= 'Cross-source company candidates: '.count($report['cross_source_company_overlap'])."\n\n";

    $md .= "## Freshness\n\n";
    $f = $report['freshness'];
    $md .= '- Median age (days): '.($f['median_age_days'] ?? 'n/a')."\n";
    $md .= '- Average age (days): '.($f['average_age_days'] ?? 'n/a')."\n";
    $md .= '- Age buckets: '.json_encode($f['global_age_buckets_days'])."\n\n";

    $md .= "## Ghost Job Readiness\n\n";
    $md .= $report['ghost_job_readiness']['sufficient_for_v1_ghost_detection']."\n\n";

    $md .= "## Critical Problems\n\n";
    foreach ($report['problems'] as $pri => $items) {
        $md .= "### {$pri}\n";
        foreach ($items as $item) {
            $md .= "- {$item}\n";
        }
        $md .= "\n";
    }

    $md .= "## Recommendations\n\n";
    foreach ($report['recommendations'] as $section => $items) {
        if ($section === 'answers') {
            continue;
        }
        $md .= '### '.strtoupper(str_replace('_', ' ', $section))."\n";
        foreach ($items as $item) {
            $md .= is_string($item) ? "- {$item}\n" : '- '.json_encode($item)."\n";
        }
        $md .= "\n";
    }

    return $md;
}

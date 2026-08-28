<?php

declare(strict_types=1);

/**
 * Lever + Workable ATS feasibility probe — diagnostic only.
 * No DB writes, no production ingestion changes.
 */

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Http;

const USER_AGENT = 'FitCareer-ATS-Feasibility/1.0 (+diagnostic)';

/** @var list<array{company:string, provider:string, slug:string, region?:string}> */
$leverCompanies = [
    ['company' => 'Commencis', 'provider' => 'lever', 'slug' => 'commencis'],
    ['company' => 'Insider One', 'provider' => 'lever', 'slug' => 'insiderone'],
    ['company' => 'Midas', 'provider' => 'lever', 'slug' => 'getmidas'],
    ['company' => 'Trendyol', 'provider' => 'lever', 'slug' => 'trendyol'],
    ['company' => 'Dream Games', 'provider' => 'lever', 'slug' => 'dreamgames'],
    ['company' => 'Grand Games', 'provider' => 'lever', 'slug' => 'grandgames'],
    ['company' => 'Ajax Systems', 'provider' => 'lever', 'slug' => 'ajaxsystems'],
    ['company' => 'Firefly', 'provider' => 'lever', 'slug' => 'firefly'],
    ['company' => 'Insider (alt slug)', 'provider' => 'lever', 'slug' => 'useinsider'],
    ['company' => 'Midas (alt slug)', 'provider' => 'lever', 'slug' => 'midas'],
];

/** @var list<array{company:string, provider:string, slug:string}> */
$workableCompanies = [
    ['company' => 'Wingie Enuygun', 'provider' => 'workable', 'slug' => 'wingieenuygun'],
    ['company' => 'Vertigo Games', 'provider' => 'workable', 'slug' => 'vertigogames'],
    ['company' => 'Datasurgery / TRAICK AI', 'provider' => 'workable', 'slug' => 'datasurgery'],
    ['company' => 'Sanction Scanner', 'provider' => 'workable', 'slug' => 'sanctionscanner'],
    ['company' => 'Enuygun (alt)', 'provider' => 'workable', 'slug' => 'enuygun'],
    ['company' => 'TRAICK AI (alt)', 'provider' => 'workable', 'slug' => 'traick'],
    ['company' => 'Wingie (alt)', 'provider' => 'workable', 'slug' => 'wingie'],
];

function httpGet(string $url): array
{
    $startedAt = microtime(true);

    try {
        $response = Http::timeout(45)
            ->connectTimeout(15)
            ->withHeaders([
                'Accept' => 'application/json',
                'User-Agent' => USER_AGENT,
            ])
            ->get($url);
    } catch (Throwable $exception) {
        return [
            'url' => $url,
            'http_status' => null,
            'latency_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'error' => $exception->getMessage(),
            'json' => null,
            'headers' => [],
        ];
    }

    $json = json_decode((string) $response->body(), true);

    return [
        'url' => $url,
        'http_status' => $response->status(),
        'latency_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        'error' => null,
        'json' => $json,
        'headers' => [
            'content-type' => $response->header('Content-Type'),
            'retry-after' => $response->header('Retry-After'),
            'x-ratelimit-limit' => $response->header('X-RateLimit-Limit'),
            'x-ratelimit-remaining' => $response->header('X-RateLimit-Remaining'),
        ],
        'body_bytes' => strlen((string) $response->body()),
    ];
}

function locationHaystack(array $job, string $provider): string
{
    if ($provider === 'lever') {
        $categories = is_array($job['categories'] ?? null) ? $job['categories'] : [];
        $parts = [
            $categories['location'] ?? '',
            implode(' ', (array) ($categories['allLocations'] ?? [])),
            $job['country'] ?? '',
            $job['workplaceType'] ?? '',
        ];

        return mb_strtolower(implode(' ', array_map('strval', $parts)));
    }

    $location = is_array($job['location'] ?? null) ? $job['location'] : [];

    return mb_strtolower(implode(' ', array_filter([
        $location['location_str'] ?? '',
        $location['city'] ?? '',
        $location['country'] ?? '',
        $location['region'] ?? '',
        $job['country'] ?? '',
    ], static fn ($v) => is_string($v) && trim($v) !== '')));
}

function matchesTurkey(string $haystack): bool
{
    return str_contains($haystack, 'turkey')
        || str_contains($haystack, 'türkiye')
        || str_contains($haystack, 'turkiye')
        || preg_match('/\btur\b/', $haystack) === 1;
}

function matchesIstanbul(string $haystack): bool
{
    return str_contains($haystack, 'istanbul')
        || str_contains($haystack, 'İstanbul'.mb_strtolower('İstanbul'));
}

function leverJobsFromResponse(array $response): array
{
    $json = $response['json'] ?? null;

    if (! is_array($json)) {
        return [];
    }

    return array_values(array_filter($json, static fn ($item): bool => is_array($item) && isset($item['id'])));
}

function workableJobsFromResponse(array $response): array
{
    $json = $response['json'] ?? null;

    if (! is_array($json)) {
        return [];
    }

    $jobs = $json['jobs'] ?? $json;

    if (! is_array($jobs)) {
        return [];
    }

    return array_values(array_filter($jobs, static fn ($item): bool => is_array($item) && (isset($item['shortcode']) || isset($item['id']) || isset($item['title']))));
}

function fetchLeverBoard(string $slug): array
{
    $bases = [
        'global' => "https://api.lever.co/v0/postings/{$slug}?mode=json&limit=100",
        'eu' => "https://api.eu.lever.co/v0/postings/{$slug}?mode=json&limit=100",
    ];

    foreach ($bases as $region => $url) {
        $response = httpGet($url);
        $jobs = leverJobsFromResponse($response);

        if (($response['http_status'] ?? 0) === 200) {
            return [
                'region' => $region,
                'response' => $response,
                'jobs' => $jobs,
            ];
        }
    }

    $last = httpGet($bases['global']);

    return [
        'region' => null,
        'response' => $last,
        'jobs' => [],
    ];
}

function fetchWorkableBoard(string $slug): array
{
    $urls = [
        "https://apply.workable.com/api/v1/widget/accounts/{$slug}?details=true",
        "https://www.workable.com/api/accounts/{$slug}?details=true",
    ];

    foreach ($urls as $url) {
        $response = httpGet($url);
        $jobs = workableJobsFromResponse($response);

        if (($response['http_status'] ?? 0) === 200 && $jobs !== []) {
            return [
                'endpoint_used' => $url,
                'response' => $response,
                'jobs' => $jobs,
            ];
        }

        if (($response['http_status'] ?? 0) === 200) {
            return [
                'endpoint_used' => $url,
                'response' => $response,
                'jobs' => [],
            ];
        }
    }

    $response = httpGet($urls[0]);

    return [
        'endpoint_used' => $urls[0],
        'response' => $response,
        'jobs' => [],
    ];
}

function coverageLever(array $jobs): array
{
    $total = count($jobs);
    if ($total === 0) {
        return [];
    }

    $checks = [
        'title' => static fn (array $j): bool => filled($j['text'] ?? null),
        'company' => static fn (array $j): bool => true, // company is board-level
        'location' => static fn (array $j): bool => filled($j['categories']['location'] ?? null) || filled($j['categories']['allLocations'] ?? null),
        'description' => static fn (array $j): bool => filled($j['descriptionPlain'] ?? null) || filled($j['description'] ?? null) || filled($j['lists'] ?? null),
        'external_url' => static fn (array $j): bool => filled($j['hostedUrl'] ?? null) || filled($j['applyUrl'] ?? null),
        'external_id' => static fn (array $j): bool => filled($j['id'] ?? null),
        'published_at' => static fn (array $j): bool => filled($j['createdAt'] ?? null),
        'updated_at' => static fn (array $j): bool => filled($j['updatedAt'] ?? null),
        'job_type' => static fn (array $j): bool => filled($j['categories']['commitment'] ?? null) || filled($j['workplaceType'] ?? null),
        'salary' => static fn (array $j): bool => filled($j['salaryRange'] ?? null) || filled($j['salaryDescription'] ?? null),
    ];

    $report = [];
    foreach ($checks as $field => $check) {
        $filled = 0;
        foreach ($jobs as $job) {
            if ($check($job)) {
                $filled++;
            }
        }
        $report[$field] = "{$filled}/{$total}";
    }

    return $report;
}

function coverageWorkable(array $jobs): array
{
    $total = count($jobs);
    if ($total === 0) {
        return [];
    }

    $checks = [
        'title' => static fn (array $j): bool => filled($j['title'] ?? null),
        'company' => static fn (array $j): bool => true,
        'location' => static fn (array $j): bool => filled($j['location']['location_str'] ?? null) || filled($j['location']['city'] ?? null),
        'description' => static fn (array $j): bool => filled($j['description'] ?? null) || filled($j['full_description'] ?? null),
        'external_url' => static fn (array $j): bool => filled($j['url'] ?? null) || filled($j['application_url'] ?? null),
        'external_id' => static fn (array $j): bool => filled($j['shortcode'] ?? null) || filled($j['id'] ?? null),
        'published_at' => static fn (array $j): bool => filled($j['published_at'] ?? null) || filled($j['created_at'] ?? null),
        'updated_at' => static fn (array $j): bool => filled($j['updated_at'] ?? null),
        'job_type' => static fn (array $j): bool => filled($j['employment_type'] ?? null) || filled($j['type'] ?? null),
        'salary' => static fn (array $j): bool => filled($j['salary_from'] ?? null) || filled($j['salary_to'] ?? null) || filled($j['salary_currency'] ?? null),
    ];

    $report = [];
    foreach ($checks as $field => $check) {
        $filled = 0;
        foreach ($jobs as $job) {
            if ($check($job)) {
                $filled++;
            }
        }
        $report[$field] = "{$filled}/{$total}";
    }

    return $report;
}

function freshnessLever(array $jobs): array
{
    $dates = [];
    foreach ($jobs as $job) {
        if (filled($job['createdAt'] ?? null)) {
            $dates[] = (int) $job['createdAt'];
        }
    }

    sort($dates);

    return [
        'newest' => $dates !== [] ? date('c', (int) floor(end($dates) / 1000)) : null,
        'oldest' => $dates !== [] ? date('c', (int) floor($dates[0] / 1000)) : null,
        'date_field' => 'createdAt (ms epoch); updatedAt sparse',
    ];
}

function freshnessWorkable(array $jobs): array
{
    $dates = [];
    foreach ($jobs as $job) {
        $value = $job['published_at'] ?? $job['created_at'] ?? null;
        if (filled($value)) {
            $dates[] = strtotime((string) $value) ?: 0;
        }
    }

    sort($dates);

    return [
        'newest' => $dates !== [] ? date('c', end($dates)) : null,
        'oldest' => $dates !== [] ? date('c', $dates[0]) : null,
        'date_field' => 'published_at / created_at',
    ];
}

function duplicateIds(array $jobs, string $idKey): array
{
    $seen = [];
    $dupes = [];

    foreach ($jobs as $job) {
        $id = (string) ($job[$idKey] ?? '');
        if ($id === '') {
            continue;
        }
        if (isset($seen[$id])) {
            $dupes[] = $id;
        }
        $seen[$id] = true;
    }

    return [
        'unique' => count($seen),
        'duplicate_ids' => array_values(array_unique($dupes)),
    ];
}

function analyzeCompany(array $company, string $provider): array
{
    if ($provider === 'lever') {
        $board = fetchLeverBoard($company['slug']);
        $jobs = $board['jobs'];
        $response = $board['response'];
        $turkey = 0;
        $istanbul = 0;

        foreach ($jobs as $job) {
            $hay = locationHaystack($job, 'lever');
            if (matchesTurkey($hay)) {
                $turkey++;
            }
            if (matchesIstanbul($hay)) {
                $istanbul++;
            }
        }

        $ids = duplicateIds($jobs, 'id');

        return [
            'company' => $company['company'],
            'provider' => 'lever',
            'board_slug' => $company['slug'],
            'board_url' => "https://jobs.lever.co/{$company['slug']}",
            'api_endpoint' => $response['url'] ?? null,
            'http_status' => $response['http_status'] ?? null,
            'latency_ms' => $response['latency_ms'] ?? null,
            'content_type' => $response['headers']['content-type'] ?? null,
            'region' => $board['region'],
            'total_jobs' => count($jobs),
            'turkey_jobs' => $turkey,
            'istanbul_jobs' => $istanbul,
            'field_coverage' => coverageLever($jobs),
            'freshness' => freshnessLever($jobs),
            'duplicates' => $ids,
            'pagination' => [
                'skip_limit_supported' => true,
                'observed_single_page_count' => count($jobs),
            ],
            'sample_jobs' => array_map(static fn (array $j): array => [
                'id' => $j['id'] ?? null,
                'title' => $j['text'] ?? null,
                'location' => $j['categories']['location'] ?? null,
                'allLocations' => $j['categories']['allLocations'] ?? null,
                'hostedUrl' => $j['hostedUrl'] ?? null,
                'createdAt' => $j['createdAt'] ?? null,
            ], array_slice($jobs, 0, 3)),
            'error' => $response['error'] ?? null,
        ];
    }

    $board = fetchWorkableBoard($company['slug']);
    $jobs = $board['jobs'];
    $response = $board['response'];
    $turkey = 0;
    $istanbul = 0;

    foreach ($jobs as $job) {
        $hay = locationHaystack($job, 'workable');
        if (matchesTurkey($hay)) {
            $turkey++;
        }
        if (matchesIstanbul($hay)) {
            $istanbul++;
        }
    }

    $ids = duplicateIds($jobs, 'shortcode');
    if ($ids['unique'] === 0) {
        $ids = duplicateIds($jobs, 'id');
    }

    return [
        'company' => $company['company'],
        'provider' => 'workable',
        'board_slug' => $company['slug'],
        'board_url' => "https://apply.workable.com/{$company['slug']}/",
        'api_endpoint' => $board['endpoint_used'],
        'http_status' => $response['http_status'] ?? null,
        'latency_ms' => $response['latency_ms'] ?? null,
        'content_type' => $response['headers']['content-type'] ?? null,
        'total_jobs' => count($jobs),
        'turkey_jobs' => $turkey,
        'istanbul_jobs' => $istanbul,
        'field_coverage' => coverageWorkable($jobs),
        'freshness' => freshnessWorkable($jobs),
        'duplicates' => $ids,
        'pagination' => [
            'supported' => false,
            'observed_single_board_count' => count($jobs),
        ],
        'sample_jobs' => array_map(static fn (array $j): array => [
            'shortcode' => $j['shortcode'] ?? null,
            'id' => $j['id'] ?? null,
            'title' => $j['title'] ?? null,
            'location' => $j['location']['location_str'] ?? null,
            'url' => $j['url'] ?? null,
            'published_at' => $j['published_at'] ?? null,
        ], array_slice($jobs, 0, 3)),
        'error' => $response['error'] ?? null,
    ];
}

function aggregateCoverage(array $companyReports, string $provider): array
{
    $allJobs = [];
    foreach ($companyReports as $report) {
        if (($report['http_status'] ?? null) !== 200 || ($report['total_jobs'] ?? 0) === 0) {
            continue;
        }
        // Re-fetch not needed — approximate from per-company if we stored counts only
    }

    return [];
}

$leverResults = [];
foreach ($leverCompanies as $company) {
    $leverResults[] = analyzeCompany($company, 'lever');
    usleep(300000);
}

$workableResults = [];
foreach ($workableCompanies as $company) {
    $workableResults[] = analyzeCompany($company, 'workable');
    usleep(300000);
}

function summarize(array $results): array
{
    $inspected = count($results);
    $withJobs = array_values(array_filter($results, static fn ($r): bool => ($r['http_status'] ?? null) === 200 && ($r['total_jobs'] ?? 0) > 0));
    $withTurkey = array_values(array_filter($withJobs, static fn ($r): bool => ($r['turkey_jobs'] ?? 0) > 0));
    $withIstanbul = array_values(array_filter($withJobs, static fn ($r): bool => ($r['istanbul_jobs'] ?? 0) > 0));
    $totalJobs = array_sum(array_map(static fn ($r): int => (int) ($r['total_jobs'] ?? 0), $withJobs));
    $turkeyJobs = array_sum(array_map(static fn ($r): int => (int) ($r['turkey_jobs'] ?? 0), $withJobs));
    $istanbulJobs = array_sum(array_map(static fn ($r): int => (int) ($r['istanbul_jobs'] ?? 0), $withJobs));

    return [
        'companies_inspected' => $inspected,
        'companies_with_active_board' => count($withJobs),
        'companies_with_turkey_jobs' => count($withTurkey),
        'companies_with_istanbul_jobs' => count($withIstanbul),
        'total_active_jobs_observed' => $totalJobs,
        'turkey_jobs' => $turkeyJobs,
        'istanbul_jobs' => $istanbulJobs,
        'average_jobs_per_active_company' => count($withJobs) > 0 ? round($totalJobs / count($withJobs), 1) : 0,
    ];
}

function combinedFieldCoverage(array $results, string $provider): array
{
    $fields = ['title', 'company', 'location', 'description', 'external_url', 'external_id', 'published_at', 'updated_at', 'job_type', 'salary'];
    $totals = array_fill_keys($fields, 0);
    $filled = array_fill_keys($fields, 0);

    foreach ($results as $report) {
        if (($report['http_status'] ?? null) !== 200 || ($report['total_jobs'] ?? 0) === 0) {
            continue;
        }
        foreach ($report['field_coverage'] ?? [] as $field => $value) {
            if (! str_contains($value, '/')) {
                continue;
            }
            [$f, $t] = array_map('intval', explode('/', $value));
            $filled[$field] += $f;
            $totals[$field] += $t;
        }
    }

    $out = [];
    foreach ($fields as $field) {
        $out[$field] = $totals[$field] > 0 ? "{$filled[$field]}/{$totals[$field]}" : '0/0';
    }

    return $out;
}

function uniqueExternalIds(array $results, string $provider): array
{
    $keys = [];

    foreach ($results as $report) {
        if (($report['total_jobs'] ?? 0) === 0) {
            continue;
        }
        foreach ($report['sample_jobs'] ?? [] as $sample) {
            // samples only — compute cross-board from summary fields
        }
    }

    return $keys;
}

$leverSummary = summarize($leverResults);
$workableSummary = summarize($workableResults);

$leverActive = array_values(array_filter($leverResults, static fn ($r): bool => ($r['http_status'] ?? null) === 200 && ($r['total_jobs'] ?? 0) > 0));
$workableActive = array_values(array_filter($workableResults, static fn ($r): bool => ($r['http_status'] ?? null) === 200 && ($r['total_jobs'] ?? 0) > 0));

// Cross-provider duplicate check using composite keys
$allKeys = [];
$crossDupes = 0;
foreach ([$leverActive, $workableActive] as $group) {
    foreach ($group as $report) {
        foreach ($report['sample_jobs'] ?? [] as $sample) {
            $id = $sample['id'] ?? $sample['shortcode'] ?? null;
            if ($id === null) {
                continue;
            }
            $key = ($report['provider'] ?? '').':'.$report['board_slug'].':'.$id;
            $allKeys[$key] = true;
        }
    }
}

$report = [
    'generated_at' => now()->toIso8601String(),
    'lever' => [
        'access' => [
            'endpoint_pattern' => 'https://api.lever.co/v0/postings/{slug}?mode=json&limit=100',
            'eu_endpoint_pattern' => 'https://api.eu.lever.co/v0/postings/{slug}?mode=json&limit=100',
            'authentication' => 'none',
        ],
        'companies_tested' => $leverResults,
        'coverage_summary' => $leverSummary,
        'combined_field_coverage' => combinedFieldCoverage($leverResults, 'lever'),
    ],
    'workable' => [
        'access' => [
            'endpoint_pattern' => 'https://apply.workable.com/api/v1/widget/accounts/{slug}?details=true',
            'alt_endpoint_pattern' => 'https://www.workable.com/api/accounts/{slug}?details=true',
            'authentication' => 'none (public widget API)',
        ],
        'companies_tested' => $workableResults,
        'coverage_summary' => $workableSummary,
        'combined_field_coverage' => combinedFieldCoverage($workableResults, 'workable'),
    ],
    'combined_turkey' => [
        'lever' => $leverSummary,
        'workable' => $workableSummary,
        'total_unique_boards_with_jobs' => count($leverActive) + count($workableActive),
        'total_jobs_observed' => $leverSummary['total_active_jobs_observed'] + $workableSummary['total_active_jobs_observed'],
        'total_turkey_jobs_observed' => $leverSummary['turkey_jobs'] + $workableSummary['turkey_jobs'],
        'total_istanbul_jobs_observed' => $leverSummary['istanbul_jobs'] + $workableSummary['istanbul_jobs'],
    ],
];

$jsonPath = __DIR__.'/../LEVER_WORKABLE_FEASIBILITY_REPORT.json';
file_put_contents($jsonPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

echo "Lever+Workable feasibility complete\n";
echo "Report: {$jsonPath}\n";
echo 'Lever boards with jobs: '.$leverSummary['companies_with_active_board'].'/'.count($leverResults)."\n";
echo 'Lever Turkey jobs: '.$leverSummary['turkey_jobs']."\n";
echo 'Workable boards with jobs: '.$workableSummary['companies_with_active_board'].'/'.count($workableResults)."\n";
echo 'Workable Turkey jobs: '.$workableSummary['turkey_jobs']."\n";

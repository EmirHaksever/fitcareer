<?php

declare(strict_types=1);

/**
 * Workable Phase 1 — Turkey board discovery & feasibility (diagnostic only).
 * Endpoint: GET https://apply.workable.com/api/v1/widget/accounts/{slug}?details=true
 * NOT the authenticated Workable SPI v3 Developer API.
 * No DB writes. No production code changes.
 */

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Http;

const USER_AGENT = 'FitCareer-Workable-Phase1/1.0 (+diagnostic)';
const WIDGET_ENDPOINT = 'https://apply.workable.com/api/v1/widget/accounts/{slug}?details=true';
const MAX_POSTING_AGE_DAYS = 365;

/** @var list<array{company:string, slug:string, verified_url?:string, notes?:string}> */
$candidates = [
    // Görev 1 — previously tested
    ['company' => 'Wingie Enuygun', 'slug' => 'wingieenuygun', 'verified_url' => 'https://apply.workable.com/wingieenuygun/'],
    ['company' => 'Vertigo Games', 'slug' => 'vertigogames', 'verified_url' => 'https://apply.workable.com/vertigogames/'],
    ['company' => 'Sanction Scanner', 'slug' => 'sanction-scanner', 'verified_url' => 'https://apply.workable.com/sanction-scanner/'],
    // Alt / negative slugs from prior feasibility
    ['company' => 'Sanction Scanner (alt slug)', 'slug' => 'sanctionscanner', 'notes' => 'Prior feasibility alt'],
    ['company' => 'Enuygun (alt slug)', 'slug' => 'enuygun', 'notes' => 'Prior feasibility empty board'],
    ['company' => 'Wingie (alt slug)', 'slug' => 'wingie', 'notes' => 'Prior feasibility alt'],
    ['company' => 'Datasurgery / TRAICK AI', 'slug' => 'datasurgery', 'notes' => 'Prior feasibility 404'],
    ['company' => 'TRAICK AI (alt)', 'slug' => 'traick', 'notes' => 'Prior feasibility 404'],
    ['company' => 'TRAICK AI (alt 2)', 'slug' => 'traickai', 'notes' => 'Prior feasibility 404'],
    ['company' => 'Datasurgery AI (alt)', 'slug' => 'datasurgery-ai', 'notes' => 'Supplement probe'],
    // Web-verified apply.workable.com slugs (2026-08-12 search)
    ['company' => 'Lucida AI', 'slug' => 'lucida-ai', 'verified_url' => 'https://apply.workable.com/lucida-ai/'],
    ['company' => 'FERASET', 'slug' => 'feraset', 'verified_url' => 'https://apply.workable.com/feraset/'],
    ['company' => 'NewMind AI', 'slug' => 'newmindai', 'verified_url' => 'https://apply.workable.com/newmindai/'],
    ['company' => 'VavaCars', 'slug' => 'vavacars', 'verified_url' => 'https://apply.workable.com/vavacars/'],
    ['company' => 'Volt Lines', 'slug' => 'voltlines', 'verified_url' => 'https://apply.workable.com/voltlines/'],
    ['company' => 'RateHawk', 'slug' => 'ratehawk', 'verified_url' => 'https://apply.workable.com/ratehawk/'],
    ['company' => 'D-ploy', 'slug' => 'd-ploy', 'verified_url' => 'https://apply.workable.com/d-ploy/'],
    ['company' => 'Intellect', 'slug' => 'intellecthq', 'verified_url' => 'https://apply.workable.com/intellecthq/'],
    ['company' => 'Teachers In Turkey', 'slug' => 'teachers-in-turkey', 'verified_url' => 'https://apply.workable.com/teachers-in-turkey/'],
    ['company' => 'Mindrift', 'slug' => 'mindrift', 'verified_url' => 'https://jobs.workable.com/company/r7muFeAbcksMFWASJu45jA/jobs-at-mindrift'],
    // Additional probes (HTTP decides)
    ['company' => 'Teltonika', 'slug' => 'teltonika'],
    ['company' => 'Teltonika Networks', 'slug' => 'teltonika-networks'],
    ['company' => '2P PR & Digital', 'slug' => '2p-pr'],
    ['company' => 'Intuition Machines', 'slug' => 'intuition-machines'],
    ['company' => 'Figopara', 'slug' => 'figopara'],
    ['company' => 'Getir', 'slug' => 'getir'],
    ['company' => 'Hepsiburada', 'slug' => 'hepsiburada'],
    ['company' => 'Peak Games', 'slug' => 'peakgames'],
    ['company' => 'Insider One', 'slug' => 'insiderone'],
    ['company' => 'Commencis', 'slug' => 'commencis'],
    ['company' => 'Intellect (alt slug)', 'slug' => 'intellect'],
    ['company' => 'Nomagic', 'slug' => 'nomagic'],
];

function fetchWorkableBoard(string $slug): array
{
    $url = str_replace('{slug}', $slug, WIDGET_ENDPOINT);
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

    $body = (string) $response->body();
    $json = json_decode($body, true);

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
        'body_bytes' => strlen($body),
        'valid_json' => is_array($json),
    ];
}

function extractJobs(array $response): array
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

function locationBlob(array $job): string
{
    return mb_strtolower(json_encode([
        'country' => $job['country'] ?? null,
        'city' => $job['city'] ?? null,
        'state' => $job['state'] ?? null,
        'region' => $job['region'] ?? null,
        'locations' => $job['locations'] ?? null,
        'location' => $job['location'] ?? null,
    ], JSON_UNESCAPED_UNICODE));
}

function analyzeLocationCounts(array $jobs): array
{
    $counts = [
        'turkey_keyword' => 0,
        'turkiye_keyword' => 0,
        'istanbul_ascii' => 0,
        'istanbul_dotted' => 0,
        'ankara' => 0,
        'izmir' => 0,
        'other_turkey_cities' => 0,
        'turkey_or_istanbul_combined' => 0,
        'istanbul_only_not_turkey_keyword' => 0,
    ];

    $turkeyCityPatterns = ['ankara', 'izmir', 'antalya', 'bursa', 'adana', 'konya', 'gaziantep', 'kocaeli', 'mersin'];

    foreach ($jobs as $job) {
        $blob = locationBlob($job);
        $hasTurkey = str_contains($blob, 'turkey') || str_contains($blob, 'türkiye') || str_contains($blob, 'turkiye');
        $hasIstanbul = str_contains($blob, 'istanbul') || str_contains($blob, 'i̇stanbul');

        if (str_contains($blob, 'turkey')) {
            $counts['turkey_keyword']++;
        }
        if (str_contains($blob, 'türkiye') || str_contains($blob, 'turkiye')) {
            $counts['turkiye_keyword']++;
        }
        if (str_contains($blob, 'istanbul')) {
            $counts['istanbul_ascii']++;
        }
        if (str_contains($blob, 'i̇stanbul')) {
            $counts['istanbul_dotted']++;
        }
        if (str_contains($blob, 'ankara')) {
            $counts['ankara']++;
        }
        if (str_contains($blob, 'izmir')) {
            $counts['izmir']++;
        }

        $otherCity = false;
        foreach ($turkeyCityPatterns as $city) {
            if ($city !== 'ankara' && $city !== 'izmir' && str_contains($blob, $city)) {
                $otherCity = true;
                break;
            }
        }
        if ($otherCity) {
            $counts['other_turkey_cities']++;
        }

        if ($hasTurkey || $hasIstanbul) {
            $counts['turkey_or_istanbul_combined']++;
        }
        if ($hasIstanbul && ! $hasTurkey) {
            $counts['istanbul_only_not_turkey_keyword']++;
        }
    }

    return $counts;
}

function parsePublishedDate(array $job): ?int
{
    foreach (['published_on', 'created_at', 'updated_at'] as $field) {
        $value = $job[$field] ?? null;
        if (! is_string($value) || trim($value) === '') {
            continue;
        }
        $ts = strtotime($value);
        if ($ts !== false) {
            return $ts;
        }
    }

    return null;
}

function fieldCoverage(array $jobs): array
{
    $total = count($jobs);
    if ($total === 0) {
        return [];
    }

    $checks = [
        'title' => static fn (array $j): bool => filled($j['title'] ?? null),
        'company' => static fn (array $j): bool => filled($j['company'] ?? null) || filled($j['company_name'] ?? null),
        'location' => static fn (array $j): bool => filled($j['country'] ?? null) || filled($j['city'] ?? null) || filled($j['state'] ?? null) || filled($j['locations'] ?? null),
        'description' => static fn (array $j): bool => filled($j['description'] ?? null),
        'external_id_shortcode' => static fn (array $j): bool => filled($j['shortcode'] ?? null),
        'external_url' => static fn (array $j): bool => filled($j['url'] ?? null) || filled($j['application_url'] ?? null),
        'published_at' => static fn (array $j): bool => filled($j['published_on'] ?? null) || filled($j['created_at'] ?? null),
        'employment_type' => static fn (array $j): bool => filled($j['employment_type'] ?? null),
        'work_type' => static fn (array $j): bool => filled($j['workplace_type'] ?? null) || filled($j['remote'] ?? null) || filled($j['telecommuting'] ?? null),
        'salary' => static fn (array $j): bool => filled($j['salary'] ?? null) || filled($j['salary_from'] ?? null) || filled($j['salary_to'] ?? null),
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

function duplicateShortcodes(array $jobs): array
{
    $seen = [];
    $dupes = [];
    foreach ($jobs as $job) {
        $code = (string) ($job['shortcode'] ?? '');
        if ($code === '') {
            continue;
        }
        if (isset($seen[$code])) {
            $dupes[] = $code;
        }
        $seen[$code] = true;
    }

    return array_values(array_unique($dupes));
}

function categorizeBoard(array $analysis): string
{
    if (($analysis['http_status'] ?? 0) !== 200 || ! ($analysis['valid_json'] ?? false)) {
        return 'C';
    }
    if (($analysis['total_jobs'] ?? 0) === 0) {
        return 'C';
    }
    if (($analysis['location']['turkey_or_istanbul_combined'] ?? 0) === 0) {
        return 'C';
    }
    if (($analysis['fresh_jobs'] ?? 0) === 0) {
        return 'C';
    }

    $trIst = $analysis['location']['turkey_or_istanbul_combined'] ?? 0;
    $fresh = $analysis['fresh_jobs'] ?? 0;
    $total = $analysis['total_jobs'] ?? 0;

    if ($trIst >= 3 && $fresh >= 3 && $total >= 3) {
        return 'A';
    }

    return 'B';
}

$boards = [];
$sampleJobShape = null;
$sampleAccountShape = null;
$allJobsForCrossDupe = [];

foreach ($candidates as $candidate) {
    $response = fetchWorkableBoard($candidate['slug']);
    $jobs = extractJobs($response);
    $location = analyzeLocationCounts($jobs);
    $fresh = 0;
    $stale = 0;
    $newestTs = null;
    $oldestTs = null;

    foreach ($jobs as $job) {
        $ts = parsePublishedDate($job);
        if ($ts === null) {
            continue;
        }
        $newestTs = $newestTs === null ? $ts : max($newestTs, $ts);
        $oldestTs = $oldestTs === null ? $ts : min($oldestTs, $ts);
        $ageDays = (int) floor((time() - $ts) / 86400);
        if ($ageDays > MAX_POSTING_AGE_DAYS) {
            $stale++;
        } else {
            $fresh++;
        }
    }

    if ($sampleJobShape === null && isset($jobs[0])) {
        $sampleJobShape = [
            'top_level_keys' => array_keys($jobs[0]),
            'sample' => $jobs[0],
        ];
    }

    if ($sampleAccountShape === null && is_array($response['json'] ?? null)) {
        $accountKeys = array_keys($response['json']);
        $sampleAccountShape = [
            'top_level_keys' => $accountKeys,
            'account_name' => $response['json']['name'] ?? null,
            'description_present' => filled($response['json']['description'] ?? null),
        ];
    }

    foreach ($jobs as $job) {
        $allJobsForCrossDupe[] = [
            'slug' => $candidate['slug'],
            'company' => $candidate['company'],
            'shortcode' => $job['shortcode'] ?? null,
            'url' => $job['url'] ?? $job['application_url'] ?? null,
            'title' => $job['title'] ?? null,
            'company_field' => $job['company'] ?? $job['company_name'] ?? null,
        ];
    }

    $boards[] = [
        'company' => $candidate['company'],
        'slug' => $candidate['slug'],
        'verified_url' => $candidate['verified_url'] ?? null,
        'notes' => $candidate['notes'] ?? null,
        'endpoint' => str_replace('{slug}', $candidate['slug'], WIDGET_ENDPOINT),
        'http_status' => $response['http_status'],
        'latency_ms' => $response['latency_ms'] ?? null,
        'valid_json' => $response['valid_json'] ?? false,
        'error' => $response['error'],
        'response_headers' => $response['headers'] ?? [],
        'total_jobs' => count($jobs),
        'location' => $location,
        'turkey_jobs' => $location['turkey_or_istanbul_combined'],
        'istanbul_jobs' => max($location['istanbul_ascii'], $location['istanbul_dotted']),
        'fresh_jobs' => $fresh,
        'stale_jobs' => $stale,
        'newest_published' => $newestTs !== null ? date('c', $newestTs) : null,
        'oldest_published' => $oldestTs !== null ? date('c', $oldestTs) : null,
        'field_coverage' => fieldCoverage($jobs),
        'duplicate_shortcodes_within_board' => duplicateShortcodes($jobs),
        'category' => '',
    ];

    usleep(150000);
}

foreach ($boards as &$board) {
    $board['category'] = categorizeBoard($board);
}
unset($board);

// Cross-board duplicate analysis
$shortcodeMap = [];
$urlMap = [];
$titleCompanyMap = [];
foreach ($allJobsForCrossDupe as $row) {
    if (filled($row['shortcode'])) {
        $shortcodeMap[$row['shortcode']][] = $row['slug'];
    }
    if (filled($row['url'])) {
        $urlMap[$row['url']][] = $row['slug'];
    }
    $tcKey = mb_strtolower(trim((string) $row['title']).'|'.trim((string) $row['company_field']));
    if ($tcKey !== '|') {
        $titleCompanyMap[$tcKey][] = $row['slug'];
    }
}

$crossShortcode = [];
foreach ($shortcodeMap as $code => $slugs) {
    if (count(array_unique($slugs)) > 1) {
        $crossShortcode[] = ['shortcode' => $code, 'slugs' => array_values(array_unique($slugs))];
    }
}
$crossUrl = [];
foreach ($urlMap as $url => $slugs) {
    if (count(array_unique($slugs)) > 1) {
        $crossUrl[] = ['url' => $url, 'slugs' => array_values(array_unique($slugs))];
    }
}
$crossTitleCompany = [];
foreach ($titleCompanyMap as $key => $slugs) {
    if (count(array_unique($slugs)) > 1) {
        $crossTitleCompany[] = ['key' => $key, 'slugs' => array_values(array_unique($slugs))];
    }
}

$validBoards = array_values(array_filter($boards, static fn ($b): bool => ($b['http_status'] ?? 0) === 200 && ($b['total_jobs'] ?? 0) > 0));
$categoryA = array_values(array_filter($boards, static fn ($b): bool => $b['category'] === 'A'));
$categoryB = array_values(array_filter($boards, static fn ($b): bool => $b['category'] === 'B'));
$categoryC = array_values(array_filter($boards, static fn ($b): bool => $b['category'] === 'C'));

$report = [
    'generated_at' => date('c'),
    'endpoint_used' => WIDGET_ENDPOINT,
    'developer_api_note' => 'This probe uses the public widget API, NOT the authenticated Workable SPI v3 API at workable.readme.io (requires bearer token).',
    'max_posting_age_days' => MAX_POSTING_AGE_DAYS,
    'istanbul_counting_note' => 'istanbul_jobs uses Istanbul/İstanbul string match in raw location JSON. turkey_jobs uses combined Turkey/Türkiye keyword OR Istanbul match — Istanbul-only listings are counted in turkey_jobs even when country string omits Turkey.',
    'sample_account_shape' => $sampleAccountShape,
    'sample_job_shape' => $sampleJobShape,
    'boards' => $boards,
    'summary' => [
        'total_candidates_probed' => count($boards),
        'valid_boards_with_jobs' => count($validBoards),
        'category_a' => count($categoryA),
        'category_b' => count($categoryB),
        'category_c' => count($categoryC),
        'http_404' => count(array_filter($boards, static fn ($b): bool => ($b['http_status'] ?? 0) === 404)),
        'total_jobs_all_valid_boards' => array_sum(array_column($validBoards, 'total_jobs')),
        'total_turkey_or_istanbul_jobs' => array_sum(array_column($validBoards, 'turkey_jobs')),
        'total_istanbul_jobs' => array_sum(array_column($validBoards, 'istanbul_jobs')),
        'total_fresh_jobs' => array_sum(array_column($validBoards, 'fresh_jobs')),
        'total_stale_jobs' => array_sum(array_column($validBoards, 'stale_jobs')),
    ],
    'duplicate_findings' => [
        'within_board' => array_values(array_filter($boards, static fn ($b): bool => ($b['duplicate_shortcodes_within_board'] ?? []) !== [])),
        'cross_board_shortcode' => $crossShortcode,
        'cross_board_url' => array_slice($crossUrl, 0, 20),
        'cross_board_title_company' => array_slice($crossTitleCompany, 0, 20),
    ],
    'operational_risk' => [
        'documented_publicly' => false,
        'authentication_required' => false,
        'observed_rate_limit_headers' => false,
        'endpoint_stability' => 'informal — widely used by careers widgets but not listed in official Developer API docs',
        'cors_relevance' => 'browser CORS may restrict frontend fetch; server-side Laravel Http client unaffected in prior probes',
        'terms_reference' => 'https://www.workable.com/terms — governs platform/customer use; third-party aggregation not explicitly addressed',
    ],
];

$jsonPath = base_path('WORKABLE_PHASE1_DISCOVERY.json');
file_put_contents($jsonPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));

echo "Wrote {$jsonPath}\n";
echo 'Probed: '.count($boards).' | Valid: '.count($validBoards)." | A: {$report['summary']['category_a']} | B: {$report['summary']['category_b']} | C: {$report['summary']['category_c']}\n";
foreach ($categoryA as $row) {
    echo "A  {$row['slug']} jobs={$row['total_jobs']} TR/IST={$row['turkey_jobs']} fresh={$row['fresh_jobs']}\n";
}

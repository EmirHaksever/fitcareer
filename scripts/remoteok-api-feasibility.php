<?php

declare(strict_types=1);

/**
 * RemoteOK API feasibility probe — diagnostic only.
 * Does NOT write to DB or touch production ingestion pipeline.
 */

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Http;

const SAMPLE_SIZE = 20;
const USER_AGENT = 'FitCareer-RemoteOK-Feasibility/1.0 (+diagnostic; contact=dev@fitcareer.local)';

/**
 * @return array<string, mixed>
 */
function remoteokFetch(string $url, string $label): array
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
            'label' => $label,
            'url' => $url,
            'http_status' => null,
            'error' => $exception->getMessage(),
            'latency_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'jobs' => [],
        ];
    }

    $body = (string) $response->body();
    $json = json_decode($body, true);
    $headers = $response->headers();
    $items = is_array($json) ? $json : [];
    $legalNotice = null;
    $jobs = [];

    if ($items !== [] && isset($items[0]) && is_array($items[0]) && array_key_exists('legal', $items[0])) {
        $legalNotice = $items[0]['legal'] ?? null;
        $jobs = array_values(array_filter(
            array_slice($items, 1),
            static fn ($item): bool => is_array($item) && (isset($item['id']) || isset($item['slug']) || isset($item['position']))
        ));
    } elseif (is_array($json)) {
        $jobs = array_values(array_filter($json, static fn ($item): bool => is_array($item)));
    }

    return [
        'label' => $label,
        'url' => $url,
        'http_status' => $response->status(),
        'latency_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        'headers' => [
            'content-type' => $headers['content-type'][0] ?? null,
            'retry-after' => $headers['retry-after'][0] ?? null,
            'x-ratelimit-limit' => $headers['x-ratelimit-limit'][0] ?? null,
            'x-ratelimit-remaining' => $headers['x-ratelimit-remaining'][0] ?? null,
        ],
        'legal_notice' => $legalNotice,
        'returned_count' => count($jobs),
        'jobs' => $jobs,
        'body_bytes' => strlen($body),
    ];
}

/**
 * @param  list<array<string, mixed>>  $jobs
 * @return array<string, string>
 */
function coverageReport(array $jobs, int $sampleSize): array
{
    $sample = array_slice($jobs, 0, $sampleSize);
    $total = count($sample);

    $checks = [
        'title' => static fn (array $job): bool => filled($job['position'] ?? null),
        'company' => static fn (array $job): bool => filled($job['company'] ?? null),
        'location' => static fn (array $job): bool => filled($job['location'] ?? null),
        'description' => static fn (array $job): bool => filled($job['description'] ?? null),
        'external_url' => static fn (array $job): bool => filled($job['url'] ?? null) || filled($job['apply_url'] ?? null),
        'external_id' => static fn (array $job): bool => filled($job['id'] ?? null),
        'published_at' => static fn (array $job): bool => filled($job['date'] ?? null) || filled($job['epoch'] ?? null),
        'updated_at' => static fn (array $job): bool => filled($job['updated_at'] ?? null) || filled($job['updated'] ?? null),
        'job_type' => static fn (array $job): bool => filled($job['job_type'] ?? null) || filled($job['employment_type'] ?? null),
        'salary' => static fn (array $job): bool => (
            ((int) ($job['salary_min'] ?? 0)) > 0
            || ((int) ($job['salary_max'] ?? 0)) > 0
            || filled($job['salary'] ?? null)
        ),
    ];

    $report = [];
    foreach ($checks as $field => $check) {
        $filled = 0;
        foreach ($sample as $job) {
            if ($check($job)) {
                $filled++;
            }
        }
        $report[$field] = "{$filled}/{$total}";
    }

    return $report;
}

/**
 * @param  list<array<string, mixed>>  $jobs
 * @return array<string, mixed>
 */
function duplicateReport(array $jobs): array
{
    $ids = [];
    $duplicates = [];

    foreach ($jobs as $job) {
        $id = (string) ($job['id'] ?? '');
        if ($id === '') {
            continue;
        }
        if (isset($ids[$id])) {
            $duplicates[] = $id;
        }
        $ids[$id] = true;
    }

    return [
        'unique_ids' => count($ids),
        'duplicate_ids' => array_values(array_unique($duplicates)),
        'duplicate_count' => count(array_unique($duplicates)),
    ];
}

/**
 * @param  list<array<string, mixed>>  $jobs
 * @return array<string, mixed>
 */
function freshnessReport(array $jobs, int $sampleSize): array
{
    $sample = array_slice($jobs, 0, $sampleSize);
    $dates = [];

    foreach ($sample as $job) {
        if (filled($job['date'] ?? null)) {
            $dates[] = (string) $job['date'];
        } elseif (filled($job['epoch'] ?? null)) {
            $dates[] = date('c', (int) $job['epoch']);
        }
    }

    sort($dates);

    return [
        'newest' => $dates !== [] ? end($dates) : null,
        'oldest' => $dates !== [] ? $dates[0] : null,
        'dated_records_in_sample' => count($dates),
    ];
}

/**
 * @param  list<array<string, mixed>>  $jobs
 * @return array<string, mixed>
 */
function locationReport(array $jobs): array
{
    $scopes = [
        'Worldwide' => ['worldwide', 'anywhere', 'global', '🌏', 'world'],
        'Europe' => ['europe', 'eu', 'emea', 'uk', 'germany', 'france', 'spain', 'netherlands'],
        'Middle East' => ['middle east', 'mena', 'dubai', 'uae', 'saudi', 'qatar', 'israel'],
        'Turkey' => ['turkey', 'türkiye', 'turkiye', ' tr', 'tr,', ',tr'],
        'Istanbul' => ['istanbul', 'İstanbul'],
    ];

    $results = [];
    foreach ($scopes as $scope => $needles) {
        $matches = [];
        foreach ($jobs as $job) {
            $location = mb_strtolower((string) ($job['location'] ?? ''));
            $tags = mb_strtolower(implode(' ', array_map('strval', $job['tags'] ?? [])));
            $description = mb_strtolower(strip_tags((string) ($job['description'] ?? '')));
            $haystack = "{$location} {$tags} {$description}";

            foreach ($needles as $needle) {
                if ($needle === ' tr' || $needle === 'tr,' || $needle === ',tr') {
                    if (preg_match('/\btr\b/i', $location) || str_contains($haystack, 'turkey') || str_contains($haystack, 'türkiye') || str_contains($haystack, 'turkiye')) {
                        $matches[] = [
                            'id' => $job['id'] ?? null,
                            'title' => $job['position'] ?? null,
                            'company' => $job['company'] ?? null,
                            'location' => $job['location'] ?? null,
                        ];
                        break;
                    }
                    continue;
                }

                if (str_contains($haystack, mb_strtolower($needle))) {
                    $matches[] = [
                        'id' => $job['id'] ?? null,
                        'title' => $job['position'] ?? null,
                        'company' => $job['company'] ?? null,
                        'location' => $job['location'] ?? null,
                    ];
                    break;
                }
            }
        }

        $results[$scope] = [
            'count' => count($matches),
            'samples' => array_slice($matches, 0, 5),
        ];
    }

    return $results;
}

/**
 * @param  list<array<string, mixed>>  $jobs
 * @return list<array<string, mixed>>
 */
function sampleRecords(array $jobs, int $sampleSize): array
{
    $sample = array_slice($jobs, 0, $sampleSize);

    return array_map(static function (array $job): array {
        return [
            'id' => $job['id'] ?? null,
            'slug' => $job['slug'] ?? null,
            'title' => $job['position'] ?? null,
            'company' => $job['company'] ?? null,
            'location' => $job['location'] ?? null,
            'description_chars' => isset($job['description']) ? mb_strlen(strip_tags((string) $job['description'])) : 0,
            'external_url' => $job['url'] ?? null,
            'apply_url' => $job['apply_url'] ?? null,
            'published_at' => $job['date'] ?? null,
            'epoch' => $job['epoch'] ?? null,
            'updated_at' => $job['updated_at'] ?? $job['updated'] ?? null,
            'job_type' => $job['job_type'] ?? $job['employment_type'] ?? null,
            'tags' => $job['tags'] ?? null,
            'salary_min' => $job['salary_min'] ?? null,
            'salary_max' => $job['salary_max'] ?? null,
        ];
    }, $sample);
}

/**
 * @return array<string, mixed>
 */
function remoteokFetchWithoutUa(string $url): array
{
    $startedAt = microtime(true);

    try {
        $response = Http::timeout(30)->connectTimeout(10)->get($url);
    } catch (Throwable $exception) {
        return [
            'label' => 'no_user_agent',
            'http_status' => null,
            'error' => $exception->getMessage(),
            'latency_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ];
    }

    $json = json_decode((string) $response->body(), true);
    $count = is_array($json) ? max(0, count($json) - 1) : 0;

    return [
        'label' => 'no_user_agent',
        'http_status' => $response->status(),
        'latency_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        'returned_count' => $count,
    ];
}

$primary = remoteokFetch('https://remoteok.com/api', 'primary_feed');
$noUserAgent = remoteokFetchWithoutUa('https://remoteok.com/api');
$tagDev = remoteokFetch('https://remoteok.com/api?tag=dev', 'tag_dev');
$tagPython = remoteokFetch('https://remoteok.com/api?tags=dev,python', 'tags_dev_python');

$jobs = $primary['jobs'] ?? [];
$sample = sampleRecords($jobs, SAMPLE_SIZE);
$coverage = coverageReport($jobs, SAMPLE_SIZE);
$dupes = duplicateReport($jobs);
$freshness = freshnessReport($jobs, SAMPLE_SIZE);
$locations = locationReport($jobs);

$locationValues = [];
foreach (array_slice($jobs, 0, 100) as $job) {
    $value = trim((string) ($job['location'] ?? ''));
    if ($value !== '') {
        $locationValues[$value] = ($locationValues[$value] ?? 0) + 1;
    }
}
arsort($locationValues);

$report = [
    'generated_at' => now()->toIso8601String(),
    'access' => [
        'endpoint' => 'https://remoteok.com/api',
        'http' => $primary['http_status'] ?? null,
        'authentication' => 'none (public feed)',
        'content_type' => $primary['headers']['content-type'] ?? null,
        'latency_ms' => $primary['latency_ms'] ?? null,
        'legal_notice' => $primary['legal_notice'] ?? null,
        'no_user_agent_probe' => $noUserAgent,
        'rate_limit_headers' => [
            'retry-after' => $primary['headers']['retry-after'] ?? null,
            'x-ratelimit-limit' => $primary['headers']['x-ratelimit-limit'] ?? null,
            'x-ratelimit-remaining' => $primary['headers']['x-ratelimit-remaining'] ?? null,
        ],
    ],
    'dataset' => [
        'returned' => $primary['returned_count'] ?? 0,
        'sample_inspected' => min(SAMPLE_SIZE, count($jobs)),
        'total_count_if_available' => null,
        'body_bytes' => $primary['body_bytes'] ?? null,
        'sample_records' => $sample,
    ],
    'field_coverage' => $coverage,
    'freshness' => $freshness,
    'pagination' => [
        'primary_returned' => $primary['returned_count'] ?? 0,
        'tag_dev_returned' => $tagDev['returned_count'] ?? 0,
        'tags_dev_python_returned' => $tagPython['returned_count'] ?? 0,
        'notes' => 'No page/limit/offset params observed; feed appears to be a single snapshot.',
    ],
    'duplicates' => $dupes,
    'location' => $locations,
    'location_value_frequency_top_20' => array_slice($locationValues, 0, 20, true),
    'attribution' => [
        'official_help_url' => 'https://remoteok.featurebase.app/help/articles/3140840-is-there-an-api-or-rssjson-feed-of-remote-jobs',
        'legal_notice_from_feed' => $primary['legal_notice'] ?? null,
    ],
];

$jsonPath = __DIR__.'/../REMOTEOK_FEASIBILITY_REPORT.json';
file_put_contents($jsonPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

echo "RemoteOK feasibility probe complete.\n";
echo "Report: {$jsonPath}\n";
echo "Returned jobs: ".($report['dataset']['returned'])."\n";
echo "Sample inspected: ".($report['dataset']['sample_inspected'])."\n";
echo "HTTP: ".($report['access']['http'])."\n";
echo "Latency ms: ".($report['access']['latency_ms'])."\n";

foreach ($coverage as $field => $value) {
    echo strtoupper($field).": {$value}\n";
}

<?php

declare(strict_types=1);

/**
 * Jooble API feasibility probe — diagnostic only.
 * Does NOT write to DB or touch production ingestion pipeline.
 */

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Http;

$apiKey = trim((string) (env('JOOBLE_API_KEY') ?: getenv('JOOBLE_API_KEY') ?: ''));

if ($apiKey === '') {
    fwrite(STDERR, "JOOBLE_API_KEY is missing. Add it to .env and save the file, then rerun.\n");
    exit(2);
}

/**
 * @param  array<string, mixed>  $payload
 * @return array<string, mixed>
 */
function joobleSearch(string $apiKey, array $payload, string $label): array
{
    $url = 'https://jooble.org/api/'.$apiKey;
    $startedAt = microtime(true);

    try {
        $response = Http::timeout(30)
            ->connectTimeout(10)
            ->withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'User-Agent' => (string) config('scraper.user_agent'),
            ])
            ->post($url, $payload);
    } catch (Throwable $exception) {
        return [
            'label' => $label,
            'payload' => $payload,
            'http_status' => null,
            'error' => $exception->getMessage(),
            'latency_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ];
    }

    $body = (string) $response->body();
    $json = json_decode($body, true);
    $headers = $response->headers();

    return [
        'label' => $label,
        'payload' => $payload,
        'http_status' => $response->status(),
        'latency_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        'headers' => [
            'retry-after' => $headers['retry-after'][0] ?? null,
            'x-ratelimit-limit' => $headers['x-ratelimit-limit'][0] ?? null,
            'x-ratelimit-remaining' => $headers['x-ratelimit-remaining'][0] ?? null,
            'content-type' => $headers['content-type'][0] ?? null,
        ],
        'total_count' => is_array($json) ? ($json['totalCount'] ?? null) : null,
        'jobs_count' => is_array($json) && isset($json['jobs']) && is_array($json['jobs']) ? count($json['jobs']) : 0,
        'jobs' => is_array($json) && isset($json['jobs']) && is_array($json['jobs']) ? $json['jobs'] : [],
        'raw_error' => is_array($json) ? ($json['error'] ?? $json['message'] ?? null) : null,
        'body_snippet' => mb_substr(trim($body), 0, 300),
    ];
}

/**
 * @param  list<array<string, mixed>>  $jobs
 * @return array<string, mixed>
 */
function fieldCoverage(array $jobs): array
{
    $fields = ['title', 'company', 'location', 'salary', 'type', 'id', 'link', 'snippet'];

    $coverage = [];
    foreach ($fields as $field) {
        $filled = 0;
        foreach ($jobs as $job) {
            $value = $job[$field] ?? null;
            if (is_string($value) && trim($value) !== '') {
                $filled++;
            } elseif ($value !== null && $value !== '') {
                $filled++;
            }
        }
        $coverage[$field] = [
            'filled' => $filled,
            'total' => count($jobs),
            'pct' => count($jobs) > 0 ? round(($filled / count($jobs)) * 100, 1) : 0,
        ];
    }

    return $coverage;
}

/**
 * @param  list<array<string, mixed>>  $jobs
 */
function locationRelevance(array $jobs, string $scope): array
{
    $turkeyHints = ['türkiye', 'turkey', 'istanbul', 'ankara', 'izmir', 'bursa', 'antalya', 'tr.jooble', 'jooble.org/tr'];
    $istanbulHints = ['istanbul', 'İstanbul', 'Istanbul'];

    $relevant = 0;
    $samples = [];

    foreach ($jobs as $job) {
        $location = mb_strtolower((string) ($job['location'] ?? ''));
        $link = mb_strtolower((string) ($job['link'] ?? ''));

        $isTurkey = false;
        foreach ($turkeyHints as $hint) {
            if (str_contains($location, mb_strtolower($hint)) || str_contains($link, mb_strtolower($hint))) {
                $isTurkey = true;
                break;
            }
        }

        $isIstanbul = false;
        foreach ($istanbulHints as $hint) {
            if (str_contains($location, mb_strtolower($hint)) || str_contains($link, mb_strtolower($hint))) {
                $isIstanbul = true;
                break;
            }
        }

        if ($scope === 'turkey' && $isTurkey) {
            $relevant++;
        }
        if ($scope === 'istanbul' && $isIstanbul) {
            $relevant++;
        }

        if (count($samples) < 3) {
            $samples[] = [
                'location' => $job['location'] ?? null,
                'link' => $job['link'] ?? null,
                'is_turkey' => $isTurkey,
                'is_istanbul' => $isIstanbul,
            ];
        }
    }

    return [
        'scope' => $scope,
        'relevant' => $relevant,
        'total' => count($jobs),
        'samples' => $samples,
    ];
}

/**
 * @param  array<string, mixed>  $sample
 * @return list<array<string, mixed>>
 */
function sampleListings(array $sample, int $limit = 10): array
{
    $rows = [];
    foreach (array_slice($sample['jobs'] ?? [], 0, $limit) as $index => $job) {
        $rows[] = [
            'index' => $index + 1,
            'title' => $job['title'] ?? null,
            'company' => $job['company'] ?? null,
            'location' => $job['location'] ?? null,
            'salary' => $job['salary'] ?? null,
            'type' => $job['type'] ?? null,
            'id' => $job['id'] ?? null,
            'link' => $job['link'] ?? null,
            'snippet' => isset($job['snippet']) ? mb_substr((string) $job['snippet'], 0, 120) : null,
        ];
    }

    return $rows;
}

$istanbul = joobleSearch($apiKey, [
    'keywords' => 'yazılım',
    'location' => 'Istanbul',
    'page' => '1',
    'ResultOnPage' => '20',
], 'istanbul_yazilim_page1');

$turkey = joobleSearch($apiKey, [
    'keywords' => 'yazılım',
    'location' => 'Turkey',
    'page' => '1',
    'ResultOnPage' => '20',
], 'turkey_yazilim_page1');

$turkeyAlt = joobleSearch($apiKey, [
    'keywords' => 'yazılım',
    'location' => 'Türkiye',
    'page' => '1',
    'ResultOnPage' => '20',
], 'turkiye_yazilim_page1');

$istanbulPage2 = joobleSearch($apiKey, [
    'keywords' => 'yazılım',
    'location' => 'Istanbul',
    'page' => '2',
    'ResultOnPage' => '20',
], 'istanbul_yazilim_page2');

$invalidKey = joobleSearch('invalid-key-for-error-probe', [
    'keywords' => 'test',
    'location' => 'Istanbul',
    'page' => '1',
], 'invalid_key_probe');

$report = [
    'generated_at' => now()->toIso8601String(),
    'endpoint' => 'POST https://jooble.org/api/{JOOBLE_API_KEY}',
    'queries' => [
        'istanbul' => $istanbul,
        'turkey' => $turkey,
        'turkey_alt_turkiye' => $turkeyAlt,
        'istanbul_page2' => $istanbulPage2,
        'invalid_key' => $invalidKey,
    ],
    'field_coverage' => [
        'istanbul' => fieldCoverage($istanbul['jobs'] ?? []),
        'turkey' => fieldCoverage($turkey['jobs'] ?? []),
    ],
    'location_relevance' => [
        'istanbul' => locationRelevance($istanbul['jobs'] ?? [], 'istanbul'),
        'turkey' => locationRelevance($turkey['jobs'] ?? [], 'turkey'),
    ],
    'sample_listings' => sampleListings($istanbul),
    'pagination' => [
        'page1_count' => $istanbul['jobs_count'] ?? 0,
        'page2_count' => $istanbulPage2['jobs_count'] ?? 0,
        'page1_total_count' => $istanbul['total_count'] ?? null,
        'page2_total_count' => $istanbulPage2['total_count'] ?? null,
        'page1_ids' => array_map(static fn (array $j): mixed => $j['id'] ?? null, array_slice($istanbul['jobs'] ?? [], 0, 5)),
        'page2_ids' => array_map(static fn (array $j): mixed => $j['id'] ?? null, array_slice($istanbulPage2['jobs'] ?? [], 0, 5)),
        'overlap_first5' => count(array_intersect(
            array_map(static fn (array $j): mixed => $j['id'] ?? null, $istanbul['jobs'] ?? []),
            array_map(static fn (array $j): mixed => $j['id'] ?? null, $istanbulPage2['jobs'] ?? []),
        )),
    ],
];

$outJson = base_path('JOOBLE_FEASIBILITY_REPORT.json');
file_put_contents($outJson, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

echo "Report written to {$outJson}\n\n";

function printSection(string $title, array $data): void
{
    echo "=== {$title} ===\n";
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n\n";
}

printSection('ISTANBUL QUERY', [
    'http_status' => $istanbul['http_status'],
    'total_count' => $istanbul['total_count'],
    'jobs_count' => $istanbul['jobs_count'],
    'latency_ms' => $istanbul['latency_ms'],
]);
printSection('TURKEY QUERY', [
    'http_status' => $turkey['http_status'],
    'total_count' => $turkey['total_count'],
    'jobs_count' => $turkey['jobs_count'],
    'latency_ms' => $turkey['latency_ms'],
]);
printSection('SAMPLE LISTINGS (first 10, Istanbul)', sampleListings($istanbul));
printSection('PAGINATION', $report['pagination']);
printSection('INVALID KEY PROBE', [
    'http_status' => $invalidKey['http_status'],
    'raw_error' => $invalidKey['raw_error'],
    'body_snippet' => $invalidKey['body_snippet'],
]);

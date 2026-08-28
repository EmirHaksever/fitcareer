<?php

declare(strict_types=1);

/**
 * Lever Phase 2 — board validation & Turkey discovery probe.
 * Diagnostic only: reads Lever API, writes JSON report. No DB writes.
 */

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Http;

const USER_AGENT = 'FitCareer-Lever-Phase2/1.0 (+discovery)';
const MAX_POSTING_AGE_DAYS = 365;
const PAGE_SIZE = 100;
const MAX_PAGES = 5;

/** @var list<array{company:string, slug:string, verified_url?:string, notes?:string}> */
$candidates = [
    // Already in production
    ['company' => 'Commencis', 'slug' => 'commencis', 'verified_url' => 'https://jobs.lever.co/commencis'],
    ['company' => 'Midas', 'slug' => 'getmidas', 'verified_url' => 'https://jobs.lever.co/getmidas'],
    // Phase 1 planned boards
    ['company' => 'Insider One', 'slug' => 'insiderone', 'verified_url' => 'https://jobs.lever.co/insiderone'],
    ['company' => 'Trendyol', 'slug' => 'trendyol', 'verified_url' => 'https://jobs.lever.co/trendyol'],
    ['company' => 'Dream Games', 'slug' => 'dreamgames', 'verified_url' => 'https://jobs.lever.co/dreamgames'],
    // Web-verified Turkey boards
    ['company' => 'Codeway', 'slug' => 'codeway', 'verified_url' => 'https://jobs.lever.co/codeway'],
    ['company' => 'Ajax Systems', 'slug' => 'ajax', 'verified_url' => 'https://jobs.lever.co/ajax'],
    ['company' => 'Peak Games', 'slug' => 'peakgames', 'verified_url' => 'https://jobs.lever.co/peakgames'],
    ['company' => 'Grand Games', 'slug' => 'grand', 'verified_url' => 'https://jobs.lever.co/grand'],
    ['company' => 'Papara', 'slug' => 'papara', 'verified_url' => 'https://jobs.lever.co/papara'],
    ['company' => 'iyzico', 'slug' => 'iyzico', 'verified_url' => 'https://jobs.lever.co/iyzico'],
    ['company' => 'Binance', 'slug' => 'binance', 'verified_url' => 'https://jobs.lever.co/binance'],
    ['company' => 'Capital.com', 'slug' => 'capital', 'verified_url' => 'https://jobs.lever.co/capital'],
    ['company' => 'Insider (legacy slug)', 'slug' => 'useinsider', 'verified_url' => 'https://jobs.lever.co/useinsider', 'notes' => 'Legacy Insider board; may overlap insiderone'],
    // Wrong slugs / negative controls (expect 404)
    ['company' => 'Grand Games (wrong slug)', 'slug' => 'grandgames', 'notes' => 'Feasibility wrong slug'],
    ['company' => 'Ajax Systems (wrong slug)', 'slug' => 'ajaxsystems', 'notes' => 'Feasibility wrong slug'],
    ['company' => 'Midas (wrong slug)', 'slug' => 'midas', 'notes' => 'Feasibility wrong slug'],
    ['company' => 'Firefly', 'slug' => 'firefly', 'notes' => 'Feasibility candidate'],
    ['company' => 'Firefly Space', 'slug' => 'fireflyspace', 'notes' => 'Supplement probe'],
    // Non-Turkey Lever boards (control)
    ['company' => 'Jam City', 'slug' => 'jamcity', 'verified_url' => 'https://jobs.lever.co/jamcity', 'notes' => 'US/global gaming — expect C'],
    ['company' => 'Nomagic', 'slug' => 'nomagic', 'verified_url' => 'https://jobs.lever.co/nomagic', 'notes' => 'Poland robotics — expect C'],
    // Additional probes without prior URL verification (HTTP will decide)
    ['company' => 'Figopara', 'slug' => 'figopara'],
    ['company' => 'Invictus', 'slug' => 'invictus'],
    ['company' => 'Invictus Games', 'slug' => 'invictusgames'],
    ['company' => 'Ozan', 'slug' => 'ozan'],
    ['company' => 'Kolay Gelsin', 'slug' => 'kolaygelsin'],
    ['company' => 'Hepsiburada', 'slug' => 'hepsiburada'],
    ['company' => 'Getir', 'slug' => 'getir'],
    ['company' => 'Logo Yazılım', 'slug' => 'logo'],
    ['company' => 'Teknasyon', 'slug' => 'teknasyon'],
    ['company' => 'Rollic', 'slug' => 'rollic'],
    ['company' => 'MagicLab', 'slug' => 'magiclab'],
];

function locationHaystack(array $job): string
{
    $categories = is_array($job['categories'] ?? null) ? $job['categories'] : [];
    $parts = [
        $categories['location'] ?? '',
        implode(' ', (array) ($categories['allLocations'] ?? [])),
        $job['country'] ?? '',
        $job['workplaceType'] ?? '',
    ];

    return mb_strtolower(implode(' ', array_map('strval', $parts)));
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

function fetchLeverBoardPaginated(string $slug): array
{
    $bases = [
        'global' => "https://api.lever.co/v0/postings/{$slug}",
        'eu' => "https://api.eu.lever.co/v0/postings/{$slug}",
    ];

    $resolvedBase = null;
    $resolvedRegion = null;
    $allJobs = [];
    $httpStatus = null;
    $validJson = false;

    for ($page = 0; $page < MAX_PAGES; $page++) {
        $skip = $page * PAGE_SIZE;
        $pageJobs = null;

        foreach ($bases as $region => $baseUrl) {
            if ($page > 0 && $resolvedBase !== null && $baseUrl !== $resolvedBase) {
                continue;
            }

            try {
                $response = Http::timeout(45)
                    ->connectTimeout(15)
                    ->withHeaders(['Accept' => 'application/json', 'User-Agent' => USER_AGENT])
                    ->get($baseUrl, ['mode' => 'json', 'skip' => $skip, 'limit' => PAGE_SIZE]);
            } catch (Throwable $e) {
                continue;
            }

            $httpStatus = $response->status();
            $json = $response->json();

            if ($httpStatus === 200 && is_array($json) && array_is_list($json)) {
                $validJson = true;
                $resolvedBase = $baseUrl;
                $resolvedRegion = $region;
                $pageJobs = array_values(array_filter($json, static fn ($item): bool => is_array($item) && isset($item['id'], $item['text'])));
                break;
            }

            if ($page === 0 && $httpStatus === 404) {
                continue;
            }
        }

        if ($pageJobs === null) {
            break;
        }

        if ($pageJobs === []) {
            break;
        }

        $allJobs = array_merge($allJobs, $pageJobs);

        if (count($pageJobs) < PAGE_SIZE) {
            break;
        }
    }

    return [
        'http_status' => $httpStatus,
        'valid_json' => $validJson,
        'region' => $resolvedRegion,
        'jobs' => $allJobs,
        'pagination_required' => count($allJobs) >= PAGE_SIZE,
    ];
}

function analyzeBoard(array $candidate, array $fetch): array
{
    $jobs = $fetch['jobs'];
    $total = count($jobs);
    $turkey = 0;
    $istanbul = 0;
    $stale = 0;
    $fresh = 0;
    $newestMs = null;
    $oldestMs = null;
    $fieldCoverage = [];

    foreach ($jobs as $job) {
        $haystack = locationHaystack($job);
        if (matchesTurkey($haystack)) {
            $turkey++;
        }
        if (matchesIstanbul($haystack)) {
            $istanbul++;
        }

        $createdAt = is_numeric($job['createdAt'] ?? null) ? (int) $job['createdAt'] : null;
        if ($createdAt !== null) {
            $newestMs = $newestMs === null ? $createdAt : max($newestMs, $createdAt);
            $oldestMs = $oldestMs === null ? $createdAt : min($oldestMs, $createdAt);
            $ageDays = (int) floor((time() * 1000 - $createdAt) / 86400000);
            if ($ageDays > MAX_POSTING_AGE_DAYS) {
                $stale++;
            } else {
                $fresh++;
            }
        }
    }

    if ($total > 0) {
        $checks = [
            'title' => static fn (array $j): bool => filled($j['text'] ?? null),
            'location' => static fn (array $j): bool => filled($j['categories']['location'] ?? null) || filled($j['categories']['allLocations'] ?? null),
            'description' => static fn (array $j): bool => filled($j['descriptionPlain'] ?? null) || filled($j['description'] ?? null),
            'external_url' => static fn (array $j): bool => filled($j['hostedUrl'] ?? null),
            'external_id' => static fn (array $j): bool => filled($j['id'] ?? null),
            'published_at' => static fn (array $j): bool => filled($j['createdAt'] ?? null),
        ];
        foreach ($checks as $field => $check) {
            $filled = 0;
            foreach ($jobs as $job) {
                if ($check($job)) {
                    $filled++;
                }
            }
            $fieldCoverage[$field] = "{$filled}/{$total}";
        }
    }

    $http = $fetch['http_status'];
    $active = $http === 200 && $fetch['valid_json'] && $total > 0;

    $category = 'C';
    if ($http !== 200 || ! $fetch['valid_json']) {
        $category = 'C';
    } elseif ($turkey === 0 && $istanbul === 0) {
        $category = 'C';
    } elseif ($fresh === 0 && $total > 0) {
        $category = 'C';
    } elseif ($turkey >= 1 && $fresh >= 1 && $active) {
        $category = 'A';
    } elseif ($turkey >= 1 || $istanbul >= 1) {
        $category = 'B';
    }

    return [
        'company' => $candidate['company'],
        'slug' => $candidate['slug'],
        'verified_url' => $candidate['verified_url'] ?? null,
        'notes' => $candidate['notes'] ?? null,
        'http_status' => $http,
        'valid_json' => $fetch['valid_json'],
        'region' => $fetch['region'],
        'total_jobs' => $total,
        'turkey_jobs' => $turkey,
        'istanbul_jobs' => $istanbul,
        'fresh_jobs' => $fresh,
        'stale_jobs' => $stale,
        'newest_created_at' => $newestMs !== null ? date('c', (int) floor($newestMs / 1000)) : null,
        'oldest_created_at' => $oldestMs !== null ? date('c', (int) floor($oldestMs / 1000)) : null,
        'field_coverage' => $fieldCoverage,
        'pagination_required' => $fetch['pagination_required'],
        'active' => $active,
        'category' => $category,
    ];
}

$results = [];
foreach ($candidates as $candidate) {
    $fetch = fetchLeverBoardPaginated($candidate['slug']);
    $results[] = analyzeBoard($candidate, $fetch);
    usleep(200000);
}

$report = [
    'generated_at' => date('c'),
    'max_posting_age_days' => MAX_POSTING_AGE_DAYS,
    'boards' => $results,
    'summary' => [
        'total_probed' => count($results),
        'category_a' => count(array_filter($results, static fn ($r): bool => $r['category'] === 'A')),
        'category_b' => count(array_filter($results, static fn ($r): bool => $r['category'] === 'B')),
        'category_c' => count(array_filter($results, static fn ($r): bool => $r['category'] === 'C')),
        'http_404' => count(array_filter($results, static fn ($r): bool => ($r['http_status'] ?? 0) === 404)),
    ],
];

$jsonPath = base_path('LEVER_PHASE2_DISCOVERY.json');
file_put_contents($jsonPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo "Wrote {$jsonPath}\n";
echo "Probed: {$report['summary']['total_probed']} | A: {$report['summary']['category_a']} | B: {$report['summary']['category_b']} | C: {$report['summary']['category_c']} | 404: {$report['summary']['http_404']}\n";

foreach ($results as $row) {
    if ($row['category'] === 'A') {
        echo "A  {$row['slug']} jobs={$row['total_jobs']} TR={$row['turkey_jobs']} IST={$row['istanbul_jobs']} fresh={$row['fresh_jobs']}\n";
    }
}

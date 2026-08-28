<?php

declare(strict_types=1);

/**
 * Phase E read-only Turkey career platform discovery probe.
 * No DB writes, imports, or application mutations.
 */

require __DIR__.'/ats-coverage-discovery-helpers.php';
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

const PHASE_E_USER_AGENT = 'FitCareer-PhaseE-Discovery/1.0 (+read-only-audit)';
const PHASE_E_DELAY_US = 200_000;

function classifyJobBlob(string $blob): string
{
    return classifyLocation($blob);
}

/** @return array<string,mixed> */
function probeTeamtailor(string $slug): array
{
    $url = "https://{$slug}.teamtailor.com/jobs.json";
    $resp = httpGetJson($url);
    $json = $resp['json'] ?? null;
    if (! is_array($json)) {
        return [
            'platform' => 'teamtailor',
            'slug' => $slug,
            'endpoint' => $url,
            'http_status' => $resp['http_status'],
            'accessible' => false,
            'error' => $resp['error'] ?? 'invalid_json',
        ];
    }

    $jobs = $json['items'] ?? $json['jobs'] ?? $json;
    if (! is_array($jobs)) {
        return [
            'platform' => 'teamtailor',
            'slug' => $slug,
            'endpoint' => $url,
            'http_status' => $resp['http_status'],
            'accessible' => false,
            'error' => 'missing_jobs_array',
        ];
    }

    $jobList = array_values(array_filter($jobs, 'is_array'));
    $total = count($jobList);
    $tr = 0;
    $ist = 0;
    $samples = [];

    foreach ($jobList as $job) {
        if (! is_array($job)) {
            continue;
        }
        $blob = locationBlobFromParts([
            $job['location'] ?? null,
            $job['locations'] ?? null,
            $job['country'] ?? null,
            $job['city'] ?? null,
            $job['remoteStatus'] ?? null,
            $job['content'] ?? null,
            $job['summary'] ?? null,
            $job['title'] ?? null,
        ]);
        $class = classifyJobBlob($blob);
        $job['_location_class'] = $class;
        if (in_array($class, ['turkey', 'istanbul', 'remote_turkey'], true)) {
            $tr++;
            if ($class === 'istanbul') {
                $ist++;
            }
        }
        if (count($samples) < 2) {
            $samples[] = [
                'id' => $job['id'] ?? null,
                'title' => $job['title'] ?? null,
                'location' => $job['location'] ?? null,
                'url' => $job['url'] ?? ($job['links']['careersite-job-url'] ?? null),
            ];
        }
    }

    return [
        'platform' => 'teamtailor',
        'slug' => $slug,
        'endpoint' => $url,
        'http_status' => $resp['http_status'],
        'accessible' => ($resp['http_status'] ?? 0) === 200,
        'total_jobs' => $total,
        'turkey_jobs' => $tr,
        'istanbul_jobs' => $ist,
        'sample_jobs' => $samples,
        'schema_keys' => $total > 0 ? array_keys($jobList[0]) : [],
    ];
}

/** @return array<string,mixed> */
function probeRecruitee(string $slug): array
{
    $url = "https://{$slug}.recruitee.com/api/offers";
    $resp = httpGetJson($url);
    $json = $resp['json'] ?? null;
    if (! is_array($json)) {
        return [
            'platform' => 'recruitee',
            'slug' => $slug,
            'endpoint' => $url,
            'http_status' => $resp['http_status'],
            'accessible' => false,
            'error' => $resp['error'] ?? 'invalid_json',
        ];
    }

    $offers = $json['offers'] ?? $json;
    if (! is_array($offers)) {
        return [
            'platform' => 'recruitee',
            'slug' => $slug,
            'endpoint' => $url,
            'http_status' => $resp['http_status'],
            'accessible' => false,
            'error' => 'missing_offers_array',
        ];
    }

    $offerList = array_values(array_filter($offers, 'is_array'));
    $total = count($offerList);
    $tr = 0;
    $ist = 0;
    $samples = [];

    foreach ($offerList as $offer) {
        if (! is_array($offer)) {
            continue;
        }
        $blob = locationBlobFromParts([
            $offer['country'] ?? null,
            $offer['country_code'] ?? null,
            $offer['city'] ?? null,
            $offer['location'] ?? null,
            $offer['locations'] ?? null,
            $offer['remote'] ?? null,
            $offer['title'] ?? null,
        ]);
        $class = classifyJobBlob($blob);
        if (in_array($class, ['turkey', 'istanbul', 'remote_turkey'], true)) {
            $tr++;
            if ($class === 'istanbul') {
                $ist++;
            }
        }
        if (count($samples) < 2) {
            $samples[] = [
                'id' => $offer['id'] ?? null,
                'slug' => $offer['slug'] ?? null,
                'title' => $offer['title'] ?? null,
                'country' => $offer['country'] ?? null,
                'city' => $offer['city'] ?? null,
            ];
        }
    }

    return [
        'platform' => 'recruitee',
        'slug' => $slug,
        'endpoint' => $url,
        'http_status' => $resp['http_status'],
        'accessible' => ($resp['http_status'] ?? 0) === 200,
        'total_jobs' => $total,
        'turkey_jobs' => $tr,
        'istanbul_jobs' => $ist,
        'sample_jobs' => $samples,
        'schema_keys' => $total > 0 ? array_keys($offerList[0]) : [],
    ];
}

/** @return array<string,mixed> */
function probeSmartRecruiters(string $companyId): array
{
    $url = 'https://api.smartrecruiters.com/v1/companies/'.rawurlencode($companyId).'/postings';
    $resp = httpGetJson($url, ['limit' => 100]);
    $json = $resp['json'] ?? null;
    if (! is_array($json)) {
        return [
            'platform' => 'smartrecruiters',
            'slug' => $companyId,
            'endpoint' => $url,
            'http_status' => $resp['http_status'],
            'accessible' => false,
            'error' => $resp['error'] ?? 'invalid_json',
        ];
    }

    $content = $json['content'] ?? [];
    $total = (int) ($json['totalFound'] ?? count($content));
    $tr = 0;
    $ist = 0;

    foreach ($content as $posting) {
        if (! is_array($posting)) {
            continue;
        }
        $loc = $posting['location'] ?? [];
        $blob = locationBlobFromParts([
            is_array($loc) ? ($loc['country'] ?? null) : null,
            is_array($loc) ? ($loc['city'] ?? null) : null,
            is_array($loc) ? ($loc['region'] ?? null) : null,
            $posting['name'] ?? null,
        ]);
        $class = classifyJobBlob($blob);
        if (in_array($class, ['turkey', 'istanbul', 'remote_turkey'], true)) {
            $tr++;
            if ($class === 'istanbul') {
                $ist++;
            }
        }
    }

    return [
        'platform' => 'smartrecruiters',
        'slug' => $companyId,
        'endpoint' => $url,
        'http_status' => $resp['http_status'],
        'accessible' => ($resp['http_status'] ?? 0) === 200,
        'total_jobs' => $total,
        'turkey_jobs' => $tr,
        'istanbul_jobs' => $ist,
    ];
}

/** @return array<string,mixed> */
function probeLever(string $slug): array
{
    $url = "https://api.lever.co/v0/postings/{$slug}?mode=json&limit=200";
    $resp = httpGetJson($url);
    $json = $resp['json'] ?? null;
    if (! is_array($json)) {
        return [
            'platform' => 'lever',
            'slug' => $slug,
            'endpoint' => $url,
            'http_status' => $resp['http_status'],
            'accessible' => false,
            'error' => $resp['error'] ?? 'invalid_json',
        ];
    }

    $total = count($json);
    $tr = 0;
    $ist = 0;
    foreach ($json as $job) {
        if (! is_array($job)) {
            continue;
        }
        $blob = locationBlobFromParts([
            $job['categories']['location'] ?? null,
            $job['categories']['allLocations'] ?? null,
            $job['workplaceType'] ?? null,
        ]);
        $class = classifyJobBlob($blob);
        if (in_array($class, ['turkey', 'istanbul', 'remote_turkey'], true)) {
            $tr++;
            if ($class === 'istanbul') {
                $ist++;
            }
        }
    }

    return [
        'platform' => 'lever',
        'slug' => $slug,
        'endpoint' => $url,
        'http_status' => $resp['http_status'],
        'accessible' => ($resp['http_status'] ?? 0) === 200 && $total > 0,
        'total_jobs' => $total,
        'turkey_jobs' => $tr,
        'istanbul_jobs' => $ist,
    ];
}

/** @return array<string,mixed> */
function probeWorkable(string $slug): array
{
    $url = "https://apply.workable.com/api/v1/widget/accounts/{$slug}?details=true";
    $resp = httpGetJson($url);
    $json = $resp['json'] ?? null;
    if (! is_array($json)) {
        return [
            'platform' => 'workable',
            'slug' => $slug,
            'endpoint' => $url,
            'http_status' => $resp['http_status'],
            'accessible' => false,
            'error' => $resp['error'] ?? 'invalid_json',
        ];
    }

    $jobs = $json['jobs'] ?? [];
    $jobList = array_values(array_filter($jobs, 'is_array'));
    $total = count($jobList);
    $tr = 0;
    $ist = 0;
    foreach ($jobList as $job) {
        if (! is_array($job)) {
            continue;
        }
        $blob = locationBlobFromParts([
            $job['country'] ?? null,
            $job['city'] ?? null,
            $job['location'] ?? null,
            $job['locations'] ?? null,
        ]);
        $class = classifyJobBlob($blob);
        if (in_array($class, ['turkey', 'istanbul', 'remote_turkey'], true)) {
            $tr++;
            if ($class === 'istanbul') {
                $ist++;
            }
        }
    }

    return [
        'platform' => 'workable',
        'slug' => $slug,
        'endpoint' => $url,
        'http_status' => $resp['http_status'],
        'accessible' => ($resp['http_status'] ?? 0) === 200 && $total > 0,
        'total_jobs' => $total,
        'turkey_jobs' => $tr,
        'istanbul_jobs' => $ist,
    ];
}

/** @return array<string,mixed> */
function probePersonio(string $slug): array
{
    $url = "https://{$slug}.jobs.personio.de/xml?language=en";
    $started = microtime(true);
    try {
        $response = Illuminate\Support\Facades\Http::timeout(25)
            ->withHeaders(['User-Agent' => PHASE_E_USER_AGENT])
            ->get($url);
        $body = (string) $response->body();
        $jobCount = preg_match_all('/<position>/i', $body) ?: 0;
        $tr = preg_match_all('/istanbul|türkiye|turkey|ankara|izmir/i', $body) ?: 0;

        return [
            'platform' => 'personio',
            'slug' => $slug,
            'endpoint' => $url,
            'http_status' => $response->status(),
            'accessible' => $response->successful() && $jobCount > 0,
            'total_jobs' => $jobCount,
            'turkey_keyword_hits' => $tr,
            'response_type' => 'xml',
        ];
    } catch (Throwable $e) {
        return [
            'platform' => 'personio',
            'slug' => $slug,
            'endpoint' => $url,
            'accessible' => false,
            'error' => $e->getMessage(),
        ];
    }
}

/** @return array<string,mixed> */
function probeComeet(string $slug): array
{
    $url = "https://www.comeet.com/jobs-api/2.0/company/{$slug}/positions";
    $resp = httpGetJson($url);
    $json = $resp['json'] ?? null;
    if (! is_array($json)) {
        return [
            'platform' => 'comeet',
            'slug' => $slug,
            'endpoint' => $url,
            'http_status' => $resp['http_status'],
            'accessible' => false,
            'error' => $resp['error'] ?? 'invalid_json',
        ];
    }

    $jobs = $json;
    $jobList = array_values(array_filter($jobs, 'is_array'));
    $total = count($jobList);
    $tr = 0;
    foreach ($jobList as $job) {
        if (! is_array($job)) {
            continue;
        }
        $blob = locationBlobFromParts([$job['location'] ?? null, $job['country'] ?? null, $job['city'] ?? null]);
        if (in_array(classifyJobBlob($blob), ['turkey', 'istanbul', 'remote_turkey'], true)) {
            $tr++;
        }
    }

    return [
        'platform' => 'comeet',
        'slug' => $slug,
        'endpoint' => $url,
        'http_status' => $resp['http_status'],
        'accessible' => ($resp['http_status'] ?? 0) === 200 && $total > 0,
        'total_jobs' => $total,
        'turkey_jobs' => $tr,
    ];
}

/** @return array<string,mixed> */
function probePinpoint(string $slug): array
{
    $url = "https://{$slug}.pinpointhq.com/postings.json";
    $resp = httpGetJson($url);
    $json = $resp['json'] ?? null;
    if (! is_array($json)) {
        return [
            'platform' => 'pinpoint',
            'slug' => $slug,
            'endpoint' => $url,
            'http_status' => $resp['http_status'],
            'accessible' => false,
            'error' => $resp['error'] ?? 'invalid_json',
        ];
    }

    $jobs = $json['data'] ?? $json;
    $total = is_array($jobs) ? count($jobs) : 0;
    $tr = 0;
    if (is_array($jobs)) {
        foreach ($jobList as $job) {
            if (! is_array($job)) {
                continue;
            }
            $blob = locationBlobFromParts([$job['location'] ?? null, $job['country'] ?? null]);
            if (in_array(classifyJobBlob($blob), ['turkey', 'istanbul', 'remote_turkey'], true)) {
                $tr++;
            }
        }
    }

    return [
        'platform' => 'pinpoint',
        'slug' => $slug,
        'endpoint' => $url,
        'http_status' => $resp['http_status'],
        'accessible' => ($resp['http_status'] ?? 0) === 200 && $total > 0,
        'total_jobs' => $total,
        'turkey_jobs' => $tr,
    ];
}

/** @param callable(): array<string,mixed> $probeFn */
function runProbe(callable $probeFn): array
{
    $result = $probeFn();
    usleep(PHASE_E_DELAY_US);

    return $result;
}

$teamtailorSlugs = [
    'dfdsturkey', 'teamblueticimax', 'teamblue', 'ticimax', 'volue', 'tradingview',
    'gofluent', 'peakgames', 'parasut', 'kolayik', 'logo', 'softtech', 'intertech',
    'craftgate', 'papara', 'getir', 'trendyol', 'hepsiburada', 'boyner', 'turkcell',
    'netas', 'protel', 'teknasyon', 'jotform', 'figopara', 'bitaksi', 'moka',
    'globaluniversitysystems', 'deliveryhero', 'revolut', 'insiderone', 'midas',
];

$recruiteeSlugs = [
    'iyzico', 'parasut', 'mikroyazilim', 'triomobil', 'krila', 'peakgames', 'figopara',
    'craftgate', 'papara', 'getir', 'trendyol', 'hepsiburada', 'turkcell', 'boyner',
    'netas', 'teknasyon', 'jotform', 'kolayik', 'logo', 'softtech', 'intertech',
    'bitaksi', 'moka', 'param', 'revolut', 'ticimax', 'dfds', 'volue', 'tradingview',
    'insider', 'midas', 'dreamgames', 'shopside', 'zirve', 'emukellef',
];

$smartRecruitersIds = [
    'DeliveryHero', 'Yemeksepeti', 'Getir', 'Papara', 'Hepsiburada', 'Trendyol',
    'Turkcell', 'Boyner', 'Arcelik', 'Logo', 'Softtech', 'Netas', 'Revolut',
    'Vodafone', 'VodafoneTurkey', 'Sovos', 'Param', 'Figopara', 'Ciceksepeti',
    'DFDS', 'Ticimax', 'Volue', 'TradingView',
];

$unseededLever = ['ajax', 'ciceksepeti', 'craftgate', 'papara', 'peakgames', 'rollic', 'nomagic', 'bitaksi', 'figopara', 'jotform'];
$unseededWorkable = ['figopara', 'jotform', 'craftgate', 'papara', 'peakgames', 'teltonika', 'ratehawk', 'intellecthq'];

$personioSlugs = ['peakgames', 'getir', 'trendyol', 'papara', 'figopara', 'craftgate', 'logo'];
$comeetSlugs = ['monday', 'wix', 'fiverr', 'gett', 'papaya', 'peak', 'getir', 'trendyol'];
$pinpointSlugs = ['peak', 'figopara', 'craftgate', 'papara', 'getir'];

$results = [
    'teamtailor' => [],
    'recruitee' => [],
    'smartrecruiters' => [],
    'lever_unseeded_gap' => [],
    'workable_unseeded_gap' => [],
    'personio' => [],
    'comeet' => [],
    'pinpoint' => [],
];

foreach ($teamtailorSlugs as $slug) {
    $results['teamtailor'][] = runProbe(fn () => probeTeamtailor($slug));
}
foreach ($recruiteeSlugs as $slug) {
    $results['recruitee'][] = runProbe(fn () => probeRecruitee($slug));
}
foreach ($smartRecruitersIds as $slug) {
    $results['smartrecruiters'][] = runProbe(fn () => probeSmartRecruiters($slug));
}
foreach ($unseededLever as $slug) {
    $results['lever_unseeded_gap'][] = runProbe(fn () => probeLever($slug));
}
foreach ($unseededWorkable as $slug) {
    $results['workable_unseeded_gap'][] = runProbe(fn () => probeWorkable($slug));
}
foreach ($personioSlugs as $slug) {
    $results['personio'][] = runProbe(fn () => probePersonio($slug));
}
foreach ($comeetSlugs as $slug) {
    $results['comeet'][] = runProbe(fn () => probeComeet($slug));
}
foreach ($pinpointSlugs as $slug) {
    $results['pinpoint'][] = runProbe(fn () => probePinpoint($slug));
}

function aggregatePlatform(array $probes): array
{
    $employers = [];
    $totalTr = 0;
    $totalIst = 0;
    $totalJobs = 0;

    foreach ($probes as $probe) {
        if (! ($probe['accessible'] ?? false)) {
            continue;
        }
        $tr = (int) ($probe['turkey_jobs'] ?? $probe['turkey_keyword_hits'] ?? 0);
        if ($tr <= 0 && (($probe['total_jobs'] ?? 0) <= 0)) {
            continue;
        }
        $employers[] = [
            'slug' => $probe['slug'],
            'total_jobs' => $probe['total_jobs'] ?? 0,
            'turkey_jobs' => $tr,
            'istanbul_jobs' => $probe['istanbul_jobs'] ?? 0,
            'endpoint' => $probe['endpoint'] ?? null,
        ];
        $totalTr += $tr;
        $totalIst += (int) ($probe['istanbul_jobs'] ?? 0);
        $totalJobs += (int) ($probe['total_jobs'] ?? 0);
    }

    return [
        'confirmed_employers' => count($employers),
        'employers' => $employers,
        'total_jobs' => $totalJobs,
        'total_turkey_jobs' => $totalTr,
        'total_istanbul_jobs' => $totalIst,
    ];
}

$output = [
    'probed_at' => now()->toIso8601String(),
    'phase' => 'E',
    'mode' => 'read_only',
    'raw_probes' => $results,
    'aggregates' => [
        'teamtailor' => aggregatePlatform($results['teamtailor']),
        'recruitee' => aggregatePlatform($results['recruitee']),
        'smartrecruiters' => aggregatePlatform($results['smartrecruiters']),
        'lever_unseeded_gap' => aggregatePlatform($results['lever_unseeded_gap']),
        'workable_unseeded_gap' => aggregatePlatform($results['workable_unseeded_gap']),
        'personio' => aggregatePlatform($results['personio']),
        'comeet' => aggregatePlatform($results['comeet']),
        'pinpoint' => aggregatePlatform($results['pinpoint']),
    ],
];

$path = __DIR__.'/../storage/phase-e-platform-probe-output.json';
file_put_contents($path, json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo json_encode($output['aggregates'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n";

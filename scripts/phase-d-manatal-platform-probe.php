<?php

declare(strict_types=1);

/**
 * Phase D read-only probe — Manatal careers-page.com platform discovery.
 * Does NOT touch DB, imports, or application services.
 */

const PHASE_D_USER_AGENT = 'FitCareer-PhaseD-Audit/1.0 (+read-only)';

function probeSlug(string $slug): array
{
    $url = 'https://api.careers-page.com/open/v1/career-pages/'.rawurlencode($slug).'/job-posts?size=50';

    try {
        $response = Illuminate\Support\Facades\Http::timeout(25)
            ->withHeaders(['User-Agent' => PHASE_D_USER_AGENT, 'Accept' => 'application/json'])
            ->get($url);

        $json = $response->json();
        if (! is_array($json) || ! isset($json['items'])) {
            return [
                'slug' => $slug,
                'http' => $response->status(),
                'found' => false,
                'error' => is_array($json) ? ($json['code'] ?? $json['detail'] ?? 'unknown') : 'invalid_json',
            ];
        }

        $items = $json['items'];
        $trLocated = 0;
        $istanbulLocated = 0;
        $emptyLocation = 0;
        $countries = [];

        foreach ($items as $item) {
            $loc = is_array($item['location'] ?? null) ? $item['location'] : [];
            $country = mb_strtolower(trim((string) ($loc['country'] ?? '')));
            $city = mb_strtolower(trim((string) ($loc['city'] ?? '')));

            if ($country === '' && $city === '') {
                $emptyLocation++;
            }

            if ($country !== '') {
                $countries[$country] = ($countries[$country] ?? 0) + 1;
            }

            if (
                str_contains($country, 'turk')
                || str_contains($country, 'türkiye')
                || str_contains($country, 'turkiye')
            ) {
                $trLocated++;
            }

            $cityNorm = str_replace(['İ', 'I'], 'i', $city);
            if (str_contains($cityNorm, 'istanbul')) {
                $istanbulLocated++;
            }
        }

        $titles = [];
        foreach (array_slice($items, 0, 3) as $item) {
            $titles[] = $item['translations'][0]['name'] ?? '?';
        }

        return [
            'slug' => $slug,
            'http' => $response->status(),
            'found' => true,
            'total' => $json['total'] ?? count($items),
            'tr_located' => $trLocated,
            'istanbul_located' => $istanbulLocated,
            'empty_location' => $emptyLocation,
            'countries' => $countries,
            'sample_titles' => $titles,
        ];
    } catch (Throwable $e) {
        return [
            'slug' => $slug,
            'http' => null,
            'found' => false,
            'error' => $e->getMessage(),
        ];
    }
}

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$slugs = [
    // Confirmed / candidate Turkish employers (subdomain + path slugs)
    'getir', 'mundi', 'medical-departures', 'david-kennedy-recruitment-2',
    'cb-talents', 'ceiba-healthcare', 'centrum-ai', 'implentio', 'mineo',
    'transparent-search-group', 'wizpresso', 'sync', 'av-hiring', 'fastn',
    'cynch-ai', 'peak', 'insider', 'migros', 'yemeksepeti', 'bitaksi',
    'param', 'boyner', 'softtech', 'intertech', 'logo', 'teknasyon',
    'craftgate', 'figopara', 'nomagic', 'rollic', 'jotform', 'ciceksepeti',
    'n11', 'papara', 'trendyol', 'hepsiburada', 'turkcell', 'deliveryhero',
    'amazon', 'google', 'microsoft', 'cross-border-talents', 'cb-talents-sp',
];

$results = [];
foreach ($slugs as $slug) {
    $results[] = probeSlug($slug);
    usleep(150_000);
}

$getirDetail = null;
try {
    $getirDetail = Illuminate\Support\Facades\Http::timeout(25)
        ->withHeaders(['User-Agent' => PHASE_D_USER_AGENT])
        ->get('https://api.careers-page.com/open/v1/career-pages/getir/job-posts?size=50')
        ->json();
} catch (Throwable) {
    $getirDetail = null;
}

$sampleJob = is_array($getirDetail) && isset($getirDetail['items'][0])
    ? $getirDetail['items'][0]
    : null;

$cbTalentsTurkey = null;
try {
    $cbTalentsTurkey = Illuminate\Support\Facades\Http::timeout(25)
        ->withHeaders(['User-Agent' => PHASE_D_USER_AGENT])
        ->get('https://api.careers-page.com/open/v1/career-pages/cb-talents/job-posts', [
            'country__icontains' => 'Turkey',
            'size' => 50,
        ])
        ->json();
} catch (Throwable) {
    $cbTalentsTurkey = null;
}

$output = [
    'probed_at' => now()->toIso8601String(),
    'platform' => 'Manatal careers-page.com',
    'api_base' => 'https://api.careers-page.com/open/v1/career-pages/{client_slug}/job-posts',
    'slug_probe' => $results,
    'cb_talents_turkey_filter' => is_array($cbTalentsTurkey) ? [
        'total' => $cbTalentsTurkey['total'] ?? null,
        'sample_count' => isset($cbTalentsTurkey['items']) ? count($cbTalentsTurkey['items']) : 0,
        'sample_titles' => array_slice(array_map(
            fn ($i) => $i['translations'][0]['name'] ?? '?',
            $cbTalentsTurkey['items'] ?? []
        ), 0, 5),
    ] : null,
    'getir_sample_job_fields' => $sampleJob ? array_keys($sampleJob) : [],
    'getir_sample_job' => $sampleJob,
];

$path = __DIR__.'/../storage/phase-d-manatal-slug-probe.json';
file_put_contents($path, json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n";

<?php

declare(strict_types=1);

/**
 * Jooble official docs format probe (matches jooble.org/api/about PHP sample).
 * Diagnostic only — no DB, no production pipeline changes.
 */

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$key = trim((string) (env('JOOBLE_API_KEY') ?: getenv('JOOBLE_API_KEY') ?: ''));
if ($key === '') {
    fwrite(STDERR, "JOOBLE_API_KEY missing\n");
    exit(2);
}

$url = 'https://jooble.org/api/'.$key;

/**
 * @return array<string, mixed>
 */
function officialPost(string $url, string $jsonBody): array
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonBody);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $started = microtime(true);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    $decoded = is_string($body) ? json_decode($body, true) : null;

    return [
        'http_status' => $status,
        'latency_ms' => (int) round((microtime(true) - $started) * 1000),
        'curl_error' => $error !== '' ? $error : null,
        'request_body' => $jsonBody,
        'total_count' => is_array($decoded) ? ($decoded['totalCount'] ?? null) : null,
        'jobs_count' => is_array($decoded) && is_array($decoded['jobs'] ?? null) ? count($decoded['jobs']) : 0,
        'first_job' => is_array($decoded) && isset($decoded['jobs'][0]) ? [
            'title' => $decoded['jobs'][0]['title'] ?? null,
            'location' => $decoded['jobs'][0]['location'] ?? null,
            'company' => $decoded['jobs'][0]['company'] ?? null,
            'id' => $decoded['jobs'][0]['id'] ?? null,
        ] : null,
        'body_snippet' => is_string($body) ? mb_substr($body, 0, 200) : null,
    ];
}

$cases = [
    'official_bern_it' => '{ "keywords": "it", "location": "Bern"}',
    'istanbul_it' => '{ "keywords": "it", "location": "Istanbul"}',
    'istanbul_yazilim' => '{ "keywords": "yazılım", "location": "Istanbul"}',
    'turkey_it' => '{ "keywords": "it", "location": "Turkey"}',
    'turkiye_it' => '{ "keywords": "it", "location": "Türkiye"}',
    'ankara_it' => '{ "keywords": "it", "location": "Ankara"}',
    'bern_developer' => '{ "keywords": "developer", "location": "Bern"}',
];

echo "Endpoint: POST {$url}\n";
echo "Format: official Jooble PHP sample (curl + raw JSON body)\n\n";

foreach ($cases as $label => $body) {
    $result = officialPost($url, $body);
    echo "=== {$label} ===\n";
    echo 'HTTP: '.$result['http_status']."\n";
    echo 'totalCount: '.($result['total_count'] ?? 'null')."\n";
    echo 'jobs: '.$result['jobs_count']."\n";
    if ($result['first_job']) {
        echo 'first: '.json_encode($result['first_job'], JSON_UNESCAPED_UNICODE)."\n";
    }
    echo "\n";
}

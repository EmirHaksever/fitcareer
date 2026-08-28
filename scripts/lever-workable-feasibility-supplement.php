<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Http;

$h = ['Accept' => 'application/json', 'User-Agent' => 'FitCareer-Probe/1.0'];

$w = Http::withHeaders($h)->get('https://apply.workable.com/api/v1/widget/accounts/wingieenuygun?details=true')->json();
$job = $w['jobs'][0] ?? [];

echo "WORKABLE SAMPLE JOB STRUCTURE\n";
echo json_encode([
    'keys' => array_keys($job),
    'location' => $job['location'] ?? null,
    'country' => $job['country'] ?? null,
    'city' => $job['city'] ?? null,
    'state' => $job['state'] ?? null,
    'remote' => $job['remote'] ?? null,
    'telecommuting' => $job['telecommuting'] ?? null,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n";

$page1 = Http::withHeaders($h)->get('https://api.lever.co/v0/postings/insiderone?mode=json&limit=100')->json();
$page2 = Http::withHeaders($h)->get('https://api.lever.co/v0/postings/insiderone?mode=json&skip=100&limit=100')->json();
echo 'insiderone page1='.(is_array($page1) ? count($page1) : 0)."\n";
echo 'insiderone page2 skip100='.(is_array($page2) ? count($page2) : 0)."\n";

$midas = Http::withHeaders($h)->get('https://api.lever.co/v0/postings/getmidas?mode=json&limit=100')->json();
$istanbul = 0;
foreach (is_array($midas) ? $midas : [] as $row) {
    $loc = json_encode($row['categories'] ?? [], JSON_UNESCAPED_UNICODE);
    if (stripos($loc, 'istanbul') !== false || stripos($loc, 'İstanbul') !== false) {
        $istanbul++;
    }
}
echo "midas istanbul explicit count={$istanbul}\n";

$extraLever = ['grand-games', 'ajax-systems', 'fireflyspace', 'grandgamesstudio'];
$extraWorkable = ['sanction-scanner', 'traick-ai', 'traickai', 'datasurgery-ai'];

foreach ($extraLever as $slug) {
    $r = Http::withHeaders($h)->get("https://api.lever.co/v0/postings/{$slug}?mode=json&limit=100");
    echo "lever {$slug} HTTP {$r->status()} count=".(is_array($r->json()) ? count($r->json()) : 0)."\n";
}

foreach ($extraWorkable as $slug) {
    $r = Http::withHeaders($h)->get("https://apply.workable.com/api/v1/widget/accounts/{$slug}?details=true");
    $jobs = is_array($r->json()) ? ($r->json()['jobs'] ?? []) : [];
    echo "workable {$slug} HTTP {$r->status()} jobs=".count($jobs)."\n";
}

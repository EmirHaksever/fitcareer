<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Http;

$h = ['Accept' => 'application/json', 'User-Agent' => 'FitCareer-Probe/1.0'];

function fetchWorkable(string $slug): array
{
    global $h;
    $json = Http::withHeaders($h)->get("https://apply.workable.com/api/v1/widget/accounts/{$slug}?details=true")->json();

    return is_array($json['jobs'] ?? null) ? $json['jobs'] : [];
}

function workableCoverage(array $jobs): array
{
    $total = count($jobs);
    $fields = [
        'title' => fn ($j) => filled($j['title'] ?? null),
        'company' => fn ($j) => true,
        'location' => fn ($j) => filled($j['country'] ?? null) || filled($j['city'] ?? null) || filled($j['state'] ?? null) || filled($j['locations'] ?? null),
        'description' => fn ($j) => filled($j['description'] ?? null),
        'external_url' => fn ($j) => filled($j['url'] ?? null) || filled($j['application_url'] ?? null),
        'external_id' => fn ($j) => filled($j['shortcode'] ?? null),
        'published_at' => fn ($j) => filled($j['published_on'] ?? null) || filled($j['created_at'] ?? null),
        'updated_at' => fn ($j) => filled($j['updated_at'] ?? null),
        'job_type' => fn ($j) => filled($j['employment_type'] ?? null),
        'salary' => fn ($j) => false,
    ];

    $out = [];
    foreach ($fields as $name => $check) {
        $n = 0;
        foreach ($jobs as $job) {
            if ($check($job)) {
                $n++;
            }
        }
        $out[$name] = "{$n}/{$total}";
    }

    return $out;
}

function locStats(array $jobs): array
{
    $turkey = 0;
    $istanbul = 0;
    foreach ($jobs as $job) {
        $hay = mb_strtolower(json_encode([
            $job['country'] ?? '',
            $job['city'] ?? '',
            $job['state'] ?? '',
            $job['locations'] ?? '',
        ], JSON_UNESCAPED_UNICODE));
        if (str_contains($hay, 'turkey') || str_contains($hay, 'türkiye') || str_contains($hay, 'turkiye')) {
            $turkey++;
        }
        if (str_contains($hay, 'istanbul') || str_contains($hay, 'i̇stanbul')) {
            $istanbul++;
        }
    }

    return compact('turkey', 'istanbul');
}

$boards = [
    'wingieenuygun' => 'Wingie Enuygun',
    'vertigogames' => 'Vertigo Games',
    'sanction-scanner' => 'Sanction Scanner',
];

$all = [];
foreach ($boards as $slug => $name) {
    $jobs = fetchWorkable($slug);
    $stats = locStats($jobs);
    $all[] = [
        'company' => $name,
        'slug' => $slug,
        'total' => count($jobs),
        'turkey' => $stats['turkey'],
        'istanbul' => $stats['istanbul'],
        'coverage' => workableCoverage($jobs),
    ];
}

$totalJobs = array_sum(array_column($all, 'total'));
$turkeyJobs = array_sum(array_column($all, 'turkey'));
$istanbulJobs = array_sum(array_column($all, 'istanbul'));

echo json_encode([
    'boards' => $all,
    'totals' => [
        'companies' => count($all),
        'jobs' => $totalJobs,
        'turkey' => $turkeyJobs,
        'istanbul' => $istanbulJobs,
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n";

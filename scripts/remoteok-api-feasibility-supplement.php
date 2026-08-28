<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Http;

$ua = ['User-Agent' => 'FitCareer-RemoteOK-Feasibility/1.0', 'Accept' => 'application/json'];

function jobs(?array $data): array
{
    if (! is_array($data)) {
        return [];
    }

    $items = isset($data[0]['legal']) ? array_slice($data, 1) : $data;

    return array_values(array_filter($items, static fn ($i): bool => is_array($i) && isset($i['id'])));
}

$resp = Http::withHeaders($ua)->get('https://remoteok.com/api');
$primary = jobs($resp->json());
$dev = jobs(Http::withHeaders($ua)->get('https://remoteok.com/api?tag=dev')->json());
$python = jobs(Http::withHeaders($ua)->get('https://remoteok.com/api?tags=dev,python')->json());

$pIds = array_column($primary, 'id');
$dIds = array_column($dev, 'id');
$dates = array_map(static fn ($j) => $j['date'] ?? null, $primary);
sort($dates);

$salary = 0;
$emptyLoc = 0;
$tagFullTime = 0;
$turkey = 0;
$istanbul = 0;
$worldwideTag = 0;
$nonTechTag = 0;

foreach ($primary as $job) {
    if ((int) ($job['salary_min'] ?? 0) > 0 || (int) ($job['salary_max'] ?? 0) > 0) {
        $salary++;
    }
    if (trim((string) ($job['location'] ?? '')) === '') {
        $emptyLoc++;
    }
    foreach ((array) ($job['tags'] ?? []) as $tag) {
        $tag = strtolower((string) $tag);
        if ($tag === 'worldwide') {
            $worldwideTag++;
        }
        if ($tag === 'non tech') {
            $nonTechTag++;
        }
        if (str_contains($tag, 'full time')) {
            $tagFullTime++;
            break;
        }
    }
    $haystack = strtolower(($job['location'] ?? '').' '.implode(' ', (array) ($job['tags'] ?? [])).' '.strip_tags((string) ($job['description'] ?? '')));
    if (str_contains($haystack, 'turkey') || str_contains($haystack, 'türkiye') || str_contains($haystack, 'turkiye')) {
        $turkey++;
    }
    if (str_contains($haystack, 'istanbul')) {
        $istanbul++;
    }
}

echo json_encode([
    'content_type' => $resp->header('Content-Type'),
    'primary_count' => count($primary),
    'dev_count' => count($dev),
    'python_count' => count($python),
    'primary_dev_same_set' => count(array_diff($pIds, $dIds)) === 0 && count($pIds) === count($dIds),
    'primary_dev_overlap' => count(array_intersect($pIds, $dIds)),
    'newest_all' => $dates !== [] ? end($dates) : null,
    'oldest_all' => $dates !== [] ? $dates[0] : null,
    'salary_gt0_in_100' => "{$salary}/100",
    'empty_location_in_100' => "{$emptyLoc}/100",
    'tags_full_time_in_100' => "{$tagFullTime}/100",
    'tags_worldwide_in_100' => "{$worldwideTag}/100",
    'tags_non_tech_in_100' => "{$nonTechTag}/100",
    'turkey_mentions_in_100' => $turkey,
    'istanbul_mentions_in_100' => $istanbul,
    'dev_first_ids' => array_slice($dIds, 0, 5),
    'primary_first_ids' => array_slice($pIds, 0, 5),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;

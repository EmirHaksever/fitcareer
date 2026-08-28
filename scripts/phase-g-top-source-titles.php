<?php

declare(strict_types=1);

require __DIR__.'/ats-coverage-discovery-helpers.php';
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Job;
use App\Models\JobSource;

$before = ['jobs' => Job::count(), 'sources' => JobSource::count()];

$targets = [
    'ticimax' => 'https://teamblueticimax.teamtailor.com/jobs.json',
    'dfds' => 'https://dfdsturkey.teamtailor.com/jobs.json',
    'getir' => 'https://api.careers-page.com/open/v1/career-pages/getir/job-posts?size=50',
    'macellan' => 'https://macellan.recruitee.com/api/offers',
    'ciceksepeti' => 'https://api.lever.co/v0/postings/ciceksepeti?mode=json',
    'makrops' => 'https://makrops.com/en/careers',
];

$out = [];
foreach ($targets as $key => $url) {
    $r = httpGetJson($url);
    $json = $r['json'];
    $titles = [];
    $locations = [];
    if (is_array($json)) {
        if (isset($json['items']) && is_array($json['items'])) {
            foreach ($json['items'] as $item) {
                $titles[] = $item['title'] ?? '';
                $locations[] = $item['location']['name'] ?? json_encode($item['location'] ?? null);
            }
        } elseif (isset($json['jobs']) && is_array($json['jobs'])) {
            foreach ($json['jobs'] as $item) {
                $titles[] = $item['title'] ?? $item['text'] ?? '';
                $locations[] = $item['location']['name'] ?? ($item['categories']['location'] ?? '');
            }
        } elseif (isset($json['offers']) && is_array($json['offers'])) {
            foreach ($json['offers'] as $item) {
                $titles[] = $item['title'] ?? '';
                $locations[] = ($item['city'] ?? '').' '.($item['country'] ?? '');
            }
        } elseif (isset($json['data']) && is_array($json['data'])) {
            foreach ($json['data'] as $item) {
                $titles[] = $item['title'] ?? $item['name'] ?? '';
                $loc = $item['location'] ?? $item['city'] ?? $item['workplace'] ?? null;
                $locations[] = is_string($loc) ? $loc : json_encode($loc);
            }
        } elseif (array_is_list($json)) {
            foreach ($json as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $titles[] = $item['title'] ?? $item['text'] ?? '';
                $locations[] = $item['categories']['location'] ?? ($item['city'] ?? '');
            }
        }
    }
    $out[$key] = [
        'http' => $r['http_status'],
        'count' => count(array_filter($titles)),
        'titles' => array_values(array_filter($titles)),
        'locations' => array_slice($locations, 0, 20),
        'json_keys' => is_array($json) ? array_slice(array_keys($json), 0, 20) : null,
    ];
    echo $key.' HTTP='.$r['http_status'].' titles='.count(array_filter($titles))."\n";
    foreach (array_slice(array_filter($titles), 0, 12) as $t) {
        echo '  - '.$t."\n";
    }
    echo "\n";
}

$after = ['jobs' => Job::count(), 'sources' => JobSource::count()];
file_put_contents(__DIR__.'/../storage/phase-g-top-source-titles.json', json_encode([
    'generated_at' => now()->toIso8601String(),
    'db_writes' => ($before !== $after) ? 1 : 0,
    'sources' => $out,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
echo 'writes='.(($before !== $after) ? 1 : 0)."\n";

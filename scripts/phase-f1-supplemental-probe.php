<?php

declare(strict_types=1);

/**
 * Phase F.1 supplemental probe — previously flagged Lever board + a few TR SaaS slugs.
 * Read-only. NO DB writes.
 */

require __DIR__.'/ats-coverage-discovery-helpers.php';
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$extra = [
    ['provider' => 'lever', 'company' => 'Çiçeksepeti', 'slug' => 'ciceksepeti', 'url' => 'https://api.lever.co/v0/postings/ciceksepeti?mode=json'],
    ['provider' => 'lever', 'company' => 'Hepsijet', 'slug' => 'hepsijet', 'url' => 'https://api.lever.co/v0/postings/hepsijet?mode=json'],
    ['provider' => 'recruitee', 'company' => 'ikas', 'slug' => 'ikas', 'url' => 'https://ikas.recruitee.com/api/offers'],
    ['provider' => 'recruitee', 'company' => 'IdeaSoft', 'slug' => 'ideasoft', 'url' => 'https://ideasoft.recruitee.com/api/offers'],
    ['provider' => 'recruitee', 'company' => 'T-Soft', 'slug' => 'tsoft', 'url' => 'https://tsoft.recruitee.com/api/offers'],
    ['provider' => 'recruitee', 'company' => 'n11', 'slug' => 'n11', 'url' => 'https://n11.recruitee.com/api/offers'],
    ['provider' => 'recruitee', 'company' => 'Çiçeksepeti', 'slug' => 'ciceksepeti', 'url' => 'https://ciceksepeti.recruitee.com/api/offers'],
    ['provider' => 'workable', 'company' => 'ikas', 'slug' => 'ikas', 'url' => 'https://apply.workable.com/api/v1/widget/accounts/ikas?details=true'],
    ['provider' => 'workable', 'company' => 'ciceksepeti', 'slug' => 'ciceksepeti', 'url' => 'https://apply.workable.com/api/v1/widget/accounts/ciceksepeti?details=true'],
    ['provider' => 'greenhouse', 'company' => 'ciceksepeti', 'slug' => 'ciceksepeti', 'url' => 'https://boards-api.greenhouse.io/v1/boards/ciceksepeti/jobs?content=true'],
    ['provider' => 'ashby', 'company' => 'ciceksepeti', 'slug' => 'ciceksepeti', 'url' => 'https://api.ashbyhq.com/posting-api/job-board/ciceksepeti'],
    ['provider' => 'ashby', 'company' => 'ikas', 'slug' => 'ikas', 'url' => 'https://api.ashbyhq.com/posting-api/job-board/ikas'],
];

foreach ($extra as $row) {
    $response = httpGetJson($row['url']);
    $json = $response['json'];
    $count = 0;
    $titles = [];
    if (is_array($json)) {
        if (array_is_list($json)) {
            $count = count($json);
            $titles = array_slice(array_map(fn ($j) => $j['text'] ?? $j['title'] ?? '', $json), 0, 6);
        } elseif (isset($json['jobs']) && is_array($json['jobs'])) {
            $count = count($json['jobs']);
            $titles = array_slice(array_column($json['jobs'], 'title'), 0, 6);
        } elseif (isset($json['offers']) && is_array($json['offers'])) {
            $count = count($json['offers']);
            $titles = array_slice(array_column($json['offers'], 'title'), 0, 6);
        }
    }
    echo sprintf(
        "%s/%s HTTP=%s jobs=%d titles=%s\n",
        $row['provider'],
        $row['slug'],
        (string) $response['http_status'],
        $count,
        json_encode($titles, JSON_UNESCAPED_UNICODE)
    );
    usleep(150000);
}

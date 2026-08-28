<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Job;
use App\Models\JobSource;

$sourceId = (int) ($argv[1] ?? 8);
$label = $argv[2] ?? 'snapshot';

$source = JobSource::query()->find($sourceId);

if ($source === null) {
    fwrite(STDERR, "Source {$sourceId} not found.\n");
    exit(1);
}

$jobs = Job::query()
    ->where('job_source_id', $sourceId)
    ->orderBy('id')
    ->get([
        'id',
        'external_id',
        'title',
        'first_seen_at',
        'provider_updated_at',
        'last_seen_at',
        'last_scraped_at',
        'published_at',
        'updated_at',
    ])
    ->map(fn (Job $job): array => [
        'id' => $job->id,
        'external_id' => $job->external_id,
        'title' => $job->title,
        'first_seen_at' => $job->first_seen_at?->toIso8601String(),
        'provider_updated_at' => $job->provider_updated_at?->toIso8601String(),
        'last_seen_at' => $job->last_seen_at?->toIso8601String(),
        'last_scraped_at' => $job->last_scraped_at?->toIso8601String(),
        'published_at' => $job->published_at?->toIso8601String(),
        'updated_at' => $job->updated_at?->toIso8601String(),
    ])
    ->values()
    ->all();

$payload = [
    'label' => $label,
    'captured_at' => now()->toIso8601String(),
    'source' => [
        'id' => $source->id,
        'name' => $source->name,
        'provider' => $source->config['provider'] ?? null,
    ],
    'job_count' => count($jobs),
    'jobs' => $jobs,
];

$path = __DIR__.'/../storage/ghost-activation-'.$sourceId.'-'.$label.'.json';
file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo "Saved {$path}\n";
echo 'Jobs captured: '.count($jobs)."\n";

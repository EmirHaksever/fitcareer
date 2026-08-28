<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Job;
use App\Models\JobSource;

$sources = JobSource::query()
    ->where('is_active', true)
    ->get(['id', 'name', 'config', 'last_success_at', 'consecutive_failures', 'last_items_found']);

foreach ($sources as $source) {
    if (($source->config['provider'] ?? '') !== 'workable') {
        continue;
    }

    $jobCount = Job::query()->where('job_source_id', $source->id)->count();

    echo json_encode([
        'id' => $source->id,
        'name' => $source->name,
        'last_success_at' => $source->last_success_at?->toIso8601String(),
        'consecutive_failures' => $source->consecutive_failures,
        'last_items_found' => $source->last_items_found,
        'job_count' => $jobCount,
    ], JSON_PRETTY_PRINT).PHP_EOL;
}

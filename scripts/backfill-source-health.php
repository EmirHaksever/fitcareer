<?php

declare(strict_types=1);

use App\Enums\ImportRunStatus;
use App\Models\JobImportRun;
use App\Models\JobSource;
use App\Services\Scraper\JobSourceHealthService;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$health = app(JobSourceHealthService::class);

foreach (JobSource::query()->orderBy('id')->get() as $source) {
    $latestSuccess = JobImportRun::query()
        ->where('job_source_id', $source->id)
        ->whereIn('status', [ImportRunStatus::Completed, ImportRunStatus::Partial])
        ->orderByDesc('finished_at')
        ->first();

    $latestFailure = JobImportRun::query()
        ->where('job_source_id', $source->id)
        ->where('status', ImportRunStatus::Failed)
        ->orderByDesc('finished_at')
        ->first();

    if ($latestSuccess !== null) {
        $health->recordSuccess($source, $latestSuccess);
        echo "Backfilled success for {$source->name}\n";
    }

    if ($latestFailure !== null && ($latestSuccess === null || $latestFailure->finished_at > $latestSuccess->finished_at)) {
        $error = is_array($latestFailure->error_log) ? implode('; ', $latestFailure->error_log) : 'Import failed';
        $health->recordFailure($source->fresh(), $latestFailure, $error);
        echo "Backfilled failure for {$source->name}: {$error}\n";
    }
}

echo "\n";
passthru(PHP_BINARY.' '.base_path('artisan').' jobs:source-health');

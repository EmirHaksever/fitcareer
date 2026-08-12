<?php

declare(strict_types=1);

use App\Models\Job;
use App\Models\JobImportRun;
use App\Models\JobSource;
use App\Enums\ScrapeStatus;
use App\Services\Scraper\ScrapedJobFreshnessService;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Job Import Runs ===\n";
foreach (JobImportRun::query()->orderBy('id')->get() as $run) {
    echo sprintf(
        "#%d source=%d status=%s found=%d created=%d updated=%d failed=%d\n",
        $run->id,
        $run->job_source_id,
        $run->status->value,
        $run->items_found,
        $run->items_created,
        $run->items_updated,
        $run->items_failed,
    );
}

echo "\n=== Job Counts ===\n";
echo 'Remotive (source 1): '.Job::query()->where('job_source_id', 1)->count()."\n";
echo 'Kariyer (source 2): '.Job::query()->where('job_source_id', 2)->count()."\n";
echo 'With last_seen_at: '.Job::query()->whereNotNull('last_seen_at')->count()."\n";

echo "\n=== Freshness Test ===\n";
$job = Job::query()->where('job_source_id', 1)->whereNotNull('last_seen_at')->first();
if ($job !== null) {
    $job->update([
        'last_seen_at' => now()->subHours(50),
        'scrape_status' => ScrapeStatus::Success,
    ]);
    echo 'Set job #'.$job->id." last_seen_at to 50h ago\n";
}

$source = JobSource::query()->find(1);
$result = app(ScrapedJobFreshnessService::class)->applyLifecycle($source);
echo 'Freshness result: '.json_encode($result)."\n";
if ($job !== null) {
    echo 'Job #'.$job->id.' scrape_status after: '.$job->fresh()->scrape_status->value."\n";
}

echo "\n=== Duplicate Check (Remotive external_id) ===\n";
$dupes = Job::query()
    ->selectRaw('job_source_id, external_id, COUNT(*) as c')
    ->where('job_source_id', 1)
    ->groupBy('job_source_id', 'external_id')
    ->having('c', '>', 1)
    ->get();
echo 'Duplicate groups: '.$dupes->count()."\n";

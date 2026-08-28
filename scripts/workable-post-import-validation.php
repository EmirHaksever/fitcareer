<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Job;
use App\Models\JobImportRun;
use App\Models\JobSource;
use Illuminate\Support\Facades\DB;

$workableSources = JobSource::query()
    ->where('config->provider', 'workable')
    ->orderBy('id')
    ->get();

echo "=== WORKABLE POST-IMPORT VALIDATION ===\n\n";

foreach ($workableSources as $source) {
    $run = JobImportRun::query()->where('job_source_id', $source->id)->latest('id')->first();
    $jobCount = Job::query()->where('job_source_id', $source->id)->count();
    $activeCount = Job::query()->where('job_source_id', $source->id)->where('status', 'published')->count();
    $dupes = DB::select(
        'SELECT external_id, COUNT(*) as c FROM jobs WHERE job_source_id = ? GROUP BY external_id HAVING c > 1',
        [$source->id],
    );

    echo $source->name.' (id='.$source->id.")\n";
    echo '  Run: fetched='.($run->items_found ?? 'n/a')
        .' created='.($run->items_created ?? 'n/a')
        .' updated='.($run->items_updated ?? 'n/a')
        .' failed='.($run->items_failed ?? 'n/a')
        .' skipped='.($run->items_skipped ?? 'n/a')
        .' status='.($run->status->value ?? 'n/a')."\n";
    echo "  DB jobs: {$jobCount} active: {$activeCount} duplicate external_ids: ".count($dupes)."\n";
    echo '  Health: last_success='.($source->last_success_at ?? 'null')
        .' consecutive_failures='.$source->consecutive_failures
        .' last_error='.($source->last_error ?? 'null')."\n";

    $sample = Job::query()->where('job_source_id', $source->id)->first();
    if ($sample) {
        echo '  Sample: company='.$sample->source_company_name
            .' city='.($sample->city ?? 'null')
            .' country='.($sample->country ?? 'null')
            .' url='.($sample->external_url ? 'yes' : 'no')
            .' published='.($sample->published_at ? 'yes' : 'no')
            .' emp='.($sample->employment_type?->value ?? 'null')
            .' work='.($sample->work_type?->value ?? 'null')."\n";
    }

    echo "\n";
}

$wingie = JobSource::query()->where('name', 'Wingie Enuygun')->first();
$wingieDup = Job::query()
    ->where('job_source_id', $wingie->id)
    ->where('external_id', '1FF5A9AA2B')
    ->count();
echo "Wingie duplicate shortcode 1FF5A9AA2B count: {$wingieDup} (expected 1)\n\n";

$leverJobs = Job::query()
    ->whereHas('sourceProvider', fn ($q) => $q->where('config->provider', 'lever'))
    ->get(['id', 'title', 'source_company_name', 'external_url', 'city', 'country']);

$workableJobs = Job::query()
    ->whereHas('sourceProvider', fn ($q) => $q->where('config->provider', 'workable'))
    ->get(['id', 'title', 'source_company_name', 'external_url', 'city', 'country']);

$urlOverlap = [];
$titleCompanyOverlap = [];
$fullOverlap = [];

foreach ($workableJobs as $workableJob) {
    foreach ($leverJobs as $leverJob) {
        if ($workableJob->external_url && $leverJob->external_url && $workableJob->external_url === $leverJob->external_url) {
            $urlOverlap[] = $workableJob->external_url;
        }

        if (strcasecmp($workableJob->title, $leverJob->title) === 0
            && strcasecmp($workableJob->source_company_name ?? '', $leverJob->source_company_name ?? '') === 0) {
            $titleCompanyOverlap[] = $workableJob->title.' @ '.$workableJob->source_company_name;
        }

        if (strcasecmp($workableJob->title, $leverJob->title) === 0
            && strcasecmp($workableJob->source_company_name ?? '', $leverJob->source_company_name ?? '') === 0
            && strcasecmp($workableJob->city ?? '', $leverJob->city ?? '') === 0) {
            $fullOverlap[] = $workableJob->title.' @ '.$workableJob->source_company_name.' ('.$workableJob->city.')';
        }
    }
}

echo "=== LEVER OVERLAP ===\n";
echo 'external_url matches: '.count($urlOverlap)."\n";
echo 'title+company matches: '.count($titleCompanyOverlap)."\n";
echo 'company+title+location matches: '.count($fullOverlap)."\n\n";

echo 'Total Workable jobs in DB: '.Job::query()
    ->whereHas('sourceProvider', fn ($q) => $q->where('config->provider', 'workable'))
    ->count()."\n";
echo 'Total Lever jobs in DB: '.Job::query()
    ->whereHas('sourceProvider', fn ($q) => $q->where('config->provider', 'lever'))
    ->count()."\n";

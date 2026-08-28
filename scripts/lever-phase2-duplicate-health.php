<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Job;
use App\Models\JobImportRun;
use App\Models\JobSource;
use Illuminate\Support\Facades\DB;

$leverSources = JobSource::query()
    ->where('config->provider', 'lever')
    ->orderBy('name')
    ->get();

echo "=== HEALTH ===\n";
foreach ($leverSources as $source) {
    $latestRun = JobImportRun::query()
        ->where('job_source_id', $source->id)
        ->orderByDesc('id')
        ->first();

    echo sprintf(
        "%s | success=%s | failures=%d | last_error=%s | latest_run=%s fetched=%d created=%d updated=%d failed=%d\n",
        $source->name,
        $source->last_success_at?->toDateTimeString() ?? 'null',
        $source->consecutive_failures,
        $source->last_error ?? '-',
        $latestRun?->status->value ?? 'none',
        $latestRun?->items_found ?? 0,
        $latestRun?->items_created ?? 0,
        $latestRun?->items_updated ?? 0,
        $latestRun?->items_failed ?? 0,
    );
}

$sourceIds = $leverSources->pluck('id')->all();
$jobs = Job::query()->whereIn('job_source_id', $sourceIds)->get();

echo "\n=== COVERAGE ===\n";
echo 'Total Lever jobs in DB: '.$jobs->count()."\n";

$turkey = 0;
$istanbul = 0;
foreach ($jobs as $job) {
    $hay = mb_strtolower(trim(($job->city ?? '').' '.($job->country ?? '')));
    if (str_contains($hay, 'turkey') || str_contains($hay, 'türkiye') || str_contains($hay, 'turkiye')) {
        $turkey++;
    }
    if (str_contains($hay, 'istanbul') || str_contains($hay, 'İstanbul')) {
        $istanbul++;
    }
}
echo "DB jobs with Turkey in city/country: {$turkey}\n";
echo "DB jobs with Istanbul in city/country: {$istanbul}\n";

echo "\n=== DUPLICATE ANALYSIS ===\n";

// Cross-board same external_id (different UUID boards shouldn't collide)
$crossExternalId = DB::select('
    SELECT external_id, COUNT(DISTINCT job_source_id) as source_count, COUNT(*) as row_count
    FROM jobs
    WHERE job_source_id IN ('.implode(',', $sourceIds).')
    GROUP BY external_id
    HAVING source_count > 1
    LIMIT 20
');
echo 'Cross-board duplicate external_id groups: '.count($crossExternalId)."\n";

// Cross-board same external_url
$crossUrl = DB::select('
    SELECT external_url, COUNT(DISTINCT job_source_id) as source_count, COUNT(*) as row_count
    FROM jobs
    WHERE job_source_id IN ('.implode(',', $sourceIds).')
      AND external_url IS NOT NULL
    GROUP BY external_url
    HAVING source_count > 1
    LIMIT 20
');
echo 'Cross-board duplicate external_url groups: '.count($crossUrl)."\n";
foreach ($crossUrl as $row) {
    echo "  URL dup: {$row->external_url} sources={$row->source_count} rows={$row->row_count}\n";
}

// Cross-board same normalized title + company
$crossTitle = DB::select('
    SELECT LOWER(title) as title_key, LOWER(source_company_name) as company_key,
           COUNT(DISTINCT job_source_id) as source_count, COUNT(*) as row_count
    FROM jobs
    WHERE job_source_id IN ('.implode(',', $sourceIds).')
    GROUP BY title_key, company_key
    HAVING source_count > 1
    LIMIT 20
');
echo 'Cross-board duplicate title+company groups: '.count($crossTitle)."\n";
foreach ($crossTitle as $row) {
    echo "  Title dup: {$row->title_key} | {$row->company_key} sources={$row->source_count}\n";
}

echo "\nPer-source job counts:\n";
foreach ($leverSources as $source) {
    $count = Job::query()->where('job_source_id', $source->id)->count();
    echo "  {$source->name}: {$count}\n";
}

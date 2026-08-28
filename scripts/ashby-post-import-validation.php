<?php

declare(strict_types=1);

use App\Enums\JobOrigin;
use App\Enums\JobStatus;
use App\Models\Job;
use App\Models\JobSource;
use App\Services\Scraper\LocationClassificationService;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$classifier = new LocationClassificationService;

$ashbySources = JobSource::query()
    ->where('is_active', true)
    ->where('config->provider', 'ashby')
    ->orderBy('name')
    ->get();

$sourceIds = $ashbySources->pluck('id')->all();

$jobs = Job::query()
    ->whereIn('job_source_id', $sourceIds)
    ->where('source', JobOrigin::Scraped)
    ->where('status', JobStatus::Published)
    ->get();

$breakdown = [
    'total' => $jobs->count(),
    'turkey_relevant' => 0,
    'istanbul' => 0,
    'foreign' => 0,
    'unknown' => 0,
];

foreach ($jobs as $job) {
    $result = $classifier->classify(
        App\Services\Scraper\DTO\LocationInput::fromSignals(
            $job->city,
            $job->country,
            $job->work_type,
        ),
    );

    if ($result->isTurkeyRelevant) {
        $breakdown['turkey_relevant']++;
    }

    match ($result->category->value) {
        'istanbul' => $breakdown['istanbul']++,
        'foreign' => $breakdown['foreign']++,
        'unknown' => $breakdown['unknown']++,
        default => null,
    };
}

$ashbyUrls = $jobs->pluck('external_url')->filter()->values()->all();

$urlDuplicates = DB::table('jobs as j')
    ->join('job_sources as js', 'j.job_source_id', '=', 'js.id')
    ->where('j.status', JobStatus::Published->value)
    ->whereNotIn('j.job_source_id', $sourceIds)
    ->whereIn('j.external_url', $ashbyUrls)
    ->select('j.external_url', 'j.title', 'js.name as source_name')
    ->get();

$titleCompanyOverlaps = DB::table('jobs as a')
    ->join('job_sources as js_a', 'a.job_source_id', '=', 'js_a.id')
    ->join('jobs as b', function ($join) use ($sourceIds): void {
        $join->on('a.title', '=', 'b.title')
            ->on('a.source_company_name', '=', 'b.source_company_name')
            ->whereIn('a.job_source_id', $sourceIds)
            ->whereNotIn('b.job_source_id', $sourceIds);
    })
    ->join('job_sources as js_b', 'b.job_source_id', '=', 'js_b.id')
    ->where('a.status', JobStatus::Published->value)
    ->where('b.status', JobStatus::Published->value)
    ->select('a.title', 'a.source_company_name', 'js_a.name as ashby_source', 'js_b.name as other_source')
    ->distinct()
    ->get();

$importRuns = DB::table('job_import_runs')
    ->whereIn('job_source_id', $sourceIds)
    ->orderByDesc('id')
    ->get()
    ->groupBy('job_source_id')
    ->map(fn ($runs) => $runs->first());

echo json_encode([
    'sources' => $ashbySources->map(fn (JobSource $source) => [
        'id' => $source->id,
        'name' => $source->name,
        'slug' => $source->config['site_slug'] ?? null,
        'last_success_at' => $source->last_success_at?->toIso8601String(),
        'last_failure_at' => $source->last_failure_at?->toIso8601String(),
        'consecutive_failures' => $source->consecutive_failures,
        'last_items_found' => $source->last_items_found,
        'latest_import' => isset($importRuns[$source->id]) ? [
            'id' => $importRuns[$source->id]->id,
            'status' => $importRuns[$source->id]->status,
            'fetched' => $importRuns[$source->id]->items_found,
            'created' => $importRuns[$source->id]->items_created,
            'updated' => $importRuns[$source->id]->items_updated,
            'failed' => $importRuns[$source->id]->items_failed,
        ] : null,
    ])->values(),
    'breakdown' => $breakdown,
    'url_duplicates' => $urlDuplicates,
    'title_company_overlaps' => $titleCompanyOverlaps,
    'scheduler' => [
        'note' => 'Ashby sources use existing RunJobSourceImportJob scheduler with refresh_interval_minutes=360',
        'active_ashby_sources' => $ashbySources->count(),
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;

<?php

declare(strict_types=1);

use App\Enums\JobOrigin;
use App\Enums\JobStatus;
use App\Models\Job;
use App\Models\JobSource;
use App\Services\Scraper\DTO\LocationInput;
use App\Services\Scraper\LocationClassificationService;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$classifier = new LocationClassificationService;

$greenhouseSources = JobSource::query()
    ->where('is_active', true)
    ->where('config->provider', 'greenhouse')
    ->orderBy('name')
    ->get();

$sourceIds = $greenhouseSources->pluck('id')->all();

$jobs = Job::query()
    ->whereIn('job_source_id', $sourceIds)
    ->where('source', JobOrigin::Scraped)
    ->where('status', JobStatus::Published)
    ->get();

$breakdown = [
    'total' => $jobs->count(),
    'turkey_relevant' => 0,
    'istanbul' => 0,
    'other_turkey' => 0,
    'foreign' => 0,
    'unknown' => 0,
];

$fieldCoverage = [
    'title' => 0,
    'external_id' => 0,
    'external_url' => 0,
    'published_at' => 0,
    'description' => 0,
    'city' => 0,
    'country' => 0,
];

foreach ($jobs as $job) {
    if ($job->title !== '') {
        $fieldCoverage['title']++;
    }
    if ($job->external_id !== null && $job->external_id !== '') {
        $fieldCoverage['external_id']++;
    }
    if ($job->external_url !== null && $job->external_url !== '') {
        $fieldCoverage['external_url']++;
    }
    if ($job->published_at !== null) {
        $fieldCoverage['published_at']++;
    }
    if ($job->description !== null && trim($job->description) !== '') {
        $fieldCoverage['description']++;
    }
    if ($job->city !== null && $job->city !== '') {
        $fieldCoverage['city']++;
    }
    if ($job->country !== null && $job->country !== '') {
        $fieldCoverage['country']++;
    }

    $result = $classifier->classify(LocationInput::fromSignals(
        $job->city,
        $job->country,
        $job->work_type,
    ));

    if ($result->isTurkeyRelevant) {
        $breakdown['turkey_relevant']++;
    }

    match ($result->category->value) {
        'istanbul' => $breakdown['istanbul']++,
        'other_turkey' => $breakdown['other_turkey']++,
        'foreign' => $breakdown['foreign']++,
        'unknown' => $breakdown['unknown']++,
        default => null,
    };
}

$greenhouseUrls = $jobs->pluck('external_url')->filter()->values()->all();

$otherSourceIds = JobSource::query()
    ->where('is_active', true)
    ->whereIn('config->provider', ['lever', 'workable', 'ashby'])
    ->pluck('id')
    ->all();

$urlDuplicates = DB::table('jobs as j')
    ->join('job_sources as js', 'j.job_source_id', '=', 'js.id')
    ->where('j.status', JobStatus::Published->value)
    ->whereIn('j.job_source_id', $otherSourceIds)
    ->whereIn('j.external_url', $greenhouseUrls)
    ->select('j.external_url', 'j.title', 'js.name as source_name', 'js.config->provider as provider')
    ->get();

$titleCompanyOverlaps = DB::table('jobs as g')
    ->join('job_sources as js_g', 'g.job_source_id', '=', 'js_g.id')
    ->join('jobs as o', function ($join) use ($sourceIds, $otherSourceIds): void {
        $join->on('g.title', '=', 'o.title')
            ->on('g.source_company_name', '=', 'o.source_company_name')
            ->whereIn('g.job_source_id', $sourceIds)
            ->whereIn('o.job_source_id', $otherSourceIds);
    })
    ->join('job_sources as js_o', 'o.job_source_id', '=', 'js_o.id')
    ->where('g.status', JobStatus::Published->value)
    ->where('o.status', JobStatus::Published->value)
    ->select('g.title', 'g.source_company_name', 'js_g.name as greenhouse_source', 'js_o.name as other_source', 'js_o.config->provider as other_provider')
    ->distinct()
    ->get();

$importRuns = DB::table('job_import_runs')
    ->whereIn('job_source_id', $sourceIds)
    ->orderByDesc('id')
    ->get()
    ->groupBy('job_source_id')
    ->map(fn ($runs) => $runs->first());

echo json_encode([
    'sources' => $greenhouseSources->map(fn (JobSource $source) => [
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
    'field_coverage' => $fieldCoverage,
    'url_duplicates_vs_lever_workable_ashby' => $urlDuplicates,
    'title_company_overlaps_vs_lever_workable_ashby' => $titleCompanyOverlaps,
    'scheduler' => [
        'note' => 'Greenhouse sources enrolled in existing jobs:dispatch-scheduled-imports with refresh_interval_minutes=360',
        'active_greenhouse_sources' => $greenhouseSources->count(),
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;

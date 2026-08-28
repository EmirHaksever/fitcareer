<?php

declare(strict_types=1);

/**
 * READ-ONLY dry-run: Turkey relevance ingest policy impact on existing jobs.
 * Does NOT modify data. Use before any optional cleanup apply script.
 */

use App\Enums\JobSourceIngestPolicy;
use App\Enums\JobStatus;
use App\Enums\WorkType;
use App\Models\Job;
use App\Models\JobSource;
use App\Services\Scraper\DTO\LocationInput;
use App\Services\Scraper\LocationClassificationService;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$now = now();
$classifier = app(LocationClassificationService::class);

$publishedScope = static function ($query) use ($now): void {
    $query->where('status', JobStatus::Published)
        ->where(function ($inner) use ($now): void {
            $inner->whereNull('expires_at')->orWhere('expires_at', '>', $now);
        });
};

$sources = JobSource::query()->orderBy('id')->get();

$report = [
    'generated_at' => $now->toIso8601String(),
    'mode' => 'read_only_dry_run',
    'totals' => [
        'published_active' => Job::query()->where($publishedScope)->count(),
        'turkey_relevant_published' => 0,
        'not_turkey_relevant_published' => 0,
    ],
    'sources' => [],
    'zynga_oliver' => [],
    'policy_simulation' => [
        'would_reject_on_future_import_turkey_first' => 0,
        'would_accept_on_future_import_turkey_first' => 0,
    ],
];

foreach ($sources as $source) {
    $jobs = Job::query()->where('job_source_id', $source->id)->where($publishedScope)->get();
    $trCount = 0;
    $globalCount = 0;
    $jobDetails = [];

    foreach ($jobs as $job) {
        $workType = $job->work_type instanceof WorkType ? $job->work_type : WorkType::tryFrom((string) $job->work_type);
        $relevant = $classifier->isTurkeyRelevant($job->city, $job->country, $workType);

        if ($relevant) {
            $trCount++;
            $report['totals']['turkey_relevant_published']++;
            $report['policy_simulation']['would_accept_on_future_import_turkey_first']++;
        } else {
            $globalCount++;
            $report['totals']['not_turkey_relevant_published']++;
            $report['policy_simulation']['would_reject_on_future_import_turkey_first']++;
        }
    }

    $sourceEntry = [
        'id' => $source->id,
        'name' => $source->name,
        'provider' => $source->config['provider'] ?? null,
        'is_active' => $source->is_active,
        'published_jobs' => $jobs->count(),
        'turkey_relevant' => $trCount,
        'not_turkey_relevant' => $globalCount,
        'turkey_pct' => $jobs->count() > 0 ? round($trCount / $jobs->count() * 100, 1) : 0,
    ];

    $report['sources'][] = $sourceEntry;

    if (in_array($source->name, ['Zynga', 'OLIVER Agency'], true)) {
        $report['zynga_oliver'][] = $sourceEntry;
    }
}

$report['notes'] = [
    'existing_jobs_not_modified' => true,
    'future_import_turkey_first' => 'Non-TR listings fail before create/update; existing global rows stop receiving last_seen_at refresh and expire via freshness lifecycle.',
    'source_deactivate_zynga_oliver' => 'Stops fetch entirely; existing 88 published rows remain until natural expiry.',
];

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n";

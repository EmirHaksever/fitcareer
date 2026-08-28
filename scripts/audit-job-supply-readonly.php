<?php

declare(strict_types=1);

/**
 * AUDIT-ONLY read-only job supply statistics. Does not modify data.
 */

use App\Enums\JobStatus;
use App\Models\Job;
use App\Models\JobImportRun;
use App\Models\JobSource;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$now = now();
$publishedScope = static function ($query) use ($now): void {
    $query->where('status', JobStatus::Published)
        ->where(function ($inner) use ($now): void {
            $inner->whereNull('expires_at')->orWhere('expires_at', '>', $now);
        });
};

$stats = [
    'generated_at' => $now->toIso8601String(),
    'total_jobs' => Job::count(),
    'published_active' => Job::query()->where($publishedScope)->count(),
    'draft_or_unpublished' => Job::where('status', '!=', JobStatus::Published)->count(),
    'expired_by_date' => Job::where('status', JobStatus::Published)
        ->whereNotNull('expires_at')->where('expires_at', '<=', $now)->count(),
    'internal_published' => Job::query()->where($publishedScope)->where('source', 'internal')->count(),
    'scraped_published' => Job::query()->where($publishedScope)->where('source', 'scraped')->count(),
];

$sources = JobSource::query()->orderBy('id')->get();
$byProvider = [];
$sourceDetails = [];

foreach ($sources as $source) {
    $provider = $source->config['provider'] ?? 'unknown';
    $total = Job::where('job_source_id', $source->id)->count();
    $published = Job::query()->where('job_source_id', $source->id)->where($publishedScope)->count();

    if (! isset($byProvider[$provider])) {
        $byProvider[$provider] = ['sources' => 0, 'total_jobs' => 0, 'published_jobs' => 0];
    }
    $byProvider[$provider]['sources']++;
    $byProvider[$provider]['total_jobs'] += $total;
    $byProvider[$provider]['published_jobs'] += $published;

    $sourceDetails[] = [
        'id' => $source->id,
        'name' => $source->name,
        'provider' => $provider,
        'site_slug' => $source->config['site_slug'] ?? null,
        'is_active' => $source->is_active,
        'total_jobs' => $total,
        'published_jobs' => $published,
        'last_run_at' => $source->last_run_at?->toIso8601String(),
        'last_success_at' => $source->last_success_at?->toIso8601String(),
        'last_failure_at' => $source->last_failure_at?->toIso8601String(),
        'consecutive_failures' => $source->consecutive_failures,
        'last_items_found' => $source->last_items_found,
        'last_items_created' => $source->last_items_created,
        'last_items_updated' => $source->last_items_updated,
        'last_error' => $source->last_error ? mb_substr($source->last_error, 0, 300) : null,
        'max_listings' => $source->config['max_listings'] ?? null,
        'max_pages' => $source->config['max_pages'] ?? null,
        'max_posting_age_days' => $source->config['max_posting_age_days'] ?? null,
    ];
}

$stats['by_provider'] = $byProvider;
$stats['sources'] = $sourceDetails;

$stats['geo'] = [
    'istanbul_published' => Job::query()->where($publishedScope)->where(function ($q): void {
        $q->whereRaw('LOWER(city) LIKE ?', ['%istanbul%']);
    })->count(),
    'turkey_country_published' => Job::query()->where($publishedScope)->where(function ($q): void {
        $q->whereRaw('LOWER(country) LIKE ?', ['%türk%'])
            ->orWhereRaw('LOWER(country) LIKE ?', ['%turkey%']);
    })->count(),
    'remote_work_type_published' => Job::query()->where($publishedScope)->where('work_type', 'remote')->count(),
    'null_city_published' => Job::query()->where($publishedScope)->whereNull('city')->count(),
    'null_country_published' => Job::query()->where($publishedScope)->whereNull('country')->count(),
];

$stats['freshness'] = [
    'seen_today' => Job::whereDate('last_seen_at', $now->toDateString())->count(),
    'seen_3d' => Job::where('last_seen_at', '>=', $now->copy()->subDays(3))->count(),
    'seen_7d' => Job::where('last_seen_at', '>=', $now->copy()->subDays(7))->count(),
    'older_30d_or_null' => Job::where(function ($q) use ($now): void {
        $q->where('last_seen_at', '<', $now->copy()->subDays(30))->orWhereNull('last_seen_at');
    })->count(),
    'scrape_status_stale' => Job::where('scrape_status', 'stale')->count(),
    'status_expired' => Job::where('status', JobStatus::Expired)->count(),
];

$stats['import_runs_recent'] = JobImportRun::query()
    ->orderByDesc('id')
    ->limit(40)
    ->get()
    ->map(static fn (JobImportRun $run): array => [
        'id' => $run->id,
        'job_source_id' => $run->job_source_id,
        'status' => $run->status->value ?? (string) $run->status,
        'items_found' => $run->items_found,
        'items_created' => $run->items_created,
        'items_updated' => $run->items_updated,
        'items_failed' => $run->items_failed,
        'items_stale' => $run->items_stale ?? null,
        'items_expired' => $run->items_expired ?? null,
        'started_at' => $run->started_at?->toIso8601String(),
        'finished_at' => $run->finished_at?->toIso8601String(),
    ])
    ->all();

$stats['import_totals_30d'] = JobImportRun::query()
    ->where('started_at', '>=', $now->copy()->subDays(30))
    ->selectRaw('status, COUNT(*) as runs, SUM(items_found) as found, SUM(items_created) as created, SUM(items_updated) as updated, SUM(items_failed) as failed')
    ->groupBy('status')
    ->get()
    ->all();

echo json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;

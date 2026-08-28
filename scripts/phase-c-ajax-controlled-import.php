<?php

declare(strict_types=1);

/**
 * Phase C — Ajax Systems controlled import verification (read-only analysis helpers + import).
 *
 * Usage:
 *   php scripts/phase-c-ajax-controlled-import.php before
 *   php scripts/phase-c-ajax-controlled-import.php seed
 *   php scripts/phase-c-ajax-controlled-import.php import
 *   php scripts/phase-c-ajax-controlled-import.php verify
 *   php scripts/phase-c-ajax-controlled-import.php idempotency
 */

use App\Enums\JobOrigin;
use App\Enums\JobStatus;
use App\Enums\WorkType;
use App\Models\Job;
use App\Models\JobImportRun;
use App\Models\JobSource;
use App\Services\Scraper\DTO\LocationInput;
use App\Services\Scraper\JobSourceImportService;
use App\Services\Scraper\LocationClassificationService;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$slug = 'ajax';
$command = $argv[1] ?? 'help';

$publishedScope = static function ($query): void {
    $now = now();
    $query->where('status', JobStatus::Published)
        ->where(function ($inner) use ($now): void {
            $inner->whereNull('expires_at')->orWhere('expires_at', '>', $now);
        });
};

function resolveAjaxSource(): ?JobSource
{
    return JobSource::query()
        ->where('is_active', true)
        ->where('config->site_slug', 'ajax')
        ->first();
}

function ajaxStats(callable $publishedScope): array
{
    $source = resolveAjaxSource();

    return [
        'generated_at' => now()->toIso8601String(),
        'source_exists' => $source !== null,
        'source_id' => $source?->id,
        'total_jobs' => Job::count(),
        'published_jobs' => Job::query()->where($publishedScope)->count(),
        'ajax_total' => $source ? Job::where('job_source_id', $source->id)->count() : 0,
        'ajax_published' => $source ? Job::query()->where('job_source_id', $source->id)->where($publishedScope)->count() : 0,
    ];
}

function countTurkeyPolicyRejections(?JobImportRun $run): int
{
    if ($run === null || ! is_array($run->error_log)) {
        return 0;
    }

    return count(array_filter(
        $run->error_log,
        static fn (string $line): bool => str_contains($line, 'ingest_policy=turkey_first'),
    ));
}

match ($command) {
    'before', 'after' => (function () use ($command, $publishedScope): void {
        $stats = ajaxStats($publishedScope);
        $path = storage_path('phase-c-ajax-'.$command.'.json');
        file_put_contents($path, json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        echo json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
    })(),

    'seed' => (function (): void {
        passthru(PHP_BINARY.' '.escapeshellarg(__DIR__.'/seed-lever-sources.php'), $exitCode);
        exit($exitCode);
    })(),

    'import' => (function () use ($publishedScope, $slug): void {
        $source = resolveAjaxSource();
        if ($source === null) {
            fwrite(STDERR, "Ajax source not found. Run: seed\n");
            exit(1);
        }

        $beforeJobs = Job::where('job_source_id', $source->id)->count();
        $result = app(JobSourceImportService::class)->import($source);
        $run = $result['run']->fresh();
        $source->refresh();

        $duplicateExternalIds = Job::query()
            ->select('external_id', DB::raw('COUNT(*) as cnt'))
            ->where('job_source_id', $source->id)
            ->groupBy('external_id')
            ->having('cnt', '>', 1)
            ->count();

        $output = [
            'source' => $source->name,
            'slug' => $slug,
            'ingest_policy' => $source->config['ingest_policy'] ?? null,
            'before_total' => $beforeJobs,
            'import' => [
                'run_id' => $run->id,
                'status' => $run->status->value,
                'fetched' => $result['fetched'],
                'created' => $result['created'],
                'updated' => $result['updated'],
                'failed' => $result['failed'],
                'turkey_policy_rejected' => countTurkeyPolicyRejections($run),
            ],
            'after_total' => Job::where('job_source_id', $source->id)->count(),
            'duplicate_external_ids' => $duplicateExternalIds,
        ];

        $path = storage_path('phase-c-ajax-import.json');
        file_put_contents($path, json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
    })(),

    'verify' => (function () use ($publishedScope): void {
        $source = resolveAjaxSource();
        if ($source === null) {
            fwrite(STDERR, "Ajax source not found.\n");
            exit(1);
        }

        $classifier = app(LocationClassificationService::class);
        $jobs = Job::query()
            ->where('job_source_id', $source->id)
            ->where($publishedScope)
            ->orderBy('id')
            ->get();

        $issues = [];
        $accepted = [];

        foreach ($jobs as $job) {
            $workType = $job->work_type instanceof WorkType ? $job->work_type : WorkType::tryFrom((string) $job->work_type);
            $relevant = $classifier->isTurkeyRelevant($job->city, $job->country, $workType);

            if (! $relevant) {
                $issues[] = 'not_turkey_relevant job_id='.$job->id;
            }
            if ($job->title === '') {
                $issues[] = 'empty_title job_id='.$job->id;
            }
            if ($job->external_id === null || $job->external_id === '') {
                $issues[] = 'empty_external_id job_id='.$job->id;
            }
            if ($job->source !== JobOrigin::Scraped) {
                $issues[] = 'wrong_origin job_id='.$job->id;
            }
            if ($job->first_seen_at === null) {
                $issues[] = 'missing_first_seen_at job_id='.$job->id;
            }
            if ($job->last_seen_at === null) {
                $issues[] = 'missing_last_seen_at job_id='.$job->id;
            }

            $accepted[] = [
                'id' => $job->id,
                'title' => $job->title,
                'city' => $job->city,
                'country' => $job->country,
                'work_type' => $workType?->value,
                'external_id' => $job->external_id,
                'turkey_relevant' => $relevant,
                'first_seen_at' => $job->first_seen_at?->toIso8601String(),
                'last_seen_at' => $job->last_seen_at?->toIso8601String(),
            ];
        }

        $output = [
            'source' => $source->name,
            'accepted_count' => count($accepted),
            'accepted_jobs' => $accepted,
            'issues' => $issues,
        ];

        $path = storage_path('phase-c-ajax-verify.json');
        file_put_contents($path, json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
    })(),

    'idempotency' => (function () use ($publishedScope): void {
        $source = resolveAjaxSource();
        if ($source === null) {
            fwrite(STDERR, "Ajax source not found.\n");
            exit(1);
        }

        $beforeCount = Job::where('job_source_id', $source->id)->count();
        $result = app(JobSourceImportService::class)->import($source);
        $run = $result['run']->fresh();
        $afterCount = Job::where('job_source_id', $source->id)->count();

        $duplicateExternalIds = Job::query()
            ->select('external_id', DB::raw('COUNT(*) as cnt'))
            ->where('job_source_id', $source->id)
            ->groupBy('external_id')
            ->having('cnt', '>', 1)
            ->count();

        $output = [
            'before_total' => $beforeCount,
            'after_total' => $afterCount,
            'new_rows' => $afterCount - $beforeCount,
            'created' => $result['created'],
            'updated' => $result['updated'],
            'failed' => $result['failed'],
            'turkey_policy_rejected' => countTurkeyPolicyRejections($run),
            'duplicate_external_ids' => $duplicateExternalIds,
        ];

        $path = storage_path('phase-c-ajax-idempotency.json');
        file_put_contents($path, json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
    })(),

    default => (function (): void {
        echo "Commands: before | after | seed | import | verify | idempotency\n";
    })(),
};

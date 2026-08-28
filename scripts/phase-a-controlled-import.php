<?php

declare(strict_types=1);

/**
 * Phase A — controlled import, quality gate, and idempotency verification.
 *
 * Usage:
 *   php scripts/phase-a-controlled-import.php before
 *   php scripts/phase-a-controlled-import.php import --source=iyzico
 *   php scripts/phase-a-controlled-import.php after
 *   php scripts/phase-a-controlled-import.php quality --source=iyzico
 *   php scripts/phase-a-controlled-import.php tracking --source=iyzico
 */

use App\Enums\JobOrigin;
use App\Enums\JobStatus;
use App\Models\Job;
use App\Models\JobSource;
use App\Services\Scraper\JobSourceImportService;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$phaseASlugs = ['iyzico', 'grand', 'zyngacareers', 'oliver', 'feraset'];

$command = $argv[1] ?? 'help';
$sourceArg = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--source=')) {
        $sourceArg = substr($arg, 9);
    }
}

$publishedScope = static function ($query): void {
    $now = now();
    $query->where('status', JobStatus::Published)
        ->where(function ($inner) use ($now): void {
            $inner->whereNull('expires_at')->orWhere('expires_at', '>', $now);
        });
};

function snapshotStats(callable $publishedScope): array
{
    return [
        'generated_at' => now()->toIso8601String(),
        'total_jobs' => Job::count(),
        'published_jobs' => Job::query()->where($publishedScope)->count(),
        'active_sources' => JobSource::where('is_active', true)->count(),
        'istanbul_published' => Job::query()->where($publishedScope)->where(function ($q): void {
            $q->where('city', 'like', '%Istanbul%')->orWhere('city', 'like', '%İstanbul%');
        })->count(),
        'turkey_published' => Job::query()->where($publishedScope)->where(function ($q): void {
            $q->where('country', 'like', '%Turkey%')->orWhere('country', 'like', '%Türkiye%')->orWhere('country', 'like', '%Turkiye%');
        })->count(),
        'by_provider' => JobSource::query()->get()->groupBy(fn ($s) => $s->config['provider'] ?? 'unknown')->map(function ($group) use ($publishedScope) {
            $ids = $group->pluck('id');

            return [
                'sources' => $group->count(),
                'published_jobs' => Job::query()->whereIn('job_source_id', $ids)->where($publishedScope)->count(),
            ];
        }),
    ];
}

function resolvePhaseASource(string $slug): ?JobSource
{
    return JobSource::query()
        ->where('is_active', true)
        ->where('config->site_slug', $slug)
        ->first();
}

match ($command) {
    'before', 'after' => (function () use ($command, $publishedScope): void {
        $stats = snapshotStats($publishedScope);
        $path = storage_path('phase-a-'.$command.'.json');
        file_put_contents($path, json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        echo json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
    })(),

    'import' => (function () use ($sourceArg, $publishedScope): void {
        if ($sourceArg === null) {
            fwrite(STDERR, "Usage: import --source=slug\n");
            exit(1);
        }

        $source = resolvePhaseASource($sourceArg);
        if ($source === null) {
            fwrite(STDERR, "Source not found: {$sourceArg}\n");
            exit(1);
        }

        $beforeJobs = Job::where('job_source_id', $source->id)->count();
        $beforePublished = Job::query()->where('job_source_id', $source->id)->where($publishedScope)->count();

        $result = app(JobSourceImportService::class)->import($source);
        $run = $result['run']->fresh();
        $source->refresh();

        $afterJobs = Job::where('job_source_id', $source->id)->count();
        $afterPublished = Job::query()->where('job_source_id', $source->id)->where($publishedScope)->count();

        $duplicateExternalIds = Job::query()
            ->select('external_id', DB::raw('COUNT(*) as cnt'))
            ->where('job_source_id', $source->id)
            ->groupBy('external_id')
            ->having('cnt', '>', 1)
            ->count();

        $output = [
            'source' => $source->name,
            'slug' => $sourceArg,
            'provider' => $source->config['provider'] ?? null,
            'before' => ['total' => $beforeJobs, 'published' => $beforePublished],
            'import' => [
                'run_id' => $run->id,
                'status' => $run->status->value,
                'fetched' => $result['fetched'],
                'created' => $result['created'],
                'updated' => $result['updated'],
                'failed' => $result['failed'],
            ],
            'after' => ['total' => $afterJobs, 'published' => $afterPublished],
            'duplicate_external_ids' => $duplicateExternalIds,
        ];

        $path = storage_path('phase-a-import-'.$sourceArg.'.json');
        file_put_contents($path, json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
    })(),

    'quality' => (function () use ($sourceArg, $publishedScope): void {
        if ($sourceArg === null) {
            fwrite(STDERR, "Usage: quality --source=slug\n");
            exit(1);
        }

        $source = resolvePhaseASource($sourceArg);
        if ($source === null) {
            fwrite(STDERR, "Source not found: {$sourceArg}\n");
            exit(1);
        }

        $jobs = Job::query()
            ->where('job_source_id', $source->id)
            ->where('source', JobOrigin::Scraped)
            ->where($publishedScope)
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        $issues = [];
        $samples = [];

        foreach ($jobs as $job) {
            if ($job->title === '') {
                $issues[] = 'empty title job_id='.$job->id;
            }
            if ($job->external_id === null || $job->external_id === '') {
                $issues[] = 'empty external_id job_id='.$job->id;
            }
            if ($job->external_url === null || ! filter_var($job->external_url, FILTER_VALIDATE_URL)) {
                $issues[] = 'broken url job_id='.$job->id;
            }
            if ($job->description === null || strlen(trim(strip_tags($job->description))) < 20) {
                $issues[] = 'short description job_id='.$job->id;
            }

            $samples[] = [
                'id' => $job->id,
                'title' => $job->title,
                'company' => $job->source_company_name,
                'city' => $job->city,
                'country' => $job->country,
                'work_type' => $job->work_type?->value,
                'description_length' => strlen($job->description ?? ''),
                'published_at' => $job->published_at?->toIso8601String(),
                'external_url' => $job->external_url,
                'external_id' => $job->external_id,
            ];
        }

        $output = [
            'source' => $source->name,
            'slug' => $sourceArg,
            'sample_count' => count($samples),
            'samples' => array_slice($samples, 0, 5),
            'issues' => $issues,
        ];

        echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
    })(),

    'tracking' => (function () use ($sourceArg): void {
        if ($sourceArg === null) {
            fwrite(STDERR, "Usage: tracking --source=slug\n");
            exit(1);
        }

        $source = resolvePhaseASource($sourceArg);
        if ($source === null) {
            fwrite(STDERR, "Source not found: {$sourceArg}\n");
            exit(1);
        }

        $jobs = Job::where('job_source_id', $source->id)->get();
        $total = $jobs->count();

        $output = [
            'source' => $source->name,
            'slug' => $sourceArg,
            'total_jobs' => $total,
            'first_seen_at_populated' => $jobs->whereNotNull('first_seen_at')->count(),
            'first_seen_at_null' => $jobs->whereNull('first_seen_at')->count(),
            'provider_updated_at_populated' => $jobs->whereNotNull('provider_updated_at')->count(),
            'provider_updated_at_null' => $jobs->whereNull('provider_updated_at')->count(),
            'last_seen_at_populated' => $jobs->whereNotNull('last_seen_at')->count(),
            'last_seen_at_null' => $jobs->whereNull('last_seen_at')->count(),
        ];

        echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
    })(),

    default => (function (): void {
        echo "Commands: before | after | import --source=slug | quality --source=slug | tracking --source=slug\n";
    })(),
};

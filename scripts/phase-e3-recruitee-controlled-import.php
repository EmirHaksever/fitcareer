<?php

declare(strict_types=1);

/**
 * Phase E.3 controlled Recruitee import report (read stats + import 6 core sources).
 */

use App\Enums\JobStatus;
use App\Models\Job;
use App\Models\JobSource;
use App\Services\Scraper\JobSourceImportService;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

function dbStats(): array
{
    $now = now();
    $publishedScope = static function ($query) use ($now): void {
        $query->where('status', JobStatus::Published)
            ->where(function ($inner) use ($now): void {
                $inner->whereNull('expires_at')->orWhere('expires_at', '>', $now);
            });
    };

    $recruiteeSourceIds = JobSource::query()
        ->get()
        ->filter(fn (JobSource $s): bool => ($s->config['provider'] ?? '') === 'recruitee')
        ->pluck('id')
        ->all();

    return [
        'timestamp' => $now->toIso8601String(),
        'total_jobs' => Job::count(),
        'published_active' => Job::query()->where($publishedScope)->count(),
        'turkey_visible' => Job::query()->where($publishedScope)->where(function ($q): void {
            $q->whereRaw('LOWER(country) LIKE ?', ['%türk%'])
                ->orWhereRaw('LOWER(country) LIKE ?', ['%turkey%']);
        })->count(),
        'istanbul' => Job::query()->where($publishedScope)->where(function ($q): void {
            $q->whereRaw('LOWER(city) LIKE ?', ['%istanbul%']);
        })->count(),
        'recruitee_jobs' => $recruiteeSourceIds === []
            ? 0
            : Job::query()->whereIn('job_source_id', $recruiteeSourceIds)->count(),
        'job_source_count' => JobSource::count(),
    ];
}

$before = dbStats();

echo "=== BEFORE ===\n";
echo json_encode($before, JSON_PRETTY_PRINT)."\n\n";

$importService = app(JobSourceImportService::class);
$sourceNames = [
    'Mikro Yazılım',
    'Zirve Yazılım',
    'Trio Mobil',
    'Krila Consultancy',
    'Nucs AI',
    'Paraşüt',
];

$results = [];
$totals = [
    'sources_processed' => 0,
    'offers_fetched' => 0,
    'created' => 0,
    'updated' => 0,
    'failed' => 0,
];

foreach ($sourceNames as $name) {
    $source = JobSource::query()->where('name', $name)->first();

    if ($source === null) {
        echo "SKIP missing source: {$name}\n";
        continue;
    }

    $result = $importService->import($source);
    $totals['sources_processed']++;
    $totals['offers_fetched'] += (int) ($result['fetched'] ?? 0);
    $totals['created'] += (int) ($result['created'] ?? 0);
    $totals['updated'] += (int) ($result['updated'] ?? 0);
    $totals['failed'] += (int) ($result['failed'] ?? 0);

    $results[] = [
        'employer' => $name,
        'slug' => $source->config['site_slug'] ?? null,
        'policy' => $source->config['ingest_policy'] ?? null,
        'active' => $source->is_active,
        'fetched' => $result['fetched'] ?? 0,
        'created' => $result['created'] ?? 0,
        'updated' => $result['updated'] ?? 0,
        'failed' => $result['failed'] ?? 0,
        'status' => ($result['run']->status->value ?? (string) $result['run']->status),
    ];

    echo "Imported {$name}: fetched={$result['fetched']} created={$result['created']} failed={$result['failed']}\n";
}

$afterFirst = dbStats();

echo "\n=== AFTER FIRST IMPORT ===\n";
echo json_encode($afterFirst, JSON_PRETTY_PRINT)."\n\n";

// Idempotency pass
$idempotentTotals = ['created' => 0, 'updated' => 0];
foreach ($sourceNames as $name) {
    $source = JobSource::query()->where('name', $name)->first();
    if ($source === null) {
        continue;
    }
    $result = $importService->import($source);
    $idempotentTotals['created'] += (int) ($result['created'] ?? 0);
    $idempotentTotals['updated'] += (int) ($result['updated'] ?? 0);
}

$afterSecond = dbStats();

$duplicateExternalIds = DB::select(
    'SELECT external_id, job_source_id, COUNT(*) AS cnt
     FROM jobs
     WHERE job_source_id IN (
         SELECT id FROM job_sources WHERE JSON_UNQUOTE(JSON_EXTRACT(config, "$.provider")) = "recruitee"
     )
     GROUP BY external_id, job_source_id
     HAVING cnt > 1'
);

$tracking = [
    'first_seen_at_null' => Job::query()
        ->whereIn('job_source_id', JobSource::all()->filter(fn ($s) => ($s->config['provider'] ?? '') === 'recruitee')->pluck('id'))
        ->whereNull('first_seen_at')->count(),
    'last_seen_at_null' => Job::query()
        ->whereIn('job_source_id', JobSource::all()->filter(fn ($s) => ($s->config['provider'] ?? '') === 'recruitee')->pluck('id'))
        ->whereNull('last_seen_at')->count(),
    'provider_updated_at_set' => Job::query()
        ->whereIn('job_source_id', JobSource::all()->filter(fn ($s) => ($s->config['provider'] ?? '') === 'recruitee')->pluck('id'))
        ->whereNotNull('provider_updated_at')->count(),
];

$acceptedJobs = Job::query()
    ->whereIn('job_source_id', JobSource::all()->filter(fn ($s) => ($s->config['provider'] ?? '') === 'recruitee')->pluck('id'))
    ->get(['title', 'city', 'country', 'external_id', 'job_source_id'])
    ->map(fn ($j) => [
        'title' => $j->title,
        'city' => $j->city,
        'country' => $j->country,
        'external_id' => $j->external_id,
    ])
    ->all();

$output = [
    'before' => $before,
    'after_first_import' => $afterFirst,
    'after_second_import' => $afterSecond,
    'import_results' => $results,
    'totals' => $totals,
    'idempotent_second_pass' => $idempotentTotals,
    'duplicate_external_ids' => $duplicateExternalIds,
    'tracking' => $tracking,
    'accepted_jobs' => $acceptedJobs,
];

$outPath = __DIR__.'/../storage/phase-e3-recruitee-controlled-import.json';
file_put_contents($outPath, json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

echo "\n=== IDEMPOTENT SECOND PASS ===\n";
echo json_encode($idempotentTotals, JSON_PRETTY_PRINT)."\n";
echo "\nOutput: {$outPath}\n";

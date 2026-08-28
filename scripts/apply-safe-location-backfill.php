<?php

declare(strict_types=1);

/**
 * Apply SAFE location backfill from LOCATION_BACKFILL_REVIEW.json
 *
 * Usage:
 *   php scripts/apply-safe-location-backfill.php --dry-run
 *   php scripts/apply-safe-location-backfill.php --apply
 */

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

const REVIEW_JSON = __DIR__.'/../LOCATION_BACKFILL_REVIEW.json';
const SNAPSHOT_DIR = __DIR__.'/../storage/backfill-snapshots';

$dryRun = in_array('--dry-run', $argv, true);
$apply = in_array('--apply', $argv, true);

if ($dryRun === $apply) {
    fwrite(STDERR, "Specify exactly one mode: --dry-run or --apply\n");
    exit(1);
}

if (! is_file(REVIEW_JSON)) {
    fwrite(STDERR, 'Missing review file: '.REVIEW_JSON."\n");
    exit(1);
}

/** @var array<string, mixed> $review */
$review = json_decode((string) file_get_contents(REVIEW_JSON), true, 512, JSON_THROW_ON_ERROR);

/** @var list<array<string, mixed>> $proposals */
$proposals = $review['proposals'] ?? [];

$safeTargets = array_values(array_filter(
    $proposals,
    static fn (array $row): bool => ($row['verdict'] ?? '') === 'SAFE',
));

$expectedCount = (int) ($review['verdict_counts']['SAFE'] ?? 10);

if (count($safeTargets) !== $expectedCount) {
    fwrite(STDERR, 'ABORT: SAFE target count mismatch. Expected '.$expectedCount.', found '.count($safeTargets)."\n");
    exit(1);
}

$targetIds = array_map(static fn (array $row): int => (int) $row['job_id'], $safeTargets);

$rows = DB::table('jobs')
    ->whereIn('id', $targetIds)
    ->orderBy('id')
    ->get(['id', 'title', 'city', 'country', 'work_type', 'source', 'status']);

if ($rows->count() !== $expectedCount) {
    fwrite(STDERR, 'ABORT: Expected '.$expectedCount.' jobs in DB, found '.$rows->count()."\n");
    exit(1);
}

$plan = [];
$alreadyApplied = [];
$invalid = [];

foreach ($safeTargets as $target) {
    $jobId = (int) $target['job_id'];
    $row = $rows->firstWhere('id', $jobId);

    if ($row === null) {
        $invalid[] = ['job_id' => $jobId, 'reason' => 'Job not found'];
        continue;
    }

    /** @var array{city: ?string, country: ?string} $beforeExpected */
    $beforeExpected = $target['before'];
    /** @var array{city: ?string, country: ?string} $afterExpected */
    $afterExpected = $target['after'];

    $currentBefore = [
        'city' => $row->city,
        'country' => $row->country,
    ];

    $matchesExpectedBefore = $currentBefore['city'] === $beforeExpected['city']
        && $currentBefore['country'] === $beforeExpected['country'];

    $matchesExpectedAfter = $currentBefore['city'] === $afterExpected['city']
        && $currentBefore['country'] === $afterExpected['country'];

    if ($matchesExpectedAfter) {
        $alreadyApplied[] = [
            'job_id' => $jobId,
            'title' => $row->title,
            'city' => $row->city,
            'country' => $row->country,
        ];
        continue;
    }

    if (! $matchesExpectedBefore) {
        $invalid[] = [
            'job_id' => $jobId,
            'title' => $row->title,
            'reason' => 'Current state does not match review BEFORE or AFTER',
            'current' => $currentBefore,
            'expected_before' => $beforeExpected,
            'expected_after' => $afterExpected,
        ];
        continue;
    }

    $plan[] = [
        'job_id' => $jobId,
        'title' => $row->title,
        'provider' => $target['provider'] ?? null,
        'company' => $target['company'] ?? null,
        'before' => $currentBefore,
        'after' => $afterExpected,
        'work_type' => $row->work_type,
    ];
}

if ($invalid !== []) {
    fwrite(STDERR, "ABORT: Invalid target state detected.\n");
    echo json_encode(['invalid' => $invalid], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n";
    exit(1);
}

$timestamp = Carbon::now()->format('Y-m-d_His');
$report = [
    'mode' => $dryRun ? 'dry-run' : 'apply',
    'generated_at' => Carbon::now()->toIso8601String(),
    'expected_safe_targets' => $expectedCount,
    'target_job_ids' => $targetIds,
    'to_update' => count($plan),
    'already_applied' => count($alreadyApplied),
    'updates' => $plan,
    'skipped_already_applied' => $alreadyApplied,
];

if (! is_dir(SNAPSHOT_DIR)) {
    mkdir(SNAPSHOT_DIR, 0755, true);
}

$snapshotPath = SNAPSHOT_DIR.'/safe-location-backfill-'.$timestamp.($dryRun ? '-dry-run' : '-apply').'.json';
file_put_contents($snapshotPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo "=== SAFE Location Backfill ===\n";
echo 'Mode: '.($dryRun ? 'DRY-RUN' : 'APPLY')."\n";
echo 'Expected SAFE targets: '.$expectedCount."\n";
echo 'Target IDs: '.implode(', ', $targetIds)."\n";
echo 'Will update: '.count($plan)."\n";
echo 'Already applied (no-op): '.count($alreadyApplied)."\n";
echo 'Snapshot/report: '.$snapshotPath."\n\n";

foreach ($plan as $update) {
    echo sprintf(
        "#%d %s\n  before: city=%s country=%s\n  after:  city=%s country=%s\n\n",
        $update['job_id'],
        $update['title'],
        var_export($update['before']['city'], true),
        var_export($update['before']['country'], true),
        var_export($update['after']['city'], true),
        var_export($update['after']['country'], true),
    );
}

if ($dryRun) {
    echo "Dry-run complete. No database changes made.\n";
    exit(0);
}

if ($plan === []) {
    echo "Nothing to update. Idempotent no-op.\n";
    exit(0);
}

DB::transaction(function () use ($plan): void {
    foreach ($plan as $update) {
        $affected = DB::table('jobs')
            ->where('id', $update['job_id'])
            ->whereNull('city')
            ->where('country', 'Istanbul')
            ->update([
                'city' => $update['after']['city'],
                'country' => $update['after']['country'],
            ]);

        if ($affected !== 1) {
            throw new RuntimeException(
                'ABORT: Expected to update exactly 1 row for job #'.$update['job_id'].', affected '.$affected
            );
        }
    }
});

$verified = [];

foreach ($plan as $update) {
    $row = DB::table('jobs')->where('id', $update['job_id'])->first(['id', 'city', 'country']);

    if ($row === null
        || $row->city !== $update['after']['city']
        || $row->country !== $update['after']['country']) {
        throw new RuntimeException('Verification failed for job #'.$update['job_id']);
    }

    $verified[] = [
        'job_id' => $update['job_id'],
        'city' => $row->city,
        'country' => $row->country,
    ];
}

$verificationPath = SNAPSHOT_DIR.'/safe-location-backfill-'.$timestamp.'-verification.json';
file_put_contents($verificationPath, json_encode([
    'verified_at' => Carbon::now()->toIso8601String(),
    'updated_count' => count($verified),
    'verified' => $verified,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo 'Applied '.count($verified)." updates successfully.\n";
echo 'Verification report: '.$verificationPath."\n";

exit(0);

<?php

declare(strict_types=1);

/**
 * Apply targeted description entity cleanup from DESCRIPTION_BACKFILL_REVIEW.json
 * Uses EntityOnlyDescriptionCleanupService — preserves newlines/paragraph structure.
 *
 * Usage:
 *   php scripts/apply-description-entity-cleanup.php            (defaults to dry-run)
 *   php scripts/apply-description-entity-cleanup.php --dry-run
 *   php scripts/apply-description-entity-cleanup.php --apply
 */

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\Scraper\EntityOnlyDescriptionCleanupService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

const REVIEW_JSON = __DIR__.'/../DESCRIPTION_BACKFILL_REVIEW.json';
const SNAPSHOT_DIR = __DIR__.'/../storage/backfill-snapshots';
const HIGHLIGHT_JOB_IDS = [34, 38, 17];

$apply = in_array('--apply', $argv, true);
$dryRun = ! $apply;

if (! is_file(REVIEW_JSON)) {
    fwrite(STDERR, 'Missing review file: '.REVIEW_JSON."\n");
    exit(1);
}

/** @var array<string, mixed> $review */
$review = json_decode((string) file_get_contents(REVIEW_JSON), true, 512, JSON_THROW_ON_ERROR);

$expectedCount = (int) ($review['metrics']['entity_decoding'] ?? 0);

/** @var array<string, array<string, mixed>> $fullRecords */
$fullRecords = $review['full_records_by_job_id'] ?? [];

$entityTargets = [];

foreach ($fullRecords as $record) {
    if (($record['primary_category'] ?? '') !== 'entity_decoding') {
        continue;
    }

    if (($record['risky'] ?? false) === true) {
        continue;
    }

    $entityTargets[] = $record;
}

if (count($entityTargets) !== $expectedCount) {
    fwrite(STDERR, 'ABORT: Entity target count mismatch. Expected '.$expectedCount.', found '.count($entityTargets)."\n");
    exit(1);
}

usort($entityTargets, static fn (array $a, array $b): int => ($a['job_id'] ?? 0) <=> ($b['job_id'] ?? 0));

$targetIds = array_map(static fn (array $row): int => (int) $row['job_id'], $entityTargets);

$rows = DB::table('jobs')
    ->whereIn('id', $targetIds)
    ->orderBy('id')
    ->get(['id', 'title', 'description', 'source', 'status']);

if ($rows->count() !== $expectedCount) {
    fwrite(STDERR, 'ABORT: Expected '.$expectedCount.' jobs in DB, found '.$rows->count()."\n");
    exit(1);
}

$cleanup = new EntityOnlyDescriptionCleanupService;

/**
 * @return list<string>
 */
function detectEntityChanges(string $before, string $after): array
{
    $changes = [];

    if (str_contains($before, '&nbsp;') || str_contains($before, "\xc2\xa0")) {
        $changes[] = 'nbsp_to_space';
    }

    if (html_entity_decode($before, ENT_QUOTES | ENT_HTML5, 'UTF-8') !== $before) {
        $changes[] = 'html_entity_decode';
    }

    return $changes;
}

function hasEntityArtifact(string $text): bool
{
    if (str_contains($text, '&nbsp;') || str_contains($text, "\xc2\xa0")) {
        return true;
    }

    return preg_match('/&(?:[a-zA-Z]+|#\d+|#x[0-9a-fA-F]+);/', $text) === 1;
}

function newlineCount(string $text): int
{
    return substr_count($text, "\n");
}

$plan = [];
$alreadyApplied = [];
$invalid = [];

foreach ($entityTargets as $target) {
    $jobId = (int) $target['job_id'];
    $row = $rows->firstWhere('id', $jobId);

    if ($row === null) {
        $invalid[] = ['job_id' => $jobId, 'reason' => 'Job not found'];
        continue;
    }

    $reviewBefore = (string) ($target['before'] ?? '');
    $current = (string) ($row->description ?? '');
    $proposed = $cleanup->normalize($current);

    if ($current !== $reviewBefore && ! hasEntityArtifact($current)) {
        $alreadyApplied[] = [
            'job_id' => $jobId,
            'title' => $row->title,
            'note' => 'Current description already entity-clean (no artifacts)',
        ];
        continue;
    }

    if ($current !== $reviewBefore && hasEntityArtifact($current)) {
        $invalid[] = [
            'job_id' => $jobId,
            'title' => $row->title,
            'reason' => 'Current description differs from review BEFORE but still has entity artifacts',
        ];
        continue;
    }

    if ($current !== $reviewBefore) {
        $invalid[] = [
            'job_id' => $jobId,
            'title' => $row->title,
            'reason' => 'Current description does not match review BEFORE',
        ];
        continue;
    }

    if (! hasEntityArtifact($current)) {
        $alreadyApplied[] = [
            'job_id' => $jobId,
            'title' => $row->title,
            'note' => 'No entity artifacts present',
        ];
        continue;
    }

    if (newlineCount($current) !== newlineCount($proposed)) {
        $invalid[] = [
            'job_id' => $jobId,
            'title' => $row->title,
            'reason' => 'Entity-only normalization changed newline count',
            'newlines_before' => newlineCount($current),
            'newlines_after' => newlineCount($proposed),
        ];
        continue;
    }

    if ($current === $proposed) {
        $alreadyApplied[] = [
            'job_id' => $jobId,
            'title' => $row->title,
            'note' => 'Already at entity-only normalized state',
        ];
        continue;
    }

    $plan[] = [
        'job_id' => $jobId,
        'title' => $row->title,
        'provider' => $target['provider'] ?? null,
        'company' => $target['company'] ?? null,
        'before' => $current,
        'after' => $proposed,
        'changes' => detectEntityChanges($current, $proposed),
        'before_length' => mb_strlen($current),
        'after_length' => mb_strlen($proposed),
        'newlines_before' => newlineCount($current),
        'newlines_after' => newlineCount($proposed),
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
    'service' => EntityOnlyDescriptionCleanupService::class,
    'generated_at' => Carbon::now()->toIso8601String(),
    'expected_entity_targets' => $expectedCount,
    'target_job_ids' => $targetIds,
    'to_update' => count($plan),
    'already_applied' => count($alreadyApplied),
    'newline_preservation_failures' => 0,
    'updates' => $plan,
    'skipped_already_applied' => $alreadyApplied,
    'highlight_jobs' => array_values(array_filter(
        $plan,
        static fn (array $row): bool => in_array($row['job_id'], HIGHLIGHT_JOB_IDS, true),
    )),
];

if (! is_dir(SNAPSHOT_DIR)) {
    mkdir(SNAPSHOT_DIR, 0755, true);
}

$snapshotPath = SNAPSHOT_DIR.'/description-entity-cleanup-'.$timestamp.($dryRun ? '-dry-run' : '-apply').'.json';
file_put_contents($snapshotPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo "=== Description Entity Cleanup (Entity-Only) ===\n";
echo 'Mode: '.($dryRun ? 'DRY-RUN' : 'APPLY')."\n";
echo 'Service: EntityOnlyDescriptionCleanupService'."\n";
echo 'Expected entity targets: '.$expectedCount."\n";
echo 'Will update: '.count($plan)."\n";
echo 'Already applied (no-op): '.count($alreadyApplied)."\n";
echo 'Invalid: 0'."\n";
echo 'Snapshot/report: '.$snapshotPath."\n\n";

foreach (HIGHLIGHT_JOB_IDS as $highlightId) {
    $match = null;

    foreach ($plan as $update) {
        if ($update['job_id'] === $highlightId) {
            $match = $update;
            break;
        }
    }

    if ($match === null) {
        echo "#{$highlightId} not in update plan (may be already clean)\n\n";
        continue;
    }

    echo sprintf(
        "Highlight #%d [%s] %s\n  length: %d -> %d\n  newlines: %d -> %d\n  changes: %s\n\n",
        $match['job_id'],
        $match['provider'],
        $match['title'],
        $match['before_length'],
        $match['after_length'],
        $match['newlines_before'],
        $match['newlines_after'],
        implode(', ', $match['changes']),
    );
}

foreach (array_slice($plan, 0, 5) as $update) {
    if (in_array($update['job_id'], HIGHLIGHT_JOB_IDS, true)) {
        continue;
    }

    echo sprintf(
        "#%d [%s] %s — length %d -> %d, newlines %d -> %d\n",
        $update['job_id'],
        $update['provider'],
        $update['title'],
        $update['before_length'],
        $update['after_length'],
        $update['newlines_before'],
        $update['newlines_after'],
    );
}

if ($dryRun) {
    echo "\nDry-run complete. No database changes made.\n";
    exit(0);
}

if ($plan === []) {
    echo "Nothing to update. Idempotent no-op.\n";
    exit(0);
}

DB::transaction(function () use ($plan, $entityTargets, $cleanup): void {
    foreach ($plan as $update) {
        $reviewBefore = null;

        foreach ($entityTargets as $target) {
            if ((int) $target['job_id'] === $update['job_id']) {
                $reviewBefore = (string) ($target['before'] ?? '');
                break;
            }
        }

        if ($reviewBefore === null) {
            throw new RuntimeException('Missing review BEFORE for job #'.$update['job_id']);
        }

        $affected = DB::table('jobs')
            ->where('id', $update['job_id'])
            ->where('description', $reviewBefore)
            ->update(['description' => $update['after']]);

        if ($affected !== 1) {
            throw new RuntimeException(
                'ABORT: Expected to update exactly 1 row for job #'.$update['job_id'].', affected '.$affected
            );
        }

        $verified = DB::table('jobs')->where('id', $update['job_id'])->value('description');

        if ($verified === null || newlineCount((string) $verified) !== $update['newlines_before']) {
            throw new RuntimeException('Newline preservation verification failed for job #'.$update['job_id']);
        }
    }
});

$verificationPath = SNAPSHOT_DIR.'/description-entity-cleanup-'.$timestamp.'-verification.json';
file_put_contents($verificationPath, json_encode([
    'verified_at' => Carbon::now()->toIso8601String(),
    'updated_count' => count($plan),
    'job_ids' => array_column($plan, 'job_id'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo 'Applied '.count($plan)." updates successfully.\n";
echo 'Verification report: '.$verificationPath."\n";

exit(0);

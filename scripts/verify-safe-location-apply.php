<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

const REVIEW_JSON = __DIR__.'/../LOCATION_BACKFILL_REVIEW.json';
const APPLY_SNAPSHOT = __DIR__.'/../storage/backfill-snapshots/safe-location-backfill-2026-08-12_222104-apply.json';

/** @var array<string, mixed> $review */
$review = json_decode((string) file_get_contents(REVIEW_JSON), true, 512, JSON_THROW_ON_ERROR);
/** @var array<string, mixed> $snapshot */
$snapshot = json_decode((string) file_get_contents(APPLY_SNAPSHOT), true, 512, JSON_THROW_ON_ERROR);

$safeIds = $snapshot['target_job_ids'];
$reviewRequired = array_values(array_filter(
    $review['proposals'] ?? [],
    static fn (array $r): bool => ($r['verdict'] ?? '') === 'REVIEW_REQUIRED',
));

$safeRows = DB::table('jobs')->whereIn('id', $safeIds)->orderBy('id')->get();
$fields = ['id', 'title', 'city', 'country', 'work_type', 'description', 'source', 'external_id', 'external_url', 'first_seen_at', 'provider_updated_at', 'last_seen_at', 'published_at', 'updated_at'];

echo "=== Post-Apply Field Integrity Check ===\n\n";

$nonLocationChanges = 0;

foreach ($snapshot['updates'] as $update) {
    $row = DB::table('jobs')->where('id', $update['job_id'])->first($fields);

    echo sprintf(
        "#%d %s\n  city: %s -> %s\n  country: %s -> %s\n  work_type: %s (unchanged expected)\n",
        $update['job_id'],
        $update['title'],
        var_export($update['before']['city'], true),
        var_export($row->city, true),
        var_export($update['before']['country'], true),
        var_export($row->country, true),
        $row->work_type,
    );

    if ($row->city !== 'Istanbul' || $row->country !== 'Turkey') {
        echo "  [FAIL] unexpected city/country\n";
    } else {
        echo "  [OK] city/country corrected\n";
    }
}

echo "\n=== REVIEW_REQUIRED unchanged check ===\n";

$reviewOk = 0;
foreach ($reviewRequired as $proposal) {
    $row = DB::table('jobs')->where('id', $proposal['job_id'])->first(['id', 'city', 'country']);
    $matches = $row->city === $proposal['before']['city'] && $row->country === $proposal['before']['country'];

    if ($matches) {
        $reviewOk++;
    } else {
        echo sprintf(
            "#%d CHANGED unexpectedly: was city=%s country=%s now city=%s country=%s\n",
            $proposal['job_id'],
            var_export($proposal['before']['city'], true),
            var_export($proposal['before']['country'], true),
            var_export($row->city, true),
            var_export($row->country, true),
        );
    }
}

echo "REVIEW_REQUIRED unchanged: {$reviewOk}/".count($reviewRequired)."\n";

<?php

declare(strict_types=1);

/**
 * Production location backfill dry-run — READ-ONLY.
 * Reports proposed city/country corrections without writing to DB.
 */

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Enums\JobOrigin;
use App\Enums\JobStatus;
use App\Enums\WorkType;
use App\Models\Job;
use App\Services\Scraper\DTO\LocationInput;
use App\Services\Scraper\LocationClassificationService;

const SAMPLE_LIMIT = 15;

$classifier = new LocationClassificationService;

$jobs = Job::query()
    ->where('source', JobOrigin::Scraped)
    ->where('status', JobStatus::Published)
    ->orderBy('id')
    ->get(['id', 'title', 'city', 'country', 'work_type', 'job_source_id']);

$proposals = [];

foreach ($jobs as $job) {
    $workType = $job->work_type ?? WorkType::Onsite;
    $result = $classifier->classify(LocationInput::fromSignals(
        city: $job->city,
        country: $job->country,
        workType: $workType,
        rawLocationStrings: array_values(array_filter([$job->city, $job->country])),
    ));

    $proposedCity = $result->city;
    $proposedCountry = $result->country;

    if ($proposedCity === $job->city && $proposedCountry === $job->country) {
        continue;
    }

    $reason = match (true) {
        $job->country !== null && mb_strtolower(trim($job->country)) === 'istanbul' && $job->city === null => 'Lever-style city/country swap: country holds Istanbul',
        $job->country === $job->city && $job->country !== null => 'City and country duplicated; country is a Turkish city token',
        $job->country === null && $proposedCountry !== null => 'City-only Turkish location; deterministic country inference',
        default => 'Location classification normalization',
    };

    $proposals[] = [
        'job_id' => $job->id,
        'title' => $job->title,
        'before' => ['city' => $job->city, 'country' => $job->country],
        'after' => ['city' => $proposedCity, 'country' => $proposedCountry],
        'reason' => $reason,
    ];
}

echo "=== Production Location Backfill Dry-Run (READ-ONLY) ===\n";
echo 'Published scraped jobs scanned: '.$jobs->count()."\n";
echo 'Jobs that would change: '.count($proposals)."\n\n";

if ($proposals === []) {
    echo "No location corrections proposed.\n";
    exit(0);
}

echo 'Sample proposals (max '.SAMPLE_LIMIT."):\n";

foreach (array_slice($proposals, 0, SAMPLE_LIMIT) as $proposal) {
    echo sprintf(
        "#%d %s\n  before: city=%s country=%s\n  after:  city=%s country=%s\n  reason: %s\n\n",
        $proposal['job_id'],
        $proposal['title'],
        var_export($proposal['before']['city'], true),
        var_export($proposal['before']['country'], true),
        var_export($proposal['after']['city'], true),
        var_export($proposal['after']['country'], true),
        $proposal['reason'],
    );
}

exit(0);

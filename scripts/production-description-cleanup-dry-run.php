<?php

declare(strict_types=1);

/**
 * Production description cleanup dry-run — READ-ONLY.
 * Reports HTML entity artifacts and proposed normalized descriptions.
 */

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Enums\JobOrigin;
use App\Enums\JobStatus;
use App\Models\Job;
use App\Models\JobSource;
use App\Services\Scraper\DescriptionNormalizerService;

const SAMPLE_LIMIT = 10;
const ARTIFACT_PATTERNS = ['&nbsp;', '&amp;', '&lt;', '&gt;', '&#'];

$normalizer = new DescriptionNormalizerService;

$sourceProviders = JobSource::query()
    ->get(['id', 'name', 'config'])
    ->mapWithKeys(fn (JobSource $source): array => [
        $source->id => (string) ($source->config['provider'] ?? 'unknown'),
    ]);

$jobs = Job::query()
    ->where('source', JobOrigin::Scraped)
    ->where('status', JobStatus::Published)
    ->orderBy('id')
    ->get(['id', 'title', 'description', 'job_source_id']);

$affected = [];

foreach ($jobs as $job) {
    $description = (string) ($job->description ?? '');
    $hasArtifact = false;

    foreach (ARTIFACT_PATTERNS as $pattern) {
        if (str_contains($description, $pattern)) {
            $hasArtifact = true;
            break;
        }
    }

    $normalized = $normalizer->normalize($description);

    if (! $hasArtifact && $normalized === $description) {
        continue;
    }

    if ($normalized === $description) {
        continue;
    }

    $affected[] = [
        'job_id' => $job->id,
        'title' => $job->title,
        'provider' => $sourceProviders[$job->job_source_id] ?? 'unknown',
        'before' => mb_substr($description, 0, 180),
        'after' => mb_substr($normalized, 0, 180),
    ];
}

echo "=== Production Description Cleanup Dry-Run (READ-ONLY) ===\n";
echo 'Published scraped jobs scanned: '.$jobs->count()."\n";
echo 'Jobs that would change: '.count($affected)."\n\n";

if ($affected === []) {
    echo "No description changes proposed.\n";
    exit(0);
}

$byProvider = [];

foreach ($affected as $row) {
    $byProvider[$row['provider']] = ($byProvider[$row['provider']] ?? 0) + 1;
}

echo "Affected by provider:\n";

foreach ($byProvider as $provider => $count) {
    echo "  {$provider}: {$count}\n";
}

echo "\nSample before/after (max ".SAMPLE_LIMIT."):\n";

foreach (array_slice($affected, 0, SAMPLE_LIMIT) as $row) {
    echo sprintf(
        "#%d [%s] %s\n  before: %s\n  after:  %s\n\n",
        $row['job_id'],
        $row['provider'],
        $row['title'],
        $row['before'],
        $row['after'],
    );
}

exit(0);

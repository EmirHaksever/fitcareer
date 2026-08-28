<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Enums\JobOrigin;
use App\Enums\JobStatus;
use App\Models\Job;
use App\Models\JobSource;

$publishedScraped = Job::query()
    ->where('source', JobOrigin::Scraped)
    ->where('status', JobStatus::Published);

echo "=== Ghost Tracking Production Stats ===\n";
echo 'Published scraped jobs: '.(clone $publishedScraped)->count()."\n";
echo 'With first_seen_at set: '.(clone $publishedScraped)->whereNotNull('first_seen_at')->count()."\n";
echo 'With provider_updated_at set: '.(clone $publishedScraped)->whereNotNull('provider_updated_at')->count()."\n\n";

echo "Sample jobs WITH provider_updated_at (up to 5):\n";
$withProvider = (clone $publishedScraped)
    ->whereNotNull('provider_updated_at')
    ->orderByDesc('provider_updated_at')
    ->limit(5)
    ->get(['id', 'title', 'job_source_id', 'first_seen_at', 'provider_updated_at', 'published_at']);

if ($withProvider->isEmpty()) {
    echo "  (none yet — providers must re-import to populate)\n";
} else {
    foreach ($withProvider as $job) {
        echo sprintf(
            "  #%d %s | first_seen=%s provider_updated=%s published=%s\n",
            $job->id,
            $job->title,
            $job->first_seen_at?->toDateString() ?? 'NULL',
            $job->provider_updated_at?->toDateString() ?? 'NULL',
            $job->published_at?->toDateString() ?? 'NULL',
        );
    }
}

echo "\nSample jobs WITH first_seen_at (up to 5):\n";
$withFirstSeen = (clone $publishedScraped)
    ->whereNotNull('first_seen_at')
    ->orderByDesc('first_seen_at')
    ->limit(5)
    ->get(['id', 'title', 'job_source_id', 'first_seen_at', 'provider_updated_at']);

if ($withFirstSeen->isEmpty()) {
    echo "  (none yet — only set on newly created imports post-migration)\n";
} else {
    foreach ($withFirstSeen as $job) {
        echo sprintf(
            "  #%d %s | first_seen=%s provider_updated=%s\n",
            $job->id,
            $job->title,
            $job->first_seen_at?->toDateString() ?? 'NULL',
            $job->provider_updated_at?->toDateString() ?? 'NULL',
        );
    }
}

echo "\nRecent Wingie jobs (all 8) ghost fields:\n";
$wingie = JobSource::query()->where('name', 'Wingie Enuygun')->first();
if ($wingie) {
    foreach (Job::query()->where('job_source_id', $wingie->id)->orderBy('id')->get(['id','title','first_seen_at','provider_updated_at','last_seen_at']) as $job) {
        echo sprintf(
            "  #%d %s | first_seen=%s provider_updated=%s last_seen=%s\n",
            $job->id,
            $job->title,
            $job->first_seen_at?->toIso8601String() ?? 'NULL',
            $job->provider_updated_at?->toIso8601String() ?? 'NULL',
            $job->last_seen_at?->toIso8601String() ?? 'NULL',
        );
    }
}

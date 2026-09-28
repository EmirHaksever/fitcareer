<?php

declare(strict_types=1);

namespace App\Services\Job;

use App\Enums\JobStatus;
use App\Models\Job;
use App\Models\JobSource;
use App\Services\Scraper\LocationClassificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

class PublicJobStatsService
{
    private const CACHE_KEY = 'public-job-stats';

    private const CACHE_SECONDS = 300;

    public function __construct(
        private readonly LocationClassificationService $locationClassifier,
    ) {}

    /**
     * Catalog-wide counts for the public landing page. Uses the same visibility
     * rules as the default job search so "published_jobs" equals the list total.
     *
     * @return array{published_jobs: int, trust_analyzed_jobs: int, active_sources: int}
     */
    public function get(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_SECONDS, fn (): array => [
            'published_jobs' => $this->visibleJobs()->count(),
            'trust_analyzed_jobs' => $this->visibleJobs()->whereNotNull('jobs.trust_score')->count(),
            'active_sources' => JobSource::query()->where('is_active', true)->count(),
        ]);
    }

    /**
     * @return Builder<Job>
     */
    private function visibleJobs(): Builder
    {
        $builder = Job::query()
            ->where('jobs.status', JobStatus::Published)
            ->where(function (Builder $visibilityQuery): void {
                $visibilityQuery
                    ->whereNull('jobs.expires_at')
                    ->orWhere('jobs.expires_at', '>', now());
            });

        $this->locationClassifier->applyTurkeyRelevantScope($builder);

        return $builder;
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Scraper;

use App\Enums\JobOrigin;
use App\Enums\JobStatus;
use App\Enums\ScrapeStatus;
use App\Models\Job;
use App\Models\JobSource;
use Illuminate\Support\Carbon;

class ScrapedJobFreshnessService
{
    /**
     * @return array{stale: int, expired: int}
     */
    public function applyLifecycle(JobSource $source): array
    {
        $staleAfterHours = (int) ($source->config['stale_after_hours'] ?? config('scraper.stale_after_hours'));
        $expireAfterHours = (int) ($source->config['expire_after_hours'] ?? config('scraper.expire_after_hours'));

        $staleThreshold = Carbon::now()->subHours($staleAfterHours);
        $expireThreshold = Carbon::now()->subHours($expireAfterHours);

        $staleCount = Job::query()
            ->where('job_source_id', $source->id)
            ->where('source', JobOrigin::Scraped)
            ->where('status', JobStatus::Published)
            ->where('scrape_status', ScrapeStatus::Success)
            ->where(function ($query) use ($staleThreshold): void {
                $query->whereNull('last_seen_at')
                    ->orWhere('last_seen_at', '<', $staleThreshold);
            })
            ->update([
                'scrape_status' => ScrapeStatus::Stale,
            ]);

        $expiredCount = Job::query()
            ->where('job_source_id', $source->id)
            ->where('source', JobOrigin::Scraped)
            ->where('status', JobStatus::Published)
            ->where('scrape_status', ScrapeStatus::Stale)
            ->where(function ($query) use ($expireThreshold): void {
                $query->whereNull('last_seen_at')
                    ->orWhere('last_seen_at', '<', $expireThreshold);
            })
            ->update([
                'status' => JobStatus::Expired,
            ]);

        return [
            'stale' => $staleCount,
            'expired' => $expiredCount,
        ];
    }
}

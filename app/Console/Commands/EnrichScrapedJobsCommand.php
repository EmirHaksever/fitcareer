<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\JobOrigin;
use App\Models\Job;
use App\Services\Scraper\ScrapedJobEnrichmentService;
use Illuminate\Console\Command;

class EnrichScrapedJobsCommand extends Command
{
    protected $signature = 'jobs:enrich-scraped {--limit=100 : Maximum jobs to enrich}';

    protected $description = 'Extract skills and run trust analysis for scraped jobs.';

    public function handle(ScrapedJobEnrichmentService $enrichmentService): int
    {
        $limit = max(1, (int) $this->option('limit'));

        $jobs = Job::query()
            ->where('source', JobOrigin::Scraped)
            ->orderByDesc('last_scraped_at')
            ->limit($limit)
            ->get();

        if ($jobs->isEmpty()) {
            $this->warn('No scraped jobs found.');

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($jobs->count());
        $bar->start();

        foreach ($jobs as $job) {
            $enrichmentService->enrich($job);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info('Enriched '.$jobs->count().' scraped jobs.');

        return self::SUCCESS;
    }
}

<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\JobOrigin;
use App\Enums\JobSourceType;
use App\Enums\JobStatus;
use App\Exceptions\ScraperFetchException;
use App\Models\Job;
use App\Models\JobSource;
use App\Services\Scraper\JobIngestionService;
use App\Services\Scraper\ScraperClientService;
use Illuminate\Console\Command;

class TestJobIngestionCommand extends Command
{
    protected $signature = 'jobs:test-ingestion {--source=remotive : Job source name or provider key}';

    protected $description = 'Fetch up to 10 real listings from a configured job source and upsert them into the database.';

    public function handle(
        ScraperClientService $scraperClientService,
        JobIngestionService $jobIngestionService,
    ): int {
        $sourceKey = strtolower(trim((string) $this->option('source')));

        if ($sourceKey === '') {
            $this->error('SOURCE FAILED');
            $this->line('Source key is required.');

            return self::FAILURE;
        }

        $source = $this->resolveSource($sourceKey);

        if ($source === null) {
            $this->error('SOURCE FAILED');
            $this->line('Job source not found for: '.$sourceKey);

            return self::FAILURE;
        }

        $this->line('SOURCE: '.$source->name);

        try {
            $listings = $scraperClientService->fetchListings($source);
        } catch (ScraperFetchException $exception) {
            $this->error('SOURCE FAILED');
            $this->line($exception->getMessage());

            return self::FAILURE;
        }

        $fetched = count($listings);
        $normalized = 0;
        $created = 0;
        $updated = 0;
        $failed = 0;
        $preview = [];

        foreach ($listings as $index => $rawListing) {
            try {
                $result = $jobIngestionService->ingest($source, $rawListing);
                $normalized++;
                $result['created'] ? $created++ : $updated++;

                if (count($preview) < 10) {
                    $job = $result['job'];
                    $preview[] = [
                        'number' => $index + 1,
                        'title' => $job->title,
                        'company' => $job->source_company_name,
                        'location' => trim(($job->city ?? '').($job->country ? ', '.$job->country : '')),
                        'external_id' => $job->external_id,
                        'url' => $job->external_url,
                    ];
                }
            } catch (\Throwable $exception) {
                $failed++;
                $this->warn('Failed listing #'.($index + 1).': '.$exception->getMessage());
            }
        }

        $this->line('');
        $this->line('Fetched: '.$fetched);
        $this->line('Normalized: '.$normalized);
        $this->line('Created: '.$created);
        $this->line('Updated: '.$updated);
        $this->line('Failed: '.$failed);

        foreach ($preview as $item) {
            $this->line('');
            $this->line('['.$item['number'].']');
            $this->line('✓ title: '.$item['title']);
            $this->line('✓ company: '.($item['company'] ?? 'n/a'));
            $this->line('✓ external_id: '.$item['external_id']);
            $this->line('✓ URL: '.($item['url'] ?? 'n/a'));
            if (($item['location'] ?? '') !== '') {
                $this->line('  location: '.$item['location']);
            }
        }

        $dbCount = Job::query()
            ->where('job_source_id', $source->id)
            ->where('source', JobOrigin::Scraped)
            ->count();

        $this->line('');
        $this->line('DB scraped jobs for source: '.$dbCount);

        $publishedCount = Job::query()
            ->where('job_source_id', $source->id)
            ->where('source', JobOrigin::Scraped)
            ->where('status', JobStatus::Published)
            ->count();

        $this->line('Published scraped jobs for source: '.$publishedCount);

        return $failed > 0 && ($created + $updated) === 0 ? self::FAILURE : self::SUCCESS;
    }

    private function resolveSource(string $sourceKey): ?JobSource
    {
        $source = JobSource::query()
            ->where('is_active', true)
            ->whereRaw('LOWER(name) = ?', [$sourceKey])
            ->first();

        if ($source !== null) {
            return $source;
        }

        $source = JobSource::query()
            ->where('is_active', true)
            ->where('config->site_slug', $sourceKey)
            ->first();

        if ($source !== null) {
            return $source;
        }

        if ($sourceKey === 'remotive') {
            return JobSource::query()->create([
                'name' => 'Remotive',
                'base_url' => 'https://remotive.com/api/remote-jobs',
                'type' => JobSourceType::ApiIntegration,
                'is_active' => true,
                'config' => [
                    'provider' => 'remotive',
                    'limit' => 10,
                ],
            ]);
        }

        if ($sourceKey === 'kariyer-net') {
            $this->warn('Kariyer.net source not found. Run: php scripts/seed-kariyer-net-source.php');
        }

        return null;
    }
}

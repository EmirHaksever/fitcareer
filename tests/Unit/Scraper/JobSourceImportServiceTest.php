<?php

declare(strict_types=1);

namespace Tests\Unit\Scraper;

use App\Enums\ImportRunStatus;
use App\Enums\JobOrigin;
use App\Enums\JobSourceType;
use App\Enums\JobStatus;
use App\Enums\ScrapeStatus;
use App\Jobs\RunJobSourceImportJob;
use App\Models\Job;
use App\Models\JobImportRun;
use App\Models\JobSource;
use App\Services\Scraper\JobIngestionService;
use App\Services\Scraper\JobSourceImportService;
use App\Services\Scraper\ScrapedJobFreshnessService;
use App\Services\Scraper\ScraperClientService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class JobSourceImportServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_records_import_run_and_prevents_duplicates_on_second_import(): void
    {
        Http::fake([
            'remotive.com/*' => Http::response([
                'jobs' => [
                    [
                        'id' => 777,
                        'url' => 'https://remotive.com/remote-jobs/example-777',
                        'title' => 'Import Test Engineer',
                        'company_name' => 'Import Co',
                        'category' => 'Software Development',
                        'job_type' => 'full_time',
                        'publication_date' => '2026-08-01T10:00:00',
                        'candidate_required_location' => 'Worldwide',
                        'description' => '<p>Import test</p>',
                    ],
                ],
            ], 200),
        ]);

        $source = JobSource::factory()->create([
            'name' => 'Remotive Import Test',
            'base_url' => 'https://remotive.com/api/remote-jobs',
            'type' => JobSourceType::ApiIntegration,
            'config' => [
                'provider' => 'remotive',
                'max_listings' => 25,
            ],
        ]);

        Event::fake([\App\Events\JobImportCompleted::class]);

        $first = app(JobSourceImportService::class)->import($source);

        $this->assertSame(ImportRunStatus::Completed, $first['run']->status);
        $this->assertSame(1, $first['fetched']);
        $this->assertSame(1, $first['created']);
        $this->assertSame(0, $first['updated']);
        $this->assertNotNull($first['run']->finished_at);
        $this->assertDatabaseHas('job_import_runs', [
            'id' => $first['run']->id,
            'job_source_id' => $source->id,
            'items_found' => 1,
            'items_created' => 1,
        ]);

        $job = Job::query()->where('job_source_id', $source->id)->first();
        $this->assertNotNull($job?->last_seen_at);

        $second = app(JobSourceImportService::class)->import($source);

        $this->assertSame(1, $second['fetched']);
        $this->assertSame(0, $second['created']);
        $this->assertSame(1, $second['updated']);
        $this->assertSame(1, Job::query()->where('job_source_id', $source->id)->count());
    }

    #[Test]
    public function production_fetch_uses_pagination_limits_without_legacy_ten_cap(): void
    {
        $listingHtml = file_get_contents(base_path('tests/Fixtures/Scraper/kariyer-net-listing.html'));
        $detailHtmlOne = file_get_contents(base_path('tests/Fixtures/Scraper/kariyer-net-detail-4515714.html'));
        $detailHtmlTwo = file_get_contents(base_path('tests/Fixtures/Scraper/kariyer-net-detail-4477112.html'));

        Http::fake([
            'www.kariyer.net/is-ilanlari/yazilim*' => Http::response($listingHtml, 200),
            'www.kariyer.net/is-ilani/acme-yazilim-gelistirici-4515714' => Http::response($detailHtmlOne, 200),
            'www.kariyer.net/is-ilani/fonet-lider-yazilim-gelistirme-uzmani-full-stack-4477112' => Http::response($detailHtmlTwo, 200),
        ]);

        $source = JobSource::factory()->create([
            'type' => JobSourceType::Scraper,
            'config' => [
                'provider' => 'kariyer-net',
                'listing_url' => 'https://www.kariyer.net/is-ilanlari/yazilim',
                'limit' => 10,
                'max_listings' => 25,
                'max_pages' => 3,
                'page_size' => 25,
            ],
        ]);

        $legacy = app(ScraperClientService::class)->fetchListings($source);
        $production = app(ScraperClientService::class)->fetchListingsForImport($source);

        $this->assertCount(2, $legacy);
        $this->assertCount(2, $production);
        $this->assertGreaterThanOrEqual(2, count($production));
    }

    #[Test]
    public function import_job_can_be_dispatched_to_queue(): void
    {
        Queue::fake();

        $source = JobSource::factory()->create(['is_active' => true]);

        RunJobSourceImportJob::dispatch($source->id);

        Queue::assertPushed(RunJobSourceImportJob::class, function (RunJobSourceImportJob $job) use ($source): bool {
            return $job->jobSourceId === $source->id;
        });
    }

    #[Test]
    public function freshness_marks_stale_then_expires_jobs(): void
    {
        $source = JobSource::factory()->create([
            'config' => [
                'provider' => 'remotive',
                'stale_after_hours' => 24,
                'expire_after_hours' => 48,
            ],
        ]);

        $staleCandidate = Job::factory()->published()->scraped()->create([
            'job_source_id' => $source->id,
            'scrape_status' => ScrapeStatus::Success,
            'last_seen_at' => now()->subHours(30),
        ]);

        $expireCandidate = Job::factory()->published()->scraped()->create([
            'job_source_id' => $source->id,
            'scrape_status' => ScrapeStatus::Stale,
            'last_seen_at' => now()->subHours(72),
        ]);

        $fresh = Job::factory()->published()->scraped()->create([
            'job_source_id' => $source->id,
            'scrape_status' => ScrapeStatus::Success,
            'last_seen_at' => now(),
        ]);

        $result = app(ScrapedJobFreshnessService::class)->applyLifecycle($source);

        $this->assertSame(1, $result['stale']);
        $this->assertSame(1, $result['expired']);
        $this->assertSame(ScrapeStatus::Stale, $staleCandidate->fresh()->scrape_status);
        $this->assertSame(JobStatus::Expired, $expireCandidate->fresh()->status);
        $this->assertSame(ScrapeStatus::Success, $fresh->fresh()->scrape_status);
    }
}

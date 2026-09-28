<?php

declare(strict_types=1);

namespace Tests\Unit\Scraper;

use App\Enums\JobOrigin;
use App\Enums\JobSourceType;
use App\Enums\JobStatus;
use App\Models\Job;
use App\Models\JobSource;
use App\Services\Scraper\JobIngestionService;
use App\Services\Scraper\JobNormalizerService;
use App\Services\Scraper\ScraperClientService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class JobIngestionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_fetches_and_ingests_remotive_listings(): void
    {
        Http::fake([
            'remotive.com/*' => Http::response([
                'jobs' => [
                    [
                        'id' => 12345,
                        'url' => 'https://remotive.com/remote-jobs/software/dev-12345',
                        'title' => 'Senior Flutter Developer',
                        'company_name' => 'Acme Mobile',
                        'category' => 'Software Development',
                        'job_type' => 'full_time',
                        'publication_date' => '2026-08-01T10:00:00',
                        'candidate_required_location' => 'Worldwide',
                        'description' => '<p>Build Flutter apps with Dart.</p>',
                    ],
                ],
            ], 200),
        ]);

        $source = JobSource::factory()->create([
            'name' => 'Remotive Test',
            'base_url' => 'https://remotive.com/api/remote-jobs',
            'type' => JobSourceType::ApiIntegration,
            'config' => ['provider' => 'remotive', 'limit' => 10],
        ]);

        $listings = app(ScraperClientService::class)->fetchListings($source);
        $this->assertCount(1, $listings);

        $result = app(JobIngestionService::class)->ingest($source, $listings[0]);
        $this->assertTrue($result['created']);
        $this->assertSame('Senior Flutter Developer', $result['job']->title);
        $this->assertSame(JobOrigin::Scraped, $result['job']->source);
        $this->assertSame(JobStatus::Published, $result['job']->status);
        $this->assertSame('12345', $result['job']->external_id);

        $second = app(JobIngestionService::class)->ingest($source, $listings[0]);
        $this->assertFalse($second['created']);
        $this->assertSame($result['job']->id, $second['job']->id);
        $this->assertSame(1, Job::query()->where('job_source_id', $source->id)->count());
    }

    #[Test]
    public function normalizer_maps_remotive_fields(): void
    {
        $source = JobSource::factory()->create([
            'type' => JobSourceType::ApiIntegration,
            'config' => ['provider' => 'remotive'],
        ]);

        $normalized = app(JobNormalizerService::class)->normalize($source, [
            'id' => 99,
            'url' => 'https://remotive.com/remote-jobs/example-99',
            'title' => 'Backend Engineer',
            'company_name' => 'Example Co',
            'category' => 'Software Development',
            'job_type' => 'contract',
            'publication_date' => '2026-07-01T08:00:00',
            'candidate_required_location' => 'Berlin, Germany',
            'description' => '<p>API work</p>',
        ]);

        $this->assertSame('Example Co', $normalized['source_company_name']);
        $this->assertSame('Berlin', $normalized['city']);
        $this->assertSame('Germany', $normalized['country']);
        $this->assertSame('contract', $normalized['employment_type']->value);
        $this->assertNotEmpty($normalized['content_hash']);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Job;

use App\Enums\ImportRunStatus;
use App\Models\Job;
use App\Models\JobImportRun;
use App\Models\JobSource;
use App\Services\Scraper\JobSourceImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\LeverTestSourceFactory;
use Tests\TestCase;

class LeverImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_command_resolves_source_by_site_slug(): void
    {
        LeverTestSourceFactory::create('Commencis', 'commencis', [
            'page_size' => 100,
            'max_pages' => 1,
            'max_listings' => 10,
        ]);

        Http::fake([
            'https://api.lever.co/v0/postings/commencis*' => Http::response(
                LeverTestSourceFactory::loadFixture('commencis-single.json'),
                200,
            ),
        ]);

        $this->artisan('jobs:import-source', ['source' => 'commencis', '--sync' => true])
            ->assertExitCode(0);
    }

    public function test_full_import_smoke_test_creates_jobs_and_records_health(): void
    {
        $source = LeverTestSourceFactory::create('Commencis', 'commencis', [
            'page_size' => 100,
            'max_pages' => 1,
            'max_listings' => 10,
        ]);

        Http::fake([
            'https://api.lever.co/v0/postings/commencis*' => Http::response(
                LeverTestSourceFactory::loadFixture('commencis-single.json'),
                200,
            ),
        ]);

        $result = app(JobSourceImportService::class)->import($source);

        $this->assertSame(ImportRunStatus::Completed, $result['run']->status);
        $this->assertSame(1, $result['fetched']);
        $this->assertSame(1, $result['created']);
        $this->assertSame(0, $result['updated']);
        $this->assertSame(0, $result['failed']);

        $this->assertDatabaseHas('jobs', [
            'job_source_id' => $source->id,
            'external_id' => '7440425c-1adf-40da-b230-281fe4a3caaf',
            'title' => 'Senior AI Software Engineer',
            'source_company_name' => 'Commencis',
        ]);

        $source->refresh();

        $this->assertNotNull($source->last_success_at);
        $this->assertSame(0, $source->consecutive_failures);
        $this->assertSame(1, $source->last_items_found);
    }

    public function test_duplicate_external_id_is_updated_on_reimport(): void
    {
        $source = LeverTestSourceFactory::create('Commencis', 'commencis', [
            'page_size' => 100,
            'max_pages' => 1,
            'max_listings' => 10,
        ]);

        $fixture = LeverTestSourceFactory::loadFixture('commencis-single.json');

        Http::fake([
            'https://api.lever.co/v0/postings/commencis*' => Http::response($fixture, 200),
        ]);

        $service = app(JobSourceImportService::class);
        $first = $service->import($source);
        $second = $service->import($source);

        $this->assertSame(1, $first['created']);
        $this->assertSame(0, $first['updated']);
        $this->assertSame(0, $second['created']);
        $this->assertSame(1, $second['updated']);
        $this->assertSame(1, Job::query()->where('job_source_id', $source->id)->count());
    }

    public function test_stale_posting_is_counted_as_failed_during_import(): void
    {
        $source = LeverTestSourceFactory::create('Commencis', 'commencis', [
            'page_size' => 100,
            'max_pages' => 1,
            'max_listings' => 10,
            'max_posting_age_days' => 365,
        ]);

        Http::fake([
            'https://api.lever.co/v0/postings/commencis*' => Http::response(
                LeverTestSourceFactory::loadFixture('stale-posting.json'),
                200,
            ),
        ]);

        $result = app(JobSourceImportService::class)->import($source);

        $this->assertSame(ImportRunStatus::Failed, $result['run']->status);
        $this->assertSame(1, $result['fetched']);
        $this->assertSame(0, $result['created']);
        $this->assertSame(1, $result['failed']);
        $this->assertSame(0, Job::query()->where('job_source_id', $source->id)->count());
    }

    public function test_empty_board_import_records_failure(): void
    {
        $source = LeverTestSourceFactory::create('Commencis', 'commencis', [
            'page_size' => 100,
            'max_pages' => 1,
            'max_listings' => 10,
        ]);

        Http::fake([
            'https://api.lever.co/v0/postings/commencis*' => Http::response([], 200),
        ]);

        $result = app(JobSourceImportService::class)->import($source);

        $this->assertSame(ImportRunStatus::Failed, $result['run']->status);
        $this->assertSame(0, $result['fetched']);

        $source->refresh();
        $this->assertNotNull($source->last_failure_at);
        $this->assertGreaterThan(0, $source->consecutive_failures);

        $this->assertSame(1, JobImportRun::query()->where('job_source_id', $source->id)->count());
    }

    public function test_provider_key_alone_does_not_resolve_lever_source(): void
    {
        LeverTestSourceFactory::create('Commencis', 'commencis');
        LeverTestSourceFactory::create('Midas', 'getmidas');

        $this->artisan('jobs:import-source', ['source' => 'lever', '--sync' => true])
            ->assertExitCode(1);
    }
}

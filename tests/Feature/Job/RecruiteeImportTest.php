<?php

declare(strict_types=1);

namespace Tests\Feature\Job;

use App\Enums\ImportRunStatus;
use App\Enums\JobSourceType;
use App\Exceptions\ScraperFetchException;
use App\Models\Job;
use App\Models\JobImportRun;
use App\Models\JobSource;
use App\Services\Scraper\JobIngestionService;
use App\Services\Scraper\JobSourceImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\Support\RecruiteeTestSourceFactory;
use Tests\TestCase;

class RecruiteeImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_command_resolves_source_by_name(): void
    {
        RecruiteeTestSourceFactory::create('Mikro Yazılım', 'mikroyazilim');

        Http::fake([
            'https://mikroyazilim.recruitee.com/api/offers*' => Http::response(
                RecruiteeTestSourceFactory::loadFixture('mikroyazilim-single.json'),
                200,
            ),
        ]);

        $this->artisan('jobs:import-source', ['source' => 'Mikro Yazılım', '--sync' => true])
            ->assertExitCode(0);
    }

    public function test_import_command_resolves_source_by_site_slug(): void
    {
        RecruiteeTestSourceFactory::create('Mikro Yazılım', 'mikroyazilim');

        Http::fake([
            'https://mikroyazilim.recruitee.com/api/offers*' => Http::response(
                RecruiteeTestSourceFactory::loadFixture('mikroyazilim-single.json'),
                200,
            ),
        ]);

        $this->artisan('jobs:import-source', ['source' => 'mikroyazilim', '--sync' => true])
            ->assertExitCode(0);
    }

    public function test_full_import_smoke_test_creates_jobs_and_records_health(): void
    {
        $source = RecruiteeTestSourceFactory::create('Mikro Yazılım', 'mikroyazilim');

        Http::fake([
            'https://mikroyazilim.recruitee.com/api/offers*' => Http::response(
                RecruiteeTestSourceFactory::loadFixture('mikroyazilim-single.json'),
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
            'external_id' => '2706848',
            'title' => 'Senior Financial Analyst (FP&A)',
            'source_company_name' => 'Mikro Yazılım',
        ]);

        $source->refresh();

        $this->assertNotNull($source->last_success_at);
        $this->assertSame(0, $source->consecutive_failures);
        $this->assertSame(1, $source->last_items_found);
    }

    public function test_normalization_preserves_stable_external_id_and_optional_nulls(): void
    {
        $source = RecruiteeTestSourceFactory::create('Minimal Board', 'minimal');
        $listing = RecruiteeTestSourceFactory::turkeyListing('12345', 'Backend Engineer');
        unset($listing['category_code'], $listing['company_name'], $listing['locations']);

        $result = app(JobIngestionService::class)->ingest($source, $listing);

        $this->assertTrue($result['created']);
        $this->assertSame('12345', $result['job']->external_id);
        $this->assertNull($result['job']->category);
        $this->assertSame('Minimal Board', $result['job']->source_company_name);
    }

    public function test_turkey_first_source_rejects_global_recruitee_listing(): void
    {
        $source = RecruiteeTestSourceFactory::create('Mixed Board', 'mixed');

        Http::fake([
            'https://mixed.recruitee.com/api/offers*' => Http::response(
                RecruiteeTestSourceFactory::loadFixture('mixed-tr-global.json'),
                200,
            ),
        ]);

        $result = app(JobSourceImportService::class)->import($source);

        $this->assertSame(ImportRunStatus::Partial, $result['run']->status);
        $this->assertSame(2, $result['fetched']);
        $this->assertSame(1, $result['created']);
        $this->assertSame(1, $result['failed']);
        $this->assertSame(1, Job::query()->where('job_source_id', $source->id)->count());
    }

    public function test_duplicate_id_within_board_imports_single_canonical_job(): void
    {
        $source = RecruiteeTestSourceFactory::create('Duplicate Board', 'duplicate-board');

        Http::fake([
            'https://duplicate-board.recruitee.com/api/offers*' => Http::response(
                RecruiteeTestSourceFactory::loadFixture('duplicate-id.json'),
                200,
            ),
        ]);

        $result = app(JobSourceImportService::class)->import($source);

        $this->assertSame(ImportRunStatus::Completed, $result['run']->status);
        $this->assertSame(1, $result['fetched']);
        $this->assertSame(1, $result['created']);
        $this->assertSame(1, Job::query()->where('job_source_id', $source->id)->count());
        $this->assertDatabaseHas('jobs', [
            'job_source_id' => $source->id,
            'external_id' => '8888001',
            'title' => 'Duplicate Role Updated',
        ]);
    }

    public function test_stale_posting_is_counted_as_failed_during_import(): void
    {
        $source = RecruiteeTestSourceFactory::create('Stale Board', 'stale-board', [
            'max_posting_age_days' => 365,
        ]);

        Http::fake([
            'https://stale-board.recruitee.com/api/offers*' => Http::response(
                RecruiteeTestSourceFactory::loadFixture('stale-posting.json'),
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
        $source = RecruiteeTestSourceFactory::create('Empty Board', 'empty-board');

        Http::fake([
            'https://empty-board.recruitee.com/api/offers*' => Http::response(
                RecruiteeTestSourceFactory::loadFixture('empty-board.json'),
                200,
            ),
        ]);

        $result = app(JobSourceImportService::class)->import($source);

        $this->assertSame(ImportRunStatus::Failed, $result['run']->status);
        $this->assertSame(0, $result['fetched']);

        $source->refresh();
        $this->assertNotNull($source->last_failure_at);
        $this->assertGreaterThan(0, $source->consecutive_failures);
        $this->assertSame(1, JobImportRun::query()->where('job_source_id', $source->id)->count());
    }

    public function test_http_failure_is_handled_safely(): void
    {
        $source = RecruiteeTestSourceFactory::create('Broken Board', 'broken-board');

        Http::fake([
            'https://broken-board.recruitee.com/api/offers*' => Http::response('Not Found', 404),
        ]);

        $result = app(JobSourceImportService::class)->import($source);

        $this->assertSame(ImportRunStatus::Failed, $result['run']->status);
        $this->assertSame(0, $result['fetched']);
    }

    public function test_reimport_is_idempotent_without_duplicate_jobs(): void
    {
        $source = RecruiteeTestSourceFactory::create('Mikro Yazılım', 'mikroyazilim');

        Http::fake([
            'https://mikroyazilim.recruitee.com/api/offers*' => Http::response(
                RecruiteeTestSourceFactory::loadFixture('mikroyazilim-single.json'),
                200,
            ),
        ]);

        $service = app(JobSourceImportService::class);
        $first = $service->import($source);
        $second = $service->import($source);

        $this->assertSame(1, $first['created']);
        $this->assertSame(0, $second['created']);
        $this->assertSame(1, $second['updated']);
        $this->assertSame(1, Job::query()->where('job_source_id', $source->id)->count());
    }

    public function test_new_job_tracking_lifecycle_fields_are_populated(): void
    {
        $source = RecruiteeTestSourceFactory::create('Tracking Board', 'tracking');
        $listing = RecruiteeTestSourceFactory::turkeyListing();

        Carbon::setTestNow('2026-08-12 12:00:00');

        $result = app(JobIngestionService::class)->ingest($source, $listing);

        $this->assertTrue($result['created']);
        $this->assertNotNull($result['job']->first_seen_at);
        $this->assertNotNull($result['job']->last_seen_at);
        $this->assertSame('2026-08-12', $result['job']->provider_updated_at?->toDateString());

        Carbon::setTestNow();
    }

    public function test_phase_e3_seed_is_idempotent(): void
    {
        $defaults = [
            'provider' => 'recruitee',
            'page_size' => 100,
            'max_pages' => 1,
            'max_listings' => 200,
            'refresh_interval_minutes' => 360,
            'max_posting_age_days' => 365,
            'ingest_policy' => 'turkey_first',
        ];

        $board = [
            'name' => 'Mikro Yazılım',
            'site_slug' => 'mikroyazilim',
            'company_display_name' => 'Mikro Yazılım',
            'base_url' => 'https://mikroyazilim.recruitee.com/api/offers',
        ];

        $first = JobSource::query()->updateOrCreate(
            ['name' => $board['name']],
            [
                'base_url' => $board['base_url'],
                'type' => JobSourceType::ApiIntegration,
                'is_active' => true,
                'config' => array_merge($defaults, [
                    'site_slug' => $board['site_slug'],
                    'company_display_name' => $board['company_display_name'],
                ]),
            ],
        );

        $second = JobSource::query()->updateOrCreate(
            ['name' => $board['name']],
            [
                'base_url' => $board['base_url'],
                'type' => JobSourceType::ApiIntegration,
                'is_active' => true,
                'config' => array_merge($defaults, [
                    'site_slug' => $board['site_slug'],
                    'company_display_name' => $board['company_display_name'],
                ]),
            ],
        );

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, JobSource::query()->where('name', 'Mikro Yazılım')->count());
        $this->assertSame('recruitee', $second->config['provider']);
        $this->assertSame('turkey_first', $second->config['ingest_policy']);
    }

    public function test_provider_key_alone_does_not_resolve_recruitee_source(): void
    {
        RecruiteeTestSourceFactory::create('Mikro Yazılım', 'mikroyazilim');
        RecruiteeTestSourceFactory::create('Paraşüt', 'parasut');

        $this->artisan('jobs:import-source', ['source' => 'recruitee', '--sync' => true])
            ->assertExitCode(1);
    }

    public function test_direct_ingest_rejects_global_location_under_turkey_first(): void
    {
        $source = RecruiteeTestSourceFactory::create('Policy Board', 'policy');

        $this->expectException(ScraperFetchException::class);
        $this->expectExceptionMessage('turkey_first');

        app(JobIngestionService::class)->ingest($source, [
            'id' => 555,
            'title' => 'London Engineer',
            'description' => '<p>Global</p>',
            'country' => 'United Kingdom',
            'country_code' => 'GB',
            'city' => 'London',
            'location' => 'London, United Kingdom',
            'remote' => false,
            'hybrid' => false,
            'on_site' => true,
            'employment_type_code' => 'fulltime_permanent',
            'published_at' => '2026-08-01 10:00:00 UTC',
            'updated_at' => '2026-08-02 10:00:00 UTC',
            'careers_url' => 'https://example.recruitee.com/o/london',
            'status' => 'published',
        ]);
    }
}

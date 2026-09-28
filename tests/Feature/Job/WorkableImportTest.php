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
use Tests\Support\WorkableTestSourceFactory;
use Tests\TestCase;

class WorkableImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_command_resolves_source_by_name(): void
    {
        WorkableTestSourceFactory::create('Wingie Enuygun', 'wingieenuygun');

        Http::fake([
            'https://apply.workable.com/api/v1/widget/accounts/wingieenuygun*' => Http::response(
                WorkableTestSourceFactory::loadFixture('wingie-single.json'),
                200,
            ),
        ]);

        $this->artisan('jobs:import-source', ['source' => 'Wingie Enuygun', '--sync' => true])
            ->assertExitCode(0);
    }

    public function test_import_command_resolves_source_by_site_slug(): void
    {
        WorkableTestSourceFactory::create('Wingie Enuygun', 'wingieenuygun');

        Http::fake([
            'https://apply.workable.com/api/v1/widget/accounts/wingieenuygun*' => Http::response(
                WorkableTestSourceFactory::loadFixture('wingie-single.json'),
                200,
            ),
        ]);

        $this->artisan('jobs:import-source', ['source' => 'wingieenuygun', '--sync' => true])
            ->assertExitCode(0);
    }

    public function test_full_import_smoke_test_creates_jobs_and_records_health(): void
    {
        $source = WorkableTestSourceFactory::create('Wingie Enuygun', 'wingieenuygun');

        Http::fake([
            'https://apply.workable.com/api/v1/widget/accounts/wingieenuygun*' => Http::response(
                WorkableTestSourceFactory::loadFixture('wingie-single.json'),
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
            'external_id' => 'A8A326BDEF',
            'title' => 'Campaign Management Specialist',
            'source_company_name' => 'Wingie Enuygun',
        ]);

        $source->refresh();

        $this->assertNotNull($source->last_success_at);
        $this->assertSame(0, $source->consecutive_failures);
        $this->assertSame(1, $source->last_items_found);
    }

    public function test_turkey_first_keeps_turkey_postings_when_a_big_board_hits_the_listing_cap(): void
    {
        $source = WorkableTestSourceFactory::create('Big Global Board', 'bigglobalboard', [
            'max_listings' => 2,
            'ingest_policy' => 'turkey_first',
        ]);

        $template = WorkableTestSourceFactory::loadFixture('wingie-single.json')['jobs'][0];
        $foreign = fn (string $code): array => array_merge($template, [
            'shortcode' => $code,
            'title' => 'Foreign role '.$code,
            'country' => 'Germany',
            'city' => 'Berlin',
            'state' => 'Berlin',
            'locations' => [['country' => 'Germany', 'countryCode' => 'DE', 'city' => 'Berlin', 'region' => 'Berlin', 'hidden' => false]],
        ]);

        Http::fake([
            'https://apply.workable.com/api/v1/widget/accounts/bigglobalboard*' => Http::response([
                'name' => 'Big Global Board',
                'jobs' => [$foreign('DE00000001'), $foreign('DE00000002'), $foreign('DE00000003'), $template],
            ], 200),
        ]);

        app(JobSourceImportService::class)->import($source);

        $this->assertDatabaseHas('jobs', [
            'job_source_id' => $source->id,
            'external_id' => 'A8A326BDEF',
        ]);
    }

    public function test_multi_country_posting_keeps_its_turkey_location_under_turkey_first(): void
    {
        $source = WorkableTestSourceFactory::create('Multi Country Board', 'multicountryboard', [
            'ingest_policy' => 'turkey_first',
        ]);

        $turkey = WorkableTestSourceFactory::loadFixture('wingie-single.json')['jobs'][0];
        $vietnam = array_merge($turkey, [
            'country' => 'Vietnam',
            'city' => 'Hanoi',
            'state' => 'Hanoi',
            'locations' => [['country' => 'Vietnam', 'countryCode' => 'VN', 'city' => 'Hanoi', 'region' => 'Hanoi', 'hidden' => false]],
        ]);

        // Workable lists one row per country with the same shortcode.
        Http::fake([
            'https://apply.workable.com/api/v1/widget/accounts/multicountryboard*' => Http::response([
                'name' => 'Multi Country Board',
                'jobs' => [$turkey, $vietnam],
            ], 200),
        ]);

        app(JobSourceImportService::class)->import($source);

        $this->assertDatabaseHas('jobs', [
            'job_source_id' => $source->id,
            'external_id' => 'A8A326BDEF',
            'city' => 'Istanbul',
        ]);
    }

    public function test_duplicate_shortcode_within_board_imports_single_canonical_job(): void
    {
        $source = WorkableTestSourceFactory::create('Wingie Enuygun', 'wingieenuygun');

        Http::fake([
            'https://apply.workable.com/api/v1/widget/accounts/wingieenuygun*' => Http::response(
                WorkableTestSourceFactory::loadFixture('duplicate-shortcode.json'),
                200,
            ),
        ]);

        $result = app(JobSourceImportService::class)->import($source);

        $this->assertSame(ImportRunStatus::Completed, $result['run']->status);
        $this->assertSame(1, $result['fetched']);
        $this->assertSame(1, $result['created']);
        $this->assertSame(0, $result['failed']);
        $this->assertSame(1, Job::query()->where('job_source_id', $source->id)->count());
        $this->assertDatabaseHas('jobs', [
            'job_source_id' => $source->id,
            'external_id' => '1FF5A9AA2B',
            'title' => 'Duplicate Role Updated',
        ]);
    }

    public function test_stale_posting_is_counted_as_failed_during_import(): void
    {
        $source = WorkableTestSourceFactory::create('Stale Board', 'stale-board', [
            'max_posting_age_days' => 365,
        ]);

        Http::fake([
            'https://apply.workable.com/api/v1/widget/accounts/stale-board*' => Http::response(
                WorkableTestSourceFactory::loadFixture('stale-posting.json'),
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
        $source = WorkableTestSourceFactory::create('Empty Board', 'empty-board');

        Http::fake([
            'https://apply.workable.com/api/v1/widget/accounts/empty-board*' => Http::response(
                WorkableTestSourceFactory::loadFixture('empty-board.json'),
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

    public function test_provider_key_alone_does_not_resolve_workable_source(): void
    {
        WorkableTestSourceFactory::create('Wingie Enuygun', 'wingieenuygun');
        WorkableTestSourceFactory::create('Vertigo Games', 'vertigogames');

        $this->artisan('jobs:import-source', ['source' => 'workable', '--sync' => true])
            ->assertExitCode(1);
    }
}

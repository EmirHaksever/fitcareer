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

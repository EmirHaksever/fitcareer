<?php

declare(strict_types=1);

namespace Tests\Feature\Job;

use App\Enums\ImportRunStatus;
use App\Models\Job;
use App\Models\JobImportRun;
use App\Services\Scraper\JobSourceImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\GreenhouseTestSourceFactory;
use Tests\TestCase;

class GreenhouseImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_command_resolves_source_by_name(): void
    {
        GreenhouseTestSourceFactory::create('Good Job Games', 'goodjobgames');

        Http::fake([
            'https://boards-api.greenhouse.io/v1/boards/goodjobgames/jobs*' => Http::response(
                GreenhouseTestSourceFactory::loadFixture('goodjobgames-single.json'),
                200,
            ),
        ]);

        $this->artisan('jobs:import-source', ['source' => 'Good Job Games', '--sync' => true])
            ->assertExitCode(0);
    }

    public function test_import_command_resolves_source_by_site_slug(): void
    {
        GreenhouseTestSourceFactory::create('Good Job Games', 'goodjobgames');

        Http::fake([
            'https://boards-api.greenhouse.io/v1/boards/goodjobgames/jobs*' => Http::response(
                GreenhouseTestSourceFactory::loadFixture('goodjobgames-single.json'),
                200,
            ),
        ]);

        $this->artisan('jobs:import-source', ['source' => 'goodjobgames', '--sync' => true])
            ->assertExitCode(0);
    }

    public function test_full_import_smoke_test_creates_jobs_and_records_health(): void
    {
        $source = GreenhouseTestSourceFactory::create('Good Job Games', 'goodjobgames');

        Http::fake([
            'https://boards-api.greenhouse.io/v1/boards/goodjobgames/jobs*' => Http::response(
                GreenhouseTestSourceFactory::loadFixture('goodjobgames-single.json'),
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
            'external_id' => '4968083003',
            'title' => '2D Animator, Studio',
            'source_company_name' => 'Good Job Games',
        ]);

        $source->refresh();

        $this->assertNotNull($source->last_success_at);
        $this->assertSame(0, $source->consecutive_failures);
        $this->assertSame(1, $source->last_items_found);
    }

    public function test_duplicate_id_within_board_imports_single_canonical_job(): void
    {
        $source = GreenhouseTestSourceFactory::create('Good Job Games', 'goodjobgames');

        Http::fake([
            'https://boards-api.greenhouse.io/v1/boards/goodjobgames/jobs*' => Http::response(
                GreenhouseTestSourceFactory::loadFixture('duplicate-id.json'),
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
            'external_id' => '1111111111',
            'title' => 'Duplicate Role Updated',
        ]);
    }

    public function test_stale_posting_is_counted_as_failed_during_import(): void
    {
        $source = GreenhouseTestSourceFactory::create('Stale Board', 'stale-board', [
            'max_posting_age_days' => 365,
        ]);

        Http::fake([
            'https://boards-api.greenhouse.io/v1/boards/stale-board/jobs*' => Http::response(
                GreenhouseTestSourceFactory::loadFixture('stale-posting.json'),
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
        $source = GreenhouseTestSourceFactory::create('Empty Board', 'empty-board');

        Http::fake([
            'https://boards-api.greenhouse.io/v1/boards/empty-board/jobs*' => Http::response(
                GreenhouseTestSourceFactory::loadFixture('empty-board.json'),
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

    public function test_provider_key_alone_does_not_resolve_greenhouse_source(): void
    {
        GreenhouseTestSourceFactory::create('Good Job Games', 'goodjobgames');
        GreenhouseTestSourceFactory::create('Medsien', 'medsien');

        $this->artisan('jobs:import-source', ['source' => 'greenhouse', '--sync' => true])
            ->assertExitCode(1);
    }
}

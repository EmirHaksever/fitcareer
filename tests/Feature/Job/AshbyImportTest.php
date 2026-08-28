<?php

declare(strict_types=1);

namespace Tests\Feature\Job;

use App\Enums\ImportRunStatus;
use App\Models\Job;
use App\Models\JobImportRun;
use App\Services\Scraper\JobSourceImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\AshbyTestSourceFactory;
use Tests\TestCase;

class AshbyImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_command_resolves_source_by_name(): void
    {
        AshbyTestSourceFactory::create('Codeway', 'codeway');

        Http::fake([
            'https://api.ashbyhq.com/posting-api/job-board/codeway*' => Http::response(
                AshbyTestSourceFactory::loadFixture('codeway-single.json'),
                200,
            ),
        ]);

        $this->artisan('jobs:import-source', ['source' => 'Codeway', '--sync' => true])
            ->assertExitCode(0);
    }

    public function test_import_command_resolves_source_by_site_slug(): void
    {
        AshbyTestSourceFactory::create('Codeway', 'codeway');

        Http::fake([
            'https://api.ashbyhq.com/posting-api/job-board/codeway*' => Http::response(
                AshbyTestSourceFactory::loadFixture('codeway-single.json'),
                200,
            ),
        ]);

        $this->artisan('jobs:import-source', ['source' => 'codeway', '--sync' => true])
            ->assertExitCode(0);
    }

    public function test_full_import_smoke_test_creates_jobs_and_records_health(): void
    {
        $source = AshbyTestSourceFactory::create('Codeway', 'codeway');

        Http::fake([
            'https://api.ashbyhq.com/posting-api/job-board/codeway*' => Http::response(
                AshbyTestSourceFactory::loadFixture('codeway-single.json'),
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
            'external_id' => '3f5c2489-d889-4783-b846-70f04d274094',
            'title' => 'Growth Manager, Learna',
            'source_company_name' => 'Codeway',
        ]);

        $source->refresh();

        $this->assertNotNull($source->last_success_at);
        $this->assertSame(0, $source->consecutive_failures);
        $this->assertSame(1, $source->last_items_found);
    }

    public function test_duplicate_id_within_board_imports_single_canonical_job(): void
    {
        $source = AshbyTestSourceFactory::create('Codeway', 'codeway');

        Http::fake([
            'https://api.ashbyhq.com/posting-api/job-board/codeway*' => Http::response(
                AshbyTestSourceFactory::loadFixture('duplicate-id.json'),
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
            'external_id' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
            'title' => 'Duplicate Role Updated',
        ]);
    }

    public function test_stale_posting_is_counted_as_failed_during_import(): void
    {
        $source = AshbyTestSourceFactory::create('Stale Board', 'stale-board', [
            'max_posting_age_days' => 365,
        ]);

        Http::fake([
            'https://api.ashbyhq.com/posting-api/job-board/stale-board*' => Http::response(
                AshbyTestSourceFactory::loadFixture('stale-posting.json'),
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
        $source = AshbyTestSourceFactory::create('Empty Board', 'empty-board');

        Http::fake([
            'https://api.ashbyhq.com/posting-api/job-board/empty-board*' => Http::response(
                AshbyTestSourceFactory::loadFixture('empty-board.json'),
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

    public function test_provider_key_alone_does_not_resolve_ashby_source(): void
    {
        AshbyTestSourceFactory::create('Codeway', 'codeway');
        AshbyTestSourceFactory::create('Agave Games', 'agavegames');

        $this->artisan('jobs:import-source', ['source' => 'ashby', '--sync' => true])
            ->assertExitCode(1);
    }
}

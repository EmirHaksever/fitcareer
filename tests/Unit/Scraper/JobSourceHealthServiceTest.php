<?php

declare(strict_types=1);

namespace Tests\Unit\Scraper;

use App\Enums\ImportRunStatus;
use App\Models\JobImportRun;
use App\Models\JobSource;
use App\Services\Scraper\JobSourceHealthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class JobSourceHealthServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_records_success_and_failure_metrics_on_job_source(): void
    {
        $source = JobSource::factory()->create([
            'consecutive_failures' => 2,
            'last_error' => 'Previous error',
        ]);

        $health = app(JobSourceHealthService::class);

        $successRun = JobImportRun::factory()->create([
            'job_source_id' => $source->id,
            'status' => ImportRunStatus::Completed,
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
            'items_found' => 17,
            'items_created' => 3,
            'items_updated' => 14,
        ]);

        $health->recordSuccess($source->fresh(), $successRun);

        $source->refresh();
        $this->assertSame(0, $source->consecutive_failures);
        $this->assertNull($source->last_error);
        $this->assertSame(17, $source->last_items_found);

        $failedRun = JobImportRun::factory()->create([
            'job_source_id' => $source->id,
            'status' => ImportRunStatus::Failed,
            'started_at' => now(),
            'finished_at' => now(),
            'items_found' => 0,
            'error_log' => ['HTTP 403 blocked'],
        ]);

        $health->recordFailure($source->fresh(), $failedRun, 'HTTP 403 blocked');

        $source->refresh();
        $this->assertSame(1, $source->consecutive_failures);
        $this->assertSame('HTTP 403 blocked', $source->last_error);
        $this->assertNotNull($source->last_failure_at);

        $snapshot = $health->snapshot($source->fresh());
        $this->assertSame('HTTP 403 blocked', $snapshot['last_error']);
        $this->assertNotNull($snapshot['latest_run']);
    }
}

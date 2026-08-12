<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ImportRunStatus;
use App\Models\JobImportRun;
use App\Models\JobSource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobImportRun>
 */
class JobImportRunFactory extends Factory
{
    protected $model = JobImportRun::class;

    public function definition(): array
    {
        return [
            'job_source_id' => JobSource::factory(),
            'status' => ImportRunStatus::Running,
            'started_at' => now(),
            'finished_at' => null,
            'items_found' => 0,
            'items_created' => 0,
            'items_updated' => 0,
            'items_skipped' => 0,
            'items_failed' => 0,
            'error_log' => null,
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Scraper;

use App\Enums\ImportRunStatus;
use App\Models\JobImportRun;
use App\Models\JobSource;

class JobSourceHealthService
{
    /**
     * @return array<string, mixed>
     */
    public function snapshot(JobSource $source): array
    {
        $latestRun = JobImportRun::query()
            ->where('job_source_id', $source->id)
            ->orderByDesc('started_at')
            ->first();

        $latestSuccess = JobImportRun::query()
            ->where('job_source_id', $source->id)
            ->whereIn('status', [ImportRunStatus::Completed, ImportRunStatus::Partial])
            ->orderByDesc('finished_at')
            ->first();

        $latestFailure = JobImportRun::query()
            ->where('job_source_id', $source->id)
            ->where('status', ImportRunStatus::Failed)
            ->orderByDesc('finished_at')
            ->first();

        return [
            'source_id' => $source->id,
            'source_name' => $source->name,
            'provider' => $source->config['provider'] ?? null,
            'is_active' => $source->is_active,
            'last_run_at' => $source->last_run_at?->toIso8601String(),
            'last_success_at' => $source->last_success_at?->toIso8601String(),
            'last_failure_at' => $source->last_failure_at?->toIso8601String(),
            'last_error' => $source->last_error,
            'consecutive_failures' => $source->consecutive_failures,
            'last_items_found' => $source->last_items_found,
            'last_items_created' => $source->last_items_created,
            'last_items_updated' => $source->last_items_updated,
            'latest_run' => $latestRun === null ? null : [
                'id' => $latestRun->id,
                'status' => $latestRun->status->value,
                'started_at' => $latestRun->started_at?->toIso8601String(),
                'finished_at' => $latestRun->finished_at?->toIso8601String(),
                'items_found' => $latestRun->items_found,
                'items_created' => $latestRun->items_created,
                'items_updated' => $latestRun->items_updated,
                'items_failed' => $latestRun->items_failed,
                'error_log' => $latestRun->error_log,
            ],
            'latest_successful_run_id' => $latestSuccess?->id,
            'latest_failed_run_id' => $latestFailure?->id,
        ];
    }

    public function recordSuccess(JobSource $source, JobImportRun $run): void
    {
        $source->update([
            'last_run_at' => $run->finished_at ?? now(),
            'last_success_at' => $run->finished_at ?? now(),
            'last_error' => null,
            'consecutive_failures' => 0,
            'last_items_found' => $run->items_found,
            'last_items_created' => $run->items_created,
            'last_items_updated' => $run->items_updated,
        ]);
    }

    public function recordFailure(JobSource $source, JobImportRun $run, string $error): void
    {
        $source->update([
            'last_run_at' => $run->finished_at ?? now(),
            'last_failure_at' => $run->finished_at ?? now(),
            'last_error' => mb_substr($error, 0, 2000),
            'consecutive_failures' => $source->consecutive_failures + 1,
            'last_items_found' => $run->items_found,
            'last_items_created' => $run->items_created,
            'last_items_updated' => $run->items_updated,
        ]);
    }
}

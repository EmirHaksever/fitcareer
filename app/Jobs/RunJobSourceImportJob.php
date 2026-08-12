<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\JobSource;
use App\Services\Scraper\JobSourceImportService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class RunJobSourceImportJob implements ShouldBeUnique, ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300, 900];

    public function __construct(
        public readonly int $jobSourceId,
    ) {
        $this->onQueue((string) config('scraper.queue'));
    }

    public function uniqueId(): string
    {
        return 'job-source-import:'.$this->jobSourceId;
    }

    public function handle(JobSourceImportService $jobSourceImportService): void
    {
        $source = JobSource::query()
            ->where('is_active', true)
            ->find($this->jobSourceId);

        if ($source === null) {
            Log::warning('Skipping job source import; source missing or inactive.', [
                'job_source_id' => $this->jobSourceId,
            ]);

            return;
        }

        $jobSourceImportService->import($source);
    }

    public function failed(?\Throwable $exception): void
    {
        Log::error('Job source import queue job failed.', [
            'job_source_id' => $this->jobSourceId,
            'message' => $exception?->getMessage(),
        ]);
    }
}

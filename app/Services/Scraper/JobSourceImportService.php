<?php

declare(strict_types=1);

namespace App\Services\Scraper;

use App\Enums\ImportRunStatus;
use App\Enums\JobOrigin;
use App\Enums\JobStatus;
use App\Events\JobImportCompleted;
use App\Exceptions\ScraperFetchException;
use App\Models\JobImportRun;
use App\Models\JobSource;
use Illuminate\Support\Facades\Log;

class JobSourceImportService
{
    public function __construct(
        private readonly ScraperClientService $scraperClientService,
        private readonly JobIngestionService $jobIngestionService,
        private readonly ScrapedJobFreshnessService $scrapedJobFreshnessService,
        private readonly JobSourceHealthService $jobSourceHealthService,
    ) {}

    /**
     * @return array{run: JobImportRun, fetched: int, created: int, updated: int, failed: int}
     */
    public function import(JobSource $source): array
    {
        $run = JobImportRun::query()->create([
            'job_source_id' => $source->id,
            'status' => ImportRunStatus::Running,
            'started_at' => now(),
            'items_found' => 0,
            'items_created' => 0,
            'items_updated' => 0,
            'items_skipped' => 0,
            'items_failed' => 0,
            'error_log' => [],
        ]);

        $errors = [];
        $created = 0;
        $updated = 0;
        $failed = 0;

        try {
            $listings = $this->scraperClientService->fetchListingsForImport($source);
        } catch (ScraperFetchException $exception) {
            return $this->failRun($source, $run, $exception->getMessage(), $errors);
        } catch (\Throwable $exception) {
            return $this->failRun($source, $run, $exception->getMessage(), $errors);
        }

        $fetched = count($listings);
        $run->items_found = $fetched;

        foreach ($listings as $index => $rawListing) {
            try {
                $result = $this->jobIngestionService->ingest($source, $rawListing);

                if ($result['created']) {
                    $created++;
                } else {
                    $updated++;
                }
            } catch (\Throwable $exception) {
                $failed++;
                $message = 'Listing #'.($index + 1).': '.$exception->getMessage();
                $errors[] = $message;

                Log::warning('Job ingestion item failed.', [
                    'source_id' => $source->id,
                    'import_run_id' => $run->id,
                    'listing_index' => $index + 1,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        try {
            $freshness = $this->scrapedJobFreshnessService->applyLifecycle($source);
        } catch (\Throwable $exception) {
            $failed++;
            $errors[] = 'Freshness lifecycle: '.$exception->getMessage();

            Log::warning('Scraped job freshness lifecycle failed.', [
                'source_id' => $source->id,
                'import_run_id' => $run->id,
                'message' => $exception->getMessage(),
            ]);

            $freshness = ['stale' => 0, 'expired' => 0];
        }

        $run->items_created = $created;
        $run->items_updated = $updated;
        $run->items_failed = $failed;
        $run->error_log = $errors !== [] ? $errors : null;
        $run->finished_at = now();

        if ($fetched === 0) {
            $run->status = ImportRunStatus::Failed;
        } elseif ($failed > 0 && ($created + $updated) === 0) {
            $run->status = ImportRunStatus::Failed;
        } elseif ($failed > 0) {
            $run->status = ImportRunStatus::Partial;
        } else {
            $run->status = ImportRunStatus::Completed;
        }

        $run->save();

        if ($run->status !== ImportRunStatus::Failed) {
            $this->jobSourceHealthService->recordSuccess($source->fresh(), $run->fresh());
            event(new JobImportCompleted($source->fresh(), $run->fresh()));
        } else {
            $this->jobSourceHealthService->recordFailure(
                $source->fresh(),
                $run->fresh(),
                is_array($run->error_log) ? implode('; ', $run->error_log) : 'Import failed.',
            );
        }

        Log::info('Job source import finished.', [
            'source_id' => $source->id,
            'source_name' => $source->name,
            'import_run_id' => $run->id,
            'status' => $run->status->value,
            'fetched' => $fetched,
            'created' => $created,
            'updated' => $updated,
            'failed' => $failed,
            'stale_marked' => $freshness['stale'],
            'expired_marked' => $freshness['expired'],
        ]);

        return [
            'run' => $run->fresh(),
            'fetched' => $fetched,
            'created' => $created,
            'updated' => $updated,
            'failed' => $failed,
        ];
    }

    /**
     * @param  list<string>  $errors
     * @return array{run: JobImportRun, fetched: int, created: int, updated: int, failed: int}
     */
    private function failRun(JobSource $source, JobImportRun $run, string $message, array $errors): array
    {
        $errors[] = $message;

        $run->status = ImportRunStatus::Failed;
        $run->items_failed = max(1, $run->items_failed);
        $run->error_log = $errors;
        $run->finished_at = now();
        $run->save();

        $this->jobSourceHealthService->recordFailure($source->fresh(), $run->fresh(), $message);

        Log::error('Job source import failed.', [
            'source_id' => $source->id,
            'source_name' => $source->name,
            'import_run_id' => $run->id,
            'message' => $message,
        ]);

        return [
            'run' => $run->fresh(),
            'fetched' => 0,
            'created' => 0,
            'updated' => 0,
            'failed' => max(1, $run->items_failed),
        ];
    }
}

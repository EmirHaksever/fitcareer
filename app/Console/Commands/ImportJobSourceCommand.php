<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\RunJobSourceImportJob;
use App\Models\JobSource;
use App\Services\Scraper\JobSourceImportService;
use Illuminate\Console\Command;

class ImportJobSourceCommand extends Command
{
    protected $signature = 'jobs:import-source
        {source : Job source name or provider key}
        {--sync : Run synchronously instead of dispatching to the queue}';

    protected $description = 'Dispatch or run a production import for a configured job source.';

    public function handle(JobSourceImportService $jobSourceImportService): int
    {
        $source = $this->resolveSource(strtolower(trim((string) $this->argument('source'))));

        if ($source === null) {
            $this->error('Job source not found.');

            return self::FAILURE;
        }

        if ($this->option('sync')) {
            $result = $jobSourceImportService->import($source);

            $this->line('Import run #'.$result['run']->id.' ['.$result['run']->status->value.']');
            $this->line('Fetched: '.$result['fetched']);
            $this->line('Created: '.$result['created']);
            $this->line('Updated: '.$result['updated']);
            $this->line('Failed: '.$result['failed']);

            return $result['run']->status->value === 'failed' ? self::FAILURE : self::SUCCESS;
        }

        RunJobSourceImportJob::dispatch($source->id);
        $this->info('Dispatched import job for '.$source->name.' (id='.$source->id.').');

        return self::SUCCESS;
    }

    private function resolveSource(string $sourceKey): ?JobSource
    {
        if ($sourceKey === '') {
            return null;
        }

        return JobSource::query()
            ->where('is_active', true)
            ->where(function ($query) use ($sourceKey): void {
                $query->whereRaw('LOWER(name) = ?', [$sourceKey])
                    ->orWhere('config->provider', $sourceKey);
            })
            ->first();
    }
}

<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\JobSource;
use App\Services\Scraper\JobSourceHealthService;
use Illuminate\Console\Command;

class JobSourceHealthCommand extends Command
{
    protected $signature = 'jobs:source-health {source? : Job source name or provider key}';

    protected $description = 'Show ingestion health metrics for configured job sources.';

    public function handle(JobSourceHealthService $jobSourceHealthService): int
    {
        $sourceKey = $this->argument('source');

        $query = JobSource::query()->orderBy('id');

        if (is_string($sourceKey) && trim($sourceKey) !== '') {
            $key = strtolower(trim($sourceKey));
            $query->where(function ($builder) use ($key): void {
                $builder->whereRaw('LOWER(name) = ?', [$key])
                    ->orWhere('config->provider', $key);
            });
        }

        $sources = $query->get();

        if ($sources->isEmpty()) {
            $this->error('No matching job sources found.');

            return self::FAILURE;
        }

        foreach ($sources as $source) {
            $health = $jobSourceHealthService->snapshot($source->fresh());

            $this->line('');
            $this->info($health['source_name'].' (id='.$health['source_id'].', provider='.($health['provider'] ?? 'n/a').')');
            $this->line('  active: '.($health['is_active'] ? 'yes' : 'no'));
            $this->line('  last_run_at: '.($health['last_run_at'] ?? 'never'));
            $this->line('  last_success_at: '.($health['last_success_at'] ?? 'never'));
            $this->line('  last_failure_at: '.($health['last_failure_at'] ?? 'never'));
            $this->line('  consecutive_failures: '.$health['consecutive_failures']);
            $this->line('  last fetched/created/updated: '
                .($health['last_items_found'] ?? 0).'/'
                .($health['last_items_created'] ?? 0).'/'
                .($health['last_items_updated'] ?? 0));

            if ($health['last_error']) {
                $this->line('  last_error: '.$health['last_error']);
            }

            if ($health['latest_run']) {
                $run = $health['latest_run'];
                $this->line('  latest_run: #'.$run['id'].' ['.$run['status'].'] found='.$run['items_found']);
            }
        }

        return self::SUCCESS;
    }
}

<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\RunJobSourceImportJob;
use App\Models\JobSource;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class DispatchScheduledJobImportsCommand extends Command
{
    protected $signature = 'jobs:dispatch-scheduled-imports';

    protected $description = 'Dispatch queue import jobs for job sources due for refresh.';

    public function handle(): int
    {
        $dispatched = 0;

        JobSource::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->each(function (JobSource $source) use (&$dispatched): void {
                if (! $this->isDue($source)) {
                    return;
                }

                RunJobSourceImportJob::dispatch($source->id);
                $dispatched++;
                $this->line('Dispatched: '.$source->name.' (id='.$source->id.')');
            });

        $this->info('Dispatched '.$dispatched.' import job(s).');

        return self::SUCCESS;
    }

    private function isDue(JobSource $source): bool
    {
        $intervalMinutes = (int) ($source->config['refresh_interval_minutes']
            ?? config('scraper.default_refresh_interval_minutes'));

        if ($intervalMinutes <= 0) {
            return true;
        }

        if ($source->last_run_at === null) {
            return true;
        }

        return $source->last_run_at->lte(Carbon::now()->subMinutes($intervalMinutes));
    }
}

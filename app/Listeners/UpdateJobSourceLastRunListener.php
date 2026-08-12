<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\JobImportCompleted;
use App\Models\JobImportRun;
use App\Models\JobSource;
use App\Services\Scraper\JobSourceHealthService;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class UpdateJobSourceLastRunListener implements ShouldHandleEventsAfterCommit
{
    public function handle(JobImportCompleted $event): void
    {
        // Health metrics are persisted in JobSourceImportService; keep event for future hooks.
    }
}

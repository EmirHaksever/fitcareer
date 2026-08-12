<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\JobImportRun;
use App\Models\JobSource;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class JobImportCompleted implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public JobSource $jobSource,
        public JobImportRun $importRun,
    ) {}
}

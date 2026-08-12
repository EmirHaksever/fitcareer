<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Job;
use App\Models\JobRefreshRequest;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class JobRefreshFailed implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Job $job,
        public JobRefreshRequest $refreshRequest,
    ) {}
}

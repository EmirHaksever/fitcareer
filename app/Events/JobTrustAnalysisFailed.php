<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Job;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class JobTrustAnalysisFailed implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Job $job,
    ) {}
}

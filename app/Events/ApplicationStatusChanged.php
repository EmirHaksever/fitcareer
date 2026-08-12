<?php

declare(strict_types=1);

namespace App\Events;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ApplicationStatusChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Application $application,
        public ApplicationStatus $fromStatus,
        public ApplicationStatus $toStatus,
    ) {}
}

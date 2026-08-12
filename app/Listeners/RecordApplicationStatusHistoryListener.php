<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\ApplicationStatusChanged;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class RecordApplicationStatusHistoryListener implements ShouldHandleEventsAfterCommit
{
    public function handle(ApplicationStatusChanged $event): void
    {
        // TODO: Reserved for ancillary side effects only.
        // Application status history is persisted inside ApplicationService::transitionStatus().
    }
}

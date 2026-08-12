<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\ApplicationStatusChanged;
use App\Services\Notification\NotificationDispatcherService;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class DispatchApplicationNotificationListener implements ShouldHandleEventsAfterCommit
{
    public function __construct(
        private readonly NotificationDispatcherService $notificationDispatcher,
    ) {}

    public function handle(ApplicationStatusChanged $event): void
    {
        // TODO: Dispatch application status notification via NotificationDispatcherService.
    }
}

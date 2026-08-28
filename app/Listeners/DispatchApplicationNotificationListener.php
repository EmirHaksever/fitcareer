<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\ApplicationStatusChanged;
use App\Services\Notification\ApplicationStatusNotificationFactory;
use App\Services\Notification\NotificationDispatcherService;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class DispatchApplicationNotificationListener implements ShouldHandleEventsAfterCommit
{
    public function __construct(
        private readonly NotificationDispatcherService $notificationDispatcher,
        private readonly ApplicationStatusNotificationFactory $notificationFactory,
    ) {}

    public function handle(ApplicationStatusChanged $event): void
    {
        $application = $event->application->loadMissing('candidateProfile.user');

        $user = $application->candidateProfile?->user;

        if ($user === null) {
            return;
        }

        $payload = $this->notificationFactory->fromStatusChange($event);

        if ($payload === null) {
            return;
        }

        $this->notificationDispatcher->dispatch($user, $payload);
    }
}

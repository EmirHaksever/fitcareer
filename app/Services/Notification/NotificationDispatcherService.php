<?php

declare(strict_types=1);

namespace App\Services\Notification;

use App\Models\User;
use Illuminate\Notifications\Notification;

class NotificationDispatcherService
{
    public function dispatch(User $user, Notification $notification): void
    {
        // TODO: Dispatch notification on notifications queue after commit.
        throw new \LogicException('Not implemented.');
    }
}

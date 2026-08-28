<?php

declare(strict_types=1);

namespace App\Services\Notification;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Str;

class NotificationDispatcherService
{
    public function dispatch(User $user, InAppNotificationPayload $payload): ?Notification
    {
        if ($this->hasExisting($user, $payload->dedupeKey)) {
            return null;
        }

        return $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => $payload->type,
            'category' => $payload->category,
            'data' => $payload->toDataArray(),
        ]);
    }

    private function hasExisting(User $user, string $dedupeKey): bool
    {
        return $user->notifications()
            ->where('data->dedupe_key', $dedupeKey)
            ->exists();
    }
}

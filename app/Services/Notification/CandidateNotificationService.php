<?php

declare(strict_types=1);

namespace App\Services\Notification;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class CandidateNotificationService
{
    private const DEFAULT_PER_PAGE = 20;

    private const MAX_PER_PAGE = 50;

    public function listForUser(User $user, int $page = 1, ?int $perPage = null): LengthAwarePaginator
    {
        $limit = min($perPage ?? self::DEFAULT_PER_PAGE, self::MAX_PER_PAGE);

        return $user->notifications()
            ->orderByDesc('created_at')
            ->paginate($limit, ['*'], 'page', max($page, 1));
    }

    public function unreadCountForUser(User $user): int
    {
        return $user->unreadNotifications()->count();
    }

    public function markAsRead(User $user, string $notificationId): Notification
    {
        /** @var Notification|null $notification */
        $notification = $user->notifications()->whereKey($notificationId)->first();

        if ($notification === null) {
            abort(404, 'Notification not found.');
        }

        if ($notification->read_at === null) {
            $notification->forceFill(['read_at' => Carbon::now()])->save();
        }

        return $notification->fresh();
    }

    public function markAllAsRead(User $user): int
    {
        return $user->unreadNotifications()->update(['read_at' => Carbon::now()]);
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Notification;

use App\Enums\NotificationCategory;

final readonly class InAppNotificationPayload
{
    public function __construct(
        public string $type,
        public NotificationCategory $category,
        public string $title,
        public string $body,
        public string $dedupeKey,
        public ?string $actionPath = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toDataArray(): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'dedupe_key' => $this->dedupeKey,
            'action_path' => $this->actionPath,
        ];
    }
}

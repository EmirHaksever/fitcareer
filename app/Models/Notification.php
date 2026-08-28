<?php

namespace App\Models;

use App\Enums\NotificationCategory;
use Illuminate\Notifications\DatabaseNotification;

class Notification extends DatabaseNotification
{
    protected $fillable = [
        'id',
        'type',
        'category',
        'notifiable_type',
        'notifiable_id',
        'data',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'category' => NotificationCategory::class,
            'data' => 'array',
            'read_at' => 'datetime',
        ];
    }
}

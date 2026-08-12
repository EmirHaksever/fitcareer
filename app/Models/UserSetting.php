<?php

namespace App\Models;

use App\Enums\ProfileVisibility;
use App\Enums\Theme;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'notification_email_enabled',
        'notification_push_enabled',
        'notification_sms_enabled',
        'notify_job_matches',
        'notify_application_updates',
        'notify_system',
        'notify_promotions',
        'profile_visibility',
        'language',
        'timezone',
        'theme',
    ];

    protected function casts(): array
    {
        return [
            'notification_email_enabled' => 'boolean',
            'notification_push_enabled' => 'boolean',
            'notification_sms_enabled' => 'boolean',
            'notify_job_matches' => 'boolean',
            'notify_application_updates' => 'boolean',
            'notify_system' => 'boolean',
            'notify_promotions' => 'boolean',
            'profile_visibility' => ProfileVisibility::class,
            'theme' => Theme::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

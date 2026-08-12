<?php

namespace App\Enums;

enum NotificationCategory: string
{
    case JobMatch = 'job_match';
    case ApplicationUpdate = 'application_update';
    case System = 'system';
    case Promotion = 'promotion';
}

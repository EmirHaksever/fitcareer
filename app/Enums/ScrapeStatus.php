<?php

namespace App\Enums;

enum ScrapeStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Success = 'success';
    case Failed = 'failed';
    case Stale = 'stale';
}

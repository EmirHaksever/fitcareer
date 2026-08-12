<?php

namespace App\Enums;

enum JobReportStatus: string
{
    case Reported = 'reported';
    case Reviewing = 'reviewing';
    case Resolved = 'resolved';
    case Rejected = 'rejected';
}

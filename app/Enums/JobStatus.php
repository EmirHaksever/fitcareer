<?php

namespace App\Enums;

enum JobStatus: string
{
    case Draft = 'draft';
    case PendingReview = 'pending_review';
    case Published = 'published';
    case Expired = 'expired';
    case Closed = 'closed';
    case Flagged = 'flagged';
}

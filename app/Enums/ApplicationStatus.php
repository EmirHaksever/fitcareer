<?php

namespace App\Enums;

enum ApplicationStatus: string
{
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case Shortlisted = 'shortlisted';
    case Interview = 'interview';
    case Offered = 'offered';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';
}

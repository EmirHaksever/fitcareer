<?php

namespace App\Enums;

enum TrustAnalysisStatus: string
{
    case Pending = 'pending';
    case Analyzing = 'analyzing';
    case Completed = 'completed';
    case Failed = 'failed';
}

<?php

namespace App\Enums;

enum AiAnalysisType: string
{
    case JobTrust = 'job_trust';
    case CvJobFit = 'cv_job_fit';
}

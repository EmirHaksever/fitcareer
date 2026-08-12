<?php

namespace App\Enums;

enum JobReportReason: string
{
    case SuspiciousJob = 'suspicious_job';
    case ScamSuspected = 'scam_suspected';
    case CompanyInformationWrong = 'company_information_wrong';
    case JobNoLongerExists = 'job_no_longer_exists';
    case MisleadingSalary = 'misleading_salary';
    case PersonalInformationRequest = 'personal_information_request';
    case Other = 'other';
}

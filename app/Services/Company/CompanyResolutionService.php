<?php

declare(strict_types=1);

namespace App\Services\Company;

use App\Models\Company;
use App\Models\Job;

class CompanyResolutionService
{
    public function resolveForJob(Job $job, ?string $sourceCompanyName = null): ?Company
    {
        // TODO: Resolve or create company linkage for scraped/internal jobs.
        throw new \LogicException('Not implemented.');
    }
}

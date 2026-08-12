<?php

declare(strict_types=1);

namespace App\Services\Scraper;

use App\Models\Job;
use App\Models\JobSource;

class DuplicateDetectionService
{
    public function findExisting(JobSource $source, string $externalId, ?string $contentHash): ?Job
    {
        return Job::query()
            ->where('job_source_id', $source->id)
            ->where('external_id', $externalId)
            ->first();
    }
}

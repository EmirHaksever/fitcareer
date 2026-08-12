<?php

declare(strict_types=1);

namespace App\Services\JobReport;

use App\Enums\JobReportReason;
use App\Enums\JobReportStatus;
use App\Models\JobReport;
use App\Models\User;

class JobReportService
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(int $jobId, User $user, JobReportReason $reason, array $payload = []): JobReport
    {
        // TODO: Persist user job report with initial reported status.
        throw new \LogicException('Not implemented.');
    }

    public function transitionStatus(
        int $jobReportId,
        JobReportStatus $toStatus,
        ?User $actor = null,
        ?string $adminNote = null,
    ): JobReport {
        // TODO: transition matrix + lockForUpdate + transaction per Plan v4 section 10.
        throw new \LogicException('Not implemented.');
    }
}

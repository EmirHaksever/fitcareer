<?php

declare(strict_types=1);

namespace App\Events;

use App\Enums\JobReportStatus;
use App\Models\JobReport;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class JobReportStatusChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public JobReport $jobReport,
        public JobReportStatus $fromStatus,
        public JobReportStatus $toStatus,
    ) {}
}

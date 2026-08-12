<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\TrustAnalysisStatus;
use App\Events\JobTrustAnalysisCompleted;
use App\Events\JobTrustAnalysisFailed;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class UpdateJobTrustFieldsListener implements ShouldHandleEventsAfterCommit
{
    public function handleJobTrustAnalysisCompleted(JobTrustAnalysisCompleted $event): void
    {
        // Async AI pipeline may dispatch this event in a later phase.
    }

    public function handleJobTrustAnalysisFailed(JobTrustAnalysisFailed $event): void
    {
        $event->job->forceFill([
            'trust_analysis_status' => TrustAnalysisStatus::Failed,
        ])->save();
    }
}

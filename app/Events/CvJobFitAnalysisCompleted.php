<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\AiAnalysis;
use App\Models\CandidateProfile;
use App\Models\Job;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CvJobFitAnalysisCompleted implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public CandidateProfile $candidateProfile,
        public Job $job,
        public AiAnalysis $analysis,
    ) {}
}

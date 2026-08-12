<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Enums\TrustAnalysisStatus;
use App\Events\JobTrustAnalysisFailed;
use App\Models\Job;
use App\Services\TrustScore\TrustScoreCalculator;
use Illuminate\Support\Facades\Log;

class JobTrustAnalysisService
{
    public function __construct(
        private readonly TrustScoreCalculator $trustScoreCalculator,
    ) {}

    public function analyze(Job $job): void
    {
        $job->loadMissing(['company', 'sourceProvider']);

        try {
            $result = $this->trustScoreCalculator->calculate($job);

            $job->forceFill([
                'trust_score' => $result->score,
                'trust_label' => $result->label,
                'trust_analysis_status' => TrustAnalysisStatus::Completed,
            ])->save();
        } catch (\Throwable $exception) {
            Log::error('Job trust analysis failed.', [
                'job_id' => $job->id,
                'message' => $exception->getMessage(),
            ]);

            JobTrustAnalysisFailed::dispatch($job->fresh());
        }
    }
}

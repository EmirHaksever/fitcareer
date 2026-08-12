<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\AiAnalysisStatus;
use App\Enums\AiAnalysisType;
use App\Models\AiAnalysis;
use App\Models\CandidateProfile;
use App\Models\Job;
use App\Services\AI\CvJobFitAnalysisService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class AnalyzeCvJobFitJob implements ShouldBeUnique, ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly int $candidateProfileId,
        public readonly int $jobId,
    ) {}

    public function uniqueId(): string
    {
        return 'cv-job-fit:'.$this->candidateProfileId.':'.$this->jobId;
    }

    public function handle(CvJobFitAnalysisService $cvJobFitAnalysisService): void
    {
        $candidateProfile = CandidateProfile::query()->find($this->candidateProfileId);
        $job = Job::query()->find($this->jobId);

        if ($candidateProfile === null || $job === null) {
            return;
        }

        $cvJobFitAnalysisService->analyze($candidateProfile, $job);
    }

    public function failed(?\Throwable $exception): void
    {
        Log::error('CV job fit queue analysis failed.', [
            'candidate_profile_id' => $this->candidateProfileId,
            'job_id' => $this->jobId,
            'message' => $exception?->getMessage(),
        ]);

        AiAnalysis::query()
            ->where('type', AiAnalysisType::CvJobFit)
            ->where('candidate_profile_id', $this->candidateProfileId)
            ->where('job_id', $this->jobId)
            ->where('is_latest', true)
            ->where('status', AiAnalysisStatus::Pending)
            ->update(['status' => AiAnalysisStatus::Failed]);
    }
}

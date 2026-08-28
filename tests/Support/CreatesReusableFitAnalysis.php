<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Enums\AiAnalysisStatus;
use App\Enums\AiAnalysisType;
use App\Models\AiAnalysis;
use App\Models\CandidateProfile;
use App\Models\Job;
use App\Services\FitScore\FitScoreInputFingerprint;

trait CreatesReusableFitAnalysis
{
    protected function seedCandidateCv(CandidateProfile $profile): void
    {
        if ($profile->cv_file_path === null) {
            $profile->update(['cv_file_path' => 'candidate/cvs/test.pdf']);
        }

        $profile->refresh();
    }

    protected function createReusableFitAnalysis(
        CandidateProfile $profile,
        Job $job,
        int $score = 80,
    ): AiAnalysis {
        $this->seedCandidateCv($profile);
        $profile->loadMissing(['candidateSkills', 'skills', 'experiences']);
        $job->loadMissing('skills');

        return AiAnalysis::query()->create([
            'type' => AiAnalysisType::CvJobFit,
            'job_id' => $job->id,
            'candidate_profile_id' => $profile->id,
            'score' => $score,
            'status' => AiAnalysisStatus::Completed,
            'is_latest' => true,
            'analysis_version' => config('fit_score.version'),
            'details' => [
                'input_fingerprint' => FitScoreInputFingerprint::generate($profile, $job),
            ],
            'analyzed_at' => now(),
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Enums\AiAnalysisStatus;
use App\Enums\AiAnalysisType;
use App\Jobs\AnalyzeCvJobFitJob;
use App\Models\AiAnalysis;
use App\Models\CandidateProfile;
use App\Models\Job;
use App\Services\FitScore\FitScoreInputFingerprint;
use App\Support\JobScorePresenter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CvJobFitAnalysisQueueDispatcher
{
    /**
     * @param  Collection<int, Job>  $jobs
     */
    public function dispatchForJobs(CandidateProfile $candidateProfile, Collection $jobs): void
    {
        if ($jobs->isEmpty()) {
            return;
        }

        $candidateProfile->loadMissing(['candidateSkills', 'skills', 'experiences']);

        foreach ($jobs as $job) {
            $job->loadMissing(['skills']);

            $existing = JobScorePresenter::resolveFitAnalysis($job, $candidateProfile->id);

            if ($existing !== null && FitScoreInputFingerprint::isReusable($existing, $candidateProfile, $job)) {
                continue;
            }

            if ($existing !== null && $this->hasValidPendingAnalysis($existing, $candidateProfile, $job)) {
                AnalyzeCvJobFitJob::dispatch($candidateProfile->id, $job->id);

                continue;
            }

            $pending = $this->ensurePendingAnalysis($candidateProfile, $job, $existing);
            $job->setRelation('analyses', collect([$pending]));

            AnalyzeCvJobFitJob::dispatch($candidateProfile->id, $job->id);
        }
    }

    private function hasValidPendingAnalysis(
        AiAnalysis $analysis,
        CandidateProfile $candidateProfile,
        Job $job,
    ): bool {
        if ($analysis->status !== AiAnalysisStatus::Pending || ! $analysis->is_latest) {
            return false;
        }

        $storedFingerprint = $analysis->details['input_fingerprint'] ?? null;
        $currentFingerprint = FitScoreInputFingerprint::generate($candidateProfile, $job);

        return is_string($storedFingerprint)
            && $storedFingerprint !== ''
            && hash_equals($storedFingerprint, $currentFingerprint);
    }

    private function ensurePendingAnalysis(
        CandidateProfile $candidateProfile,
        Job $job,
        ?AiAnalysis $existing,
    ): AiAnalysis {
        $metadata = FitScoreInputFingerprint::metadata($candidateProfile, $job);

        if ($existing !== null && $this->hasValidPendingAnalysis($existing, $candidateProfile, $job)) {
            return $existing;
        }

        return DB::transaction(function () use ($candidateProfile, $job, $existing, $metadata): AiAnalysis {
            if ($existing !== null && $existing->is_latest) {
                AiAnalysis::query()
                    ->where('id', $existing->id)
                    ->lockForUpdate()
                    ->update(['is_latest' => false]);
            }

            return AiAnalysis::query()->create([
                'type' => AiAnalysisType::CvJobFit,
                'job_id' => $job->id,
                'candidate_profile_id' => $candidateProfile->id,
                'score' => null,
                'label' => null,
                'summary' => null,
                'details' => $metadata,
                'ai_model' => null,
                'analysis_version' => (string) config('fit_score.version'),
                'prompt_version' => null,
                'raw_response' => null,
                'status' => AiAnalysisStatus::Pending,
                'is_latest' => true,
                'analyzed_at' => null,
            ]);
        });
    }
}

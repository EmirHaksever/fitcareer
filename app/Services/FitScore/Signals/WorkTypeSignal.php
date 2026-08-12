<?php

declare(strict_types=1);

namespace App\Services\FitScore\Signals;

use App\Enums\WorkPreference;
use App\Enums\WorkType;
use App\Models\CandidateProfile;
use App\Models\Job;
use App\Services\FitScore\Contracts\FitSignalInterface;
use App\Services\TrustScore\SignalResult;

final class WorkTypeSignal implements FitSignalInterface
{
    public function key(): string
    {
        return 'work_type';
    }

    public function evaluate(CandidateProfile $candidate, Job $job): SignalResult
    {
        $jobWorkType = $job->work_type;
        $candidatePreference = $candidate->work_preference;

        if ($jobWorkType === null || $candidatePreference === null) {
            return new SignalResult(null, 0.0, [
                'reason' => $jobWorkType === null ? 'job_work_type_missing' : 'candidate_work_preference_missing',
                'job_work_type' => $jobWorkType?->value,
                'candidate_work_preference' => $candidatePreference?->value,
            ]);
        }

        if ($candidatePreference === WorkPreference::Any) {
            return new SignalResult(
                (int) config('fit_score.thresholds.work_type.any_preference'),
                (float) config('fit_score.fallback_confidence'),
                [
                    'job_work_type' => $jobWorkType->value,
                    'candidate_work_preference' => $candidatePreference->value,
                    'match_type' => 'any',
                ],
            );
        }

        if ($candidatePreference->value === $jobWorkType->value) {
            return new SignalResult(
                (int) config('fit_score.thresholds.work_type.exact_match'),
                (float) config('fit_score.fallback_confidence'),
                [
                    'job_work_type' => $jobWorkType->value,
                    'candidate_work_preference' => $candidatePreference->value,
                    'match_type' => 'exact',
                ],
            );
        }

        $compatibility = config('fit_score.thresholds.work_type.compatibility');
        $score = (int) ($compatibility[$jobWorkType->value][$candidatePreference->value]
            ?? config('fit_score.thresholds.work_type.mismatch'));

        return new SignalResult($score, (float) config('fit_score.fallback_confidence'), [
            'job_work_type' => $jobWorkType->value,
            'candidate_work_preference' => $candidatePreference->value,
            'match_type' => $score > 0 ? 'partial' : 'mismatch',
        ]);
    }
}

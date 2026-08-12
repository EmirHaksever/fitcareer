<?php

declare(strict_types=1);

namespace App\Services\FitScore\Signals;

use App\Models\CandidateProfile;
use App\Models\Job;
use App\Services\FitScore\Contracts\FitSignalInterface;
use App\Services\TrustScore\SignalResult;

final class ExperienceLevelSignal implements FitSignalInterface
{
    public function key(): string
    {
        return 'experience';
    }

    public function evaluate(CandidateProfile $candidate, Job $job): SignalResult
    {
        $experienceLevel = $job->experience_level;
        $candidateYears = $candidate->years_of_experience;

        if ($experienceLevel === null || $candidateYears === null) {
            return new SignalResult(null, 0.0, [
                'reason' => $experienceLevel === null ? 'job_experience_level_missing' : 'candidate_experience_missing',
                'job_experience_level' => $experienceLevel?->value,
                'candidate_years_of_experience' => $candidateYears,
            ]);
        }

        $thresholds = config('fit_score.thresholds.experience');
        $levelConfig = $thresholds['levels'][$experienceLevel->value] ?? null;

        if ($levelConfig === null) {
            return new SignalResult(null, 0.0, [
                'reason' => 'unknown_experience_level',
                'job_experience_level' => $experienceLevel->value,
            ]);
        }

        $minYears = (int) $levelConfig['min_years'];
        $idealYears = (int) $levelConfig['ideal_years'];
        $gap = max(0, $minYears - $candidateYears);

        $score = match (true) {
            $candidateYears >= $idealYears => (int) $thresholds['meets_or_exceeds'],
            $candidateYears >= $minYears => (int) $thresholds['slightly_below'],
            $gap <= (int) $thresholds['slightly_below_gap_years'] => (int) $thresholds['slightly_below'],
            default => (int) $thresholds['well_below'],
        };

        return new SignalResult($score, (float) config('fit_score.fallback_confidence'), [
            'job_experience_level' => $experienceLevel->value,
            'candidate_years_of_experience' => $candidateYears,
            'required_min_years' => $minYears,
            'ideal_years' => $idealYears,
            'gap_years' => $gap,
        ]);
    }
}

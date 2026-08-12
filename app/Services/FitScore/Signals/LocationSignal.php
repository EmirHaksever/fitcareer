<?php

declare(strict_types=1);

namespace App\Services\FitScore\Signals;

use App\Enums\WorkType;
use App\Models\CandidateProfile;
use App\Models\Job;
use App\Services\FitScore\Contracts\FitSignalInterface;
use App\Services\TrustScore\SignalResult;

final class LocationSignal implements FitSignalInterface
{
    public function key(): string
    {
        return 'location';
    }

    public function evaluate(CandidateProfile $candidate, Job $job): SignalResult
    {
        if ($job->work_type === WorkType::Remote) {
            return new SignalResult(
                (int) config('fit_score.thresholds.location.remote_bypass_score'),
                (float) config('fit_score.fallback_confidence'),
                [
                    'reason' => 'remote_job_bypass',
                    'job_work_type' => $job->work_type->value,
                ],
            );
        }

        $jobCity = $this->normalize($job->city);
        $jobCountry = $this->normalize($job->country);
        $candidateCity = $this->normalize($candidate->city);
        $candidateCountry = $this->normalize($candidate->country);

        if ($jobCountry === null || $candidateCountry === null) {
            return new SignalResult(null, 0.0, [
                'reason' => 'location_data_missing',
                'job_city' => $job->city,
                'job_country' => $job->country,
                'candidate_city' => $candidate->city,
                'candidate_country' => $candidate->country,
            ]);
        }

        if ($jobCity !== null && $candidateCity !== null && $jobCity === $candidateCity && $jobCountry === $candidateCountry) {
            return new SignalResult(
                (int) config('fit_score.thresholds.location.same_city'),
                (float) config('fit_score.fallback_confidence'),
                [
                    'match_type' => 'same_city',
                    'job_city' => $job->city,
                    'job_country' => $job->country,
                    'candidate_city' => $candidate->city,
                    'candidate_country' => $candidate->country,
                ],
            );
        }

        if ($jobCountry === $candidateCountry) {
            return new SignalResult(
                (int) config('fit_score.thresholds.location.same_country'),
                (float) config('fit_score.fallback_confidence'),
                [
                    'match_type' => 'same_country',
                    'job_city' => $job->city,
                    'job_country' => $job->country,
                    'candidate_city' => $candidate->city,
                    'candidate_country' => $candidate->country,
                ],
            );
        }

        return new SignalResult(
            (int) config('fit_score.thresholds.location.different_country'),
            (float) config('fit_score.fallback_confidence'),
            [
                'match_type' => 'different_country',
                'job_city' => $job->city,
                'job_country' => $job->country,
                'candidate_city' => $candidate->city,
                'candidate_country' => $candidate->country,
            ],
        );
    }

    private function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = mb_strtolower(trim($value));

        return $normalized === '' ? null : $normalized;
    }
}

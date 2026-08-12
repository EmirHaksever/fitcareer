<?php

declare(strict_types=1);

namespace App\Services\FitScore\Signals;

use App\Models\CandidateProfile;
use App\Models\Job;
use App\Services\FitScore\Contracts\FitSignalInterface;
use App\Services\TrustScore\SignalResult;

final class SalarySignal implements FitSignalInterface
{
    public function key(): string
    {
        return 'salary';
    }

    public function evaluate(CandidateProfile $candidate, Job $job): SignalResult
    {
        if (! $job->is_salary_visible) {
            return new SignalResult(null, 0.0, [
                'reason' => 'salary_not_visible',
            ]);
        }

        $candidateMin = $this->toFloat($candidate->desired_salary_min);
        $candidateMax = $this->toFloat($candidate->desired_salary_max);
        $jobMin = $this->toFloat($job->salary_min);
        $jobMax = $this->toFloat($job->salary_max);

        if ($candidateMin === null || $candidateMax === null || $jobMin === null || $jobMax === null) {
            return new SignalResult(null, 0.0, [
                'reason' => 'salary_data_incomplete',
                'candidate_desired_salary_min' => $candidate->desired_salary_min,
                'candidate_desired_salary_max' => $candidate->desired_salary_max,
                'job_salary_min' => $job->salary_min,
                'job_salary_max' => $job->salary_max,
            ]);
        }

        $jobCurrency = $this->normalizeCurrency($job->salary_currency);

        if ($jobCurrency === null) {
            return new SignalResult(null, 0.0, [
                'reason' => 'job_currency_missing',
            ]);
        }

        if ($candidateMin > $candidateMax || $jobMin > $jobMax) {
            return new SignalResult(null, 0.0, [
                'reason' => 'invalid_salary_range',
            ]);
        }

        $overlapStart = max($candidateMin, $jobMin);
        $overlapEnd = min($candidateMax, $jobMax);

        if ($overlapEnd < $overlapStart) {
            return new SignalResult(
                (int) config('fit_score.thresholds.salary.no_overlap'),
                (float) config('fit_score.fallback_confidence'),
                [
                    'candidate_range' => [$candidateMin, $candidateMax],
                    'job_range' => [$jobMin, $jobMax],
                    'overlap' => false,
                    'currency' => $jobCurrency,
                ],
            );
        }

        $candidateSpan = max($candidateMax - $candidateMin, 1.0);
        $overlapSpan = $overlapEnd - $overlapStart;
        $ratio = min(1.0, $overlapSpan / $candidateSpan);
        $score = (int) round($ratio * (int) config('fit_score.thresholds.salary.full_overlap'));

        return new SignalResult($score, (float) config('fit_score.fallback_confidence'), [
            'candidate_range' => [$candidateMin, $candidateMax],
            'job_range' => [$jobMin, $jobMax],
            'overlap' => true,
            'overlap_range' => [$overlapStart, $overlapEnd],
            'currency' => $jobCurrency,
        ]);
    }

    private function toFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }

    private function normalizeCurrency(?string $currency): ?string
    {
        if ($currency === null) {
            return null;
        }

        $normalized = strtoupper(trim($currency));

        return $normalized === '' ? null : $normalized;
    }
}

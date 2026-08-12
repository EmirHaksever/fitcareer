<?php

declare(strict_types=1);

namespace App\Services\TrustScore\Signals;

use App\Models\Job;
use App\Services\TrustScore\Contracts\TrustSignalInterface;
use App\Services\TrustScore\SignalResult;

final class SalaryTransparencySignal implements TrustSignalInterface
{
    public function key(): string
    {
        return 'salary_transparency';
    }

    public function evaluate(Job $job): SignalResult
    {
        if (! $job->is_salary_visible) {
            return new SignalResult(null, 0.0, [
                'reason' => 'salary_hidden',
                'is_salary_visible' => false,
            ]);
        }

        if ($job->salary_min === null || $job->salary_max === null) {
            return new SignalResult(45, 0.7, [
                'reason' => 'incomplete_salary_range',
                'salary_min' => $job->salary_min,
                'salary_max' => $job->salary_max,
            ]);
        }

        if ((float) $job->salary_max < (float) $job->salary_min) {
            return new SignalResult(30, 0.8, [
                'reason' => 'invalid_salary_range',
            ]);
        }

        return new SignalResult(90, 1.0, [
            'is_salary_visible' => true,
            'salary_min' => $job->salary_min,
            'salary_max' => $job->salary_max,
            'salary_currency' => $job->salary_currency,
        ]);
    }
}

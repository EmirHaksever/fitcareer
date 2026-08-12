<?php

declare(strict_types=1);

namespace App\Services\TrustScore\Signals;

use App\Models\Job;
use App\Services\TrustScore\Contracts\TrustSignalInterface;
use App\Services\TrustScore\SignalResult;
use Illuminate\Support\Carbon;

final class JobFreshnessSignal implements TrustSignalInterface
{
    public function key(): string
    {
        return 'job_freshness';
    }

    public function evaluate(Job $job): SignalResult
    {
        $now = now();

        if ($job->expires_at !== null && $job->expires_at->isPast()) {
            return new SignalResult(15, 1.0, [
                'reason' => 'expired',
                'expires_at' => $job->expires_at->toIso8601String(),
            ]);
        }

        if ($job->application_deadline !== null && $job->application_deadline->lt(now()->startOfDay())) {
            return new SignalResult(20, 1.0, [
                'reason' => 'application_deadline_passed',
                'application_deadline' => $job->application_deadline->toDateString(),
            ]);
        }

        if ($job->published_at === null) {
            return new SignalResult(null, 0.0, [
                'reason' => 'not_published',
            ]);
        }

        $thresholds = config('trust_score.thresholds.freshness');
        $ageInDays = $job->published_at->diffInDays($now);

        if ($ageInDays <= (int) $thresholds['fresh_days']) {
            $score = 95;
        } elseif ($ageInDays <= (int) $thresholds['stale_days']) {
            $score = 70;
        } else {
            $score = 45;
        }

        return new SignalResult($score, 1.0, [
            'published_at' => $job->published_at->toIso8601String(),
            'age_in_days' => $ageInDays,
        ]);
    }
}

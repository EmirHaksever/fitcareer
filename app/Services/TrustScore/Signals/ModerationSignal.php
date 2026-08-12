<?php

declare(strict_types=1);

namespace App\Services\TrustScore\Signals;

use App\Enums\JobStatus;
use App\Models\Job;
use App\Services\TrustScore\Contracts\TrustSignalInterface;
use App\Services\TrustScore\SignalResult;

final class ModerationSignal implements TrustSignalInterface
{
    public function key(): string
    {
        return 'moderation';
    }

    public function evaluate(Job $job): SignalResult
    {
        if ($job->status !== JobStatus::Flagged) {
            return new SignalResult(null, 0.0, [
                'reason' => 'not_flagged',
                'status' => $job->status->value,
            ]);
        }

        return new SignalResult(10, 1.0, [
            'status' => $job->status->value,
        ]);
    }
}

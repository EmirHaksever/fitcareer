<?php

declare(strict_types=1);

namespace App\Services\FitScore\Contracts;

use App\Models\CandidateProfile;
use App\Models\Job;
use App\Services\TrustScore\SignalResult;

interface FitSignalInterface
{
    public function key(): string;

    public function evaluate(CandidateProfile $candidate, Job $job): SignalResult;
}

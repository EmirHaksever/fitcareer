<?php

declare(strict_types=1);

namespace App\Services\TrustScore\Contracts;

use App\Models\Job;
use App\Services\TrustScore\SignalResult;

interface TrustSignalInterface
{
    public function key(): string;

    public function evaluate(Job $job): SignalResult;
}

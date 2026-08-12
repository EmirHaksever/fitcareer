<?php

declare(strict_types=1);

namespace App\Services\Job;

use App\Models\Job;
use App\Models\JobRefreshRequest;
use App\Models\User;

class JobRefreshService
{
    public function requestRefresh(Job $job, User $user): JobRefreshRequest
    {
        // TODO: Enforce global and per-job cooldown limits before queue dispatch.
        throw new \LogicException('Not implemented.');
    }
}

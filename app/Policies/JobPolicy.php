<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\JobOrigin;
use App\Enums\JobStatus;
use App\Enums\UserRole;
use App\Models\Job;
use App\Models\User;

class JobPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::Company && $user->company !== null;
    }

    public function view(User $user, Job $job): bool
    {
        if ($job->status === JobStatus::Published) {
            return true;
        }

        return $this->ownsInternalJob($user, $job);
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::Company && $user->company !== null;
    }

    public function update(User $user, Job $job): bool
    {
        return $this->ownsInternalJob($user, $job)
            && in_array($job->status, [JobStatus::Draft, JobStatus::PendingReview], true);
    }

    public function publish(User $user, Job $job): bool
    {
        return $this->ownsInternalJob($user, $job)
            && $job->status === JobStatus::Draft;
    }

    public function manageSkills(User $user, Job $job): bool
    {
        return $this->ownsInternalJob($user, $job)
            && in_array($job->status, [JobStatus::Draft, JobStatus::PendingReview], true);
    }

    public function manageFitScoreSettings(User $user, Job $job): bool
    {
        return $this->manageSkills($user, $job);
    }

    private function ownsInternalJob(User $user, Job $job): bool
    {
        return $user->role === UserRole::Company
            && $user->company !== null
            && $job->source === JobOrigin::Internal
            && $job->company_id === $user->company->id;
    }
}

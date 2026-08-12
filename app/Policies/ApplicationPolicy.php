<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\JobOrigin;
use App\Enums\UserRole;
use App\Models\Application;
use App\Models\User;

class ApplicationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::Company && $user->company !== null;
    }

    public function view(User $user, Application $application): bool
    {
        return $this->ownsApplicationJob($user, $application);
    }

    public function transition(User $user, Application $application): bool
    {
        return $this->ownsApplicationJob($user, $application);
    }

    private function ownsApplicationJob(User $user, Application $application): bool
    {
        if ($user->role !== UserRole::Company || $user->company === null) {
            return false;
        }

        $job = $application->job;

        if ($job === null) {
            return false;
        }

        return $job->source === JobOrigin::Internal
            && $job->company_id === $user->company->id;
    }
}

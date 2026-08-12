<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\CandidateProfile;
use App\Models\User;

class CandidateProfilePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::Candidate && $user->candidateProfile !== null;
    }

    public function view(User $user, CandidateProfile $candidateProfile): bool
    {
        return $user->role === UserRole::Candidate
            && $user->candidateProfile?->is($candidateProfile);
    }

    public function update(User $user, CandidateProfile $candidateProfile): bool
    {
        return $this->view($user, $candidateProfile);
    }
}

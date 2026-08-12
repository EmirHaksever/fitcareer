<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\User;

class CompanyPolicy
{
    public function view(User $user, Company $company): bool
    {
        return $user->role === UserRole::Company
            && $user->company?->is($company);
    }

    public function update(User $user, Company $company): bool
    {
        return $this->view($user, $company);
    }

    public function requestVerification(User $user, Company $company): bool
    {
        return $this->view($user, $company);
    }
}

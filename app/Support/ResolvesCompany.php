<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Company;
use App\Models\User;

trait ResolvesCompany
{
    protected function resolveCompany(User $user): Company
    {
        $company = $user->company;

        if ($company === null) {
            abort(404, 'Company profile not found.');
        }

        return $company;
    }
}

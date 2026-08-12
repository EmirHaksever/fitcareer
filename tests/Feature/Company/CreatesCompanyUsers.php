<?php

namespace Tests\Feature\Company;

use App\Enums\UserRole;
use App\Models\CandidateProfile;
use App\Models\Company;
use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;

trait CreatesCompanyUsers
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Company, 2: string}
     */
    protected function createCompanyActor(): array
    {
        $user = User::factory()->company()->create();
        $company = Company::factory()->create([
            'user_id' => $user->id,
        ]);
        UserSetting::query()->create(['user_id' => $user->id]);

        $token = $user->createToken('api-access')->plainTextToken;

        return [$user, $company, $token];
    }

    protected function createCandidateActorToken(): string
    {
        $user = User::factory()->create(['role' => UserRole::Candidate]);
        CandidateProfile::factory()->create(['user_id' => $user->id]);
        UserSetting::query()->create(['user_id' => $user->id]);

        return $user->createToken('api-access')->plainTextToken;
    }

    protected function createAdminActorToken(): string
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);

        return $user->createToken('api-access')->plainTextToken;
    }
}

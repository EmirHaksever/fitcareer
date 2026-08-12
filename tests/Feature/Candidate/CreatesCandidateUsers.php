<?php

namespace Tests\Feature\Candidate;

use App\Enums\UserRole;
use App\Models\CandidateProfile;
use App\Models\Company;
use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;

trait CreatesCandidateUsers
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: CandidateProfile, 2: string}
     */
    protected function createCandidateActor(): array
    {
        $user = User::factory()->create([
            'role' => UserRole::Candidate,
        ]);

        $profile = CandidateProfile::factory()->create([
            'user_id' => $user->id,
        ]);

        UserSetting::query()->create(['user_id' => $user->id]);

        $token = $user->createToken('api-access')->plainTextToken;

        return [$user, $profile, $token];
    }

    protected function createCompanyActor(): string
    {
        $user = User::factory()->company()->create();
        Company::query()->create([
            'user_id' => $user->id,
            'name' => 'Test Company',
            'slug' => 'test-company-'.uniqid(),
        ]);

        return $user->createToken('api-access')->plainTextToken;
    }

    protected function createAdminActor(): string
    {
        $user = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        return $user->createToken('api-access')->plainTextToken;
    }
}

<?php

namespace Tests\Feature\Candidate;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CandidateAuthorizationTest extends TestCase
{
    use CreatesCandidateUsers;

    #[Test]
    public function guest_cannot_access_candidate_profile(): void
    {
        $this->getJson('/api/v1/candidate/profile')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    #[Test]
    public function company_user_cannot_access_candidate_profile(): void
    {
        $token = $this->createCompanyActor();

        $this->withToken($token)
            ->getJson('/api/v1/candidate/profile')
            ->assertForbidden()
            ->assertJsonPath('message', 'Forbidden.');
    }

    #[Test]
    public function admin_user_cannot_access_candidate_profile(): void
    {
        $token = $this->createAdminActor();

        $this->withToken($token)
            ->getJson('/api/v1/candidate/profile')
            ->assertForbidden()
            ->assertJsonPath('message', 'Forbidden.');
    }

    #[Test]
    public function candidate_cannot_access_another_candidates_experience(): void
    {
        [, $profile, $token] = $this->createCandidateActor();
        [, $otherProfile] = $this->createCandidateActor();

        $experience = $profile->experiences()->create([
            'company_name' => 'Owned Co',
            'position_title' => 'Engineer',
            'start_date' => '2020-01-01',
            'is_current' => true,
        ]);

        $foreignExperience = $otherProfile->experiences()->create([
            'company_name' => 'Foreign Co',
            'position_title' => 'Engineer',
            'start_date' => '2021-01-01',
            'is_current' => true,
        ]);

        $this->withToken($token)
            ->getJson('/api/v1/candidate/experiences')
            ->assertOk()
            ->assertJsonPath('data.0.id', $experience->id)
            ->assertJsonMissing(['company_name' => 'Foreign Co']);

        $this->withToken($token)
            ->deleteJson('/api/v1/candidate/experiences/'.$foreignExperience->id)
            ->assertNotFound();
    }
}

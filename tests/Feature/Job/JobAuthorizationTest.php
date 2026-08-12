<?php

namespace Tests\Feature\Job;

use App\Models\Company;
use App\Models\Job;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class JobAuthorizationTest extends TestCase
{
    use CreatesJobActors;

    #[Test]
    public function guest_can_search_jobs(): void
    {
        Job::factory()->published()->create();

        $this->getJson('/api/v1/jobs')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    #[Test]
    public function candidate_cannot_create_company_jobs(): void
    {
        [, , $token] = $this->createCandidateActor();

        $this->withToken($token)
            ->postJson('/api/v1/company/jobs', [
                'title' => 'Backend Developer',
                'description' => 'Build APIs',
                'employment_type' => 'full_time',
                'work_type' => 'remote',
            ])
            ->assertForbidden();
    }

    #[Test]
    public function admin_cannot_create_company_jobs(): void
    {
        $this->withToken($this->createAdminActorToken())
            ->postJson('/api/v1/company/jobs', [
                'title' => 'Backend Developer',
                'description' => 'Build APIs',
                'employment_type' => 'full_time',
                'work_type' => 'remote',
            ])
            ->assertForbidden();
    }

    #[Test]
    public function company_cannot_modify_another_companys_job(): void
    {
        [, $companyA, $tokenA] = $this->createCompanyActor();
        $companyB = Company::factory()->create();
        $foreignJob = Job::factory()->draft()->create(['company_id' => $companyB->id]);

        $this->withToken($tokenA)
            ->putJson('/api/v1/company/jobs/'.$foreignJob->id, [
                'title' => 'Hijacked Title',
            ])
            ->assertNotFound();

        $this->assertNotSame('Hijacked Title', $foreignJob->fresh()->title);
        $this->assertSame($companyB->id, $companyA->id === $companyB->id ? $companyA->id : $foreignJob->company_id);
    }
}

<?php

namespace Tests\Feature\Job;

use App\Enums\JobStatus;
use App\Models\Job;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class JobCompanyCrudTest extends TestCase
{
    use CreatesJobActors;

    #[Test]
    public function company_can_create_update_and_publish_job(): void
    {
        [, $company, $token] = $this->createCompanyActor();

        $createResponse = $this->withToken($token)
            ->postJson('/api/v1/company/jobs', [
                'title' => 'Backend Developer',
                'description' => 'Build reliable Laravel APIs for production systems.',
                'employment_type' => 'full_time',
                'work_type' => 'remote',
                'category' => 'engineering',
                'city' => 'Istanbul',
                'country' => 'Turkey',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', JobStatus::Draft->value)
            ->assertJsonPath('data.source', 'internal');

        $jobId = $createResponse->json('data.id');

        $this->withToken($token)
            ->putJson('/api/v1/company/jobs/'.$jobId, [
                'title' => 'Senior Backend Developer',
                'description' => 'Lead backend engineering initiatives with Laravel.',
            ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Senior Backend Developer')
            ->assertJsonPath('data.slug', 'senior-backend-developer');

        $this->withToken($token)
            ->postJson('/api/v1/company/jobs/'.$jobId.'/publish')
            ->assertOk()
            ->assertJsonPath('data.status', JobStatus::Published->value);

        $this->assertDatabaseHas('jobs', [
            'id' => $jobId,
            'company_id' => $company->id,
            'status' => JobStatus::Published->value,
        ]);

        $this->getJson('/api/v1/jobs/senior-backend-developer')
            ->assertOk()
            ->assertJsonPath('data.slug', 'senior-backend-developer');
    }

    #[Test]
    public function protected_fields_cannot_be_mass_assigned_on_job_create(): void
    {
        [, , $token] = $this->createCompanyActor();

        $this->withToken($token)
            ->postJson('/api/v1/company/jobs', [
                'title' => 'Backend Developer',
                'description' => 'Build APIs',
                'employment_type' => 'full_time',
                'work_type' => 'remote',
                'status' => JobStatus::Published->value,
                'trust_score' => 99,
                'company_id' => 999,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status', 'trust_score', 'company_id']);
    }

    #[Test]
    public function published_job_cannot_be_updated(): void
    {
        [, $company, $token] = $this->createCompanyActor();
        $job = Job::factory()->published()->create(['company_id' => $company->id]);

        $this->withToken($token)
            ->putJson('/api/v1/company/jobs/'.$job->id, [
                'title' => 'Changed After Publish',
            ])
            ->assertForbidden();
    }

    #[Test]
    public function company_can_list_own_jobs(): void
    {
        [, $company, $token] = $this->createCompanyActor();
        Job::factory()->count(2)->create(['company_id' => $company->id]);

        $this->withToken($token)
            ->getJson('/api/v1/company/jobs')
            ->assertOk()
            ->assertJsonCount(2, 'data.items');
    }
}

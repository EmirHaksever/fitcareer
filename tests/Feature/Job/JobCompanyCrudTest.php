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
                'description' => str_repeat('Build reliable Laravel APIs for production systems. ', 3),
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
                'description' => str_repeat('Lead backend engineering initiatives with Laravel. ', 3),
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
            ->assertJsonPath('data.slug', 'senior-backend-developer')
            ->assertJsonPath('data.source', 'internal')
            ->assertJsonPath('data.company.is_verified', false);
    }

    #[Test]
    public function short_description_is_rejected_on_create(): void
    {
        [, , $token] = $this->createCompanyActor();

        $this->withToken($token)
            ->postJson('/api/v1/company/jobs', [
                'title' => 'Backend Developer',
                'description' => 'x',
                'employment_type' => 'full_time',
                'work_type' => 'onsite',
                'city' => 'Istanbul',
                'country' => 'Turkey',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['description']);
    }

    #[Test]
    public function onsite_job_without_city_is_rejected(): void
    {
        [, , $token] = $this->createCompanyActor();

        $this->withToken($token)
            ->postJson('/api/v1/company/jobs', [
                'title' => 'Junior Backend Developer',
                'description' => str_repeat('Build reliable Laravel APIs for production systems. ', 3),
                'employment_type' => 'full_time',
                'work_type' => 'onsite',
                'country' => 'Turkey',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['city']);
    }

    #[Test]
    public function junior_istanbul_job_is_accepted_without_silent_mid_or_remote_defaults(): void
    {
        [, , $token] = $this->createCompanyActor();

        $this->withToken($token)
            ->postJson('/api/v1/company/jobs', [
                'title' => 'Junior Backend Developer',
                'description' => str_repeat('Entry-level backend role based in Istanbul with mentoring. ', 3),
                'employment_type' => 'full_time',
                'work_type' => 'onsite',
                'experience_level' => 'entry',
                'city' => 'Istanbul',
                'country' => 'Turkey',
            ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Junior Backend Developer')
            ->assertJsonPath('data.experience_level', 'entry')
            ->assertJsonPath('data.work_type', 'onsite')
            ->assertJsonPath('data.city', 'Istanbul')
            ->assertJsonPath('data.source', 'internal');
    }

    #[Test]
    public function omitted_experience_level_is_not_defaulted_to_mid(): void
    {
        [, , $token] = $this->createCompanyActor();

        $this->withToken($token)
            ->postJson('/api/v1/company/jobs', [
                'title' => 'Backend Developer',
                'description' => str_repeat('Build reliable Laravel APIs for production systems. ', 3),
                'employment_type' => 'full_time',
                'work_type' => 'remote',
                'country' => 'Turkey',
            ])
            ->assertCreated()
            ->assertJsonPath('data.experience_level', null)
            ->assertJsonPath('data.work_type', 'remote');
    }

    #[Test]
    public function short_draft_cannot_be_published(): void
    {
        [, $company, $token] = $this->createCompanyActor();
        $job = Job::factory()->draft()->create([
            'company_id' => $company->id,
            'description' => 'x',
            'work_type' => \App\Enums\WorkType::Onsite,
            'city' => 'Istanbul',
            'country' => 'Turkey',
        ]);

        $this->withToken($token)
            ->postJson('/api/v1/company/jobs/'.$job->id.'/publish')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['description']);

        $this->assertSame(\App\Enums\JobStatus::Draft, $job->fresh()->status);
    }

    #[Test]
    public function draft_job_is_not_public_even_for_owning_company(): void
    {
        [, $company, $token] = $this->createCompanyActor();
        $job = Job::factory()->draft()->create([
            'company_id' => $company->id,
            'slug' => 'private-draft-role',
        ]);

        $this->getJson('/api/v1/jobs/'.$job->slug)
            ->assertNotFound();

        $this->withToken($token)
            ->getJson('/api/v1/jobs/'.$job->slug)
            ->assertNotFound();
    }

    #[Test]
    public function published_internal_job_exposes_verified_flag_only_when_company_is_verified(): void
    {
        [, $company, $token] = $this->createCompanyActor();
        $job = Job::factory()->published()->create([
            'company_id' => $company->id,
            'source' => \App\Enums\JobOrigin::Internal,
            'slug' => 'direct-employer-role',
            'category' => 'h1-verified-label-case',
        ]);

        $this->getJson('/api/v1/jobs/'.$job->slug)
            ->assertOk()
            ->assertJsonPath('data.source', 'internal')
            ->assertJsonPath('data.company.is_verified', false);

        $this->getJson('/api/v1/jobs?category=h1-verified-label-case')
            ->assertOk()
            ->assertJsonPath('data.items.0.company.is_verified', false);

        $company->update([
            'is_verified' => true,
            'verification_status' => \App\Enums\CompanyVerificationStatus::Verified,
        ]);

        $this->withToken($token)
            ->getJson('/api/v1/jobs/'.$job->slug)
            ->assertOk()
            ->assertJsonPath('data.company.is_verified', true);
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

<?php

namespace Tests\Feature\Job;

use App\Enums\JobOrigin;
use App\Enums\JobStatus;
use App\Models\Company;
use App\Models\Job;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class JobUnpublishTest extends TestCase
{
    use CreatesJobActors;

    #[Test]
    public function company_can_unpublish_own_published_job(): void
    {
        [, $company, $token] = $this->createCompanyActor();
        $job = Job::factory()->published()->create([
            'company_id' => $company->id,
            'source' => JobOrigin::Internal,
        ]);

        $this->withToken($token)
            ->patchJson('/api/v1/company/jobs/'.$job->id.'/unpublish')
            ->assertOk()
            ->assertJsonPath('data.status', JobStatus::Closed->value);

        $this->assertDatabaseHas('jobs', [
            'id' => $job->id,
            'status' => JobStatus::Closed->value,
        ]);
    }

    #[Test]
    public function draft_job_cannot_be_unpublished(): void
    {
        [, $company, $token] = $this->createCompanyActor();
        $job = Job::factory()->draft()->create([
            'company_id' => $company->id,
            'source' => JobOrigin::Internal,
        ]);

        $this->withToken($token)
            ->patchJson('/api/v1/company/jobs/'.$job->id.'/unpublish')
            ->assertForbidden();

        $this->assertSame(JobStatus::Draft, $job->fresh()->status);
    }

    #[Test]
    public function company_cannot_unpublish_another_companys_job(): void
    {
        [, , $token] = $this->createCompanyActor();
        $otherCompany = Company::factory()->create();
        $foreignJob = Job::factory()->published()->create([
            'company_id' => $otherCompany->id,
            'source' => JobOrigin::Internal,
        ]);

        $this->withToken($token)
            ->patchJson('/api/v1/company/jobs/'.$foreignJob->id.'/unpublish')
            ->assertNotFound();

        $this->assertSame(JobStatus::Published, $foreignJob->fresh()->status);
    }
}

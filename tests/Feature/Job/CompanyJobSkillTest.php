<?php

namespace Tests\Feature\Job;

use App\Enums\JobStatus;
use App\Models\Company;
use App\Models\Job;
use App\Models\Skill;
use App\Models\UserSetting;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CompanyJobSkillTest extends TestCase
{
    use CreatesJobActors;

    #[Test]
    public function company_can_attach_required_skill_to_own_job(): void
    {
        [, $company, $token] = $this->createCompanyActor();
        $job = Job::factory()->draft()->create(['company_id' => $company->id]);
        $skill = Skill::factory()->create(['name' => 'Laravel', 'slug' => 'laravel']);

        $this->withToken($token)
            ->postJson('/api/v1/company/jobs/'.$job->id.'/skills', [
                'skill_id' => $skill->id,
                'importance' => 'required',
            ])
            ->assertCreated()
            ->assertJsonPath('data.id', $skill->id)
            ->assertJsonPath('data.name', 'Laravel')
            ->assertJsonPath('data.importance', 'required');

        $this->assertDatabaseHas('job_skills', [
            'job_id' => $job->id,
            'skill_id' => $skill->id,
            'importance' => 'required',
        ]);
    }

    #[Test]
    public function company_can_attach_preferred_skill_to_own_job(): void
    {
        [, $company, $token] = $this->createCompanyActor();
        $job = Job::factory()->draft()->create(['company_id' => $company->id]);
        $skill = Skill::factory()->create();

        $this->withToken($token)
            ->postJson('/api/v1/company/jobs/'.$job->id.'/skills', [
                'skill_id' => $skill->id,
                'importance' => 'preferred',
            ])
            ->assertCreated()
            ->assertJsonPath('data.importance', 'preferred');
    }

    #[Test]
    public function company_can_list_job_skills(): void
    {
        [, $company, $token] = $this->createCompanyActor();
        $job = Job::factory()->draft()->create(['company_id' => $company->id]);
        $skill = Skill::factory()->create(['name' => 'PHP', 'slug' => 'php']);

        $job->jobSkills()->create([
            'skill_id' => $skill->id,
            'importance' => 'required',
        ]);

        $this->withToken($token)
            ->getJson('/api/v1/company/jobs/'.$job->id.'/skills')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'php')
            ->assertJsonPath('data.0.importance', 'required');
    }

    #[Test]
    public function company_can_replace_job_skills_in_bulk(): void
    {
        [, $company, $token] = $this->createCompanyActor();
        $job = Job::factory()->draft()->create(['company_id' => $company->id]);
        $php = Skill::factory()->create(['name' => 'PHP', 'slug' => 'php']);
        $laravel = Skill::factory()->create(['name' => 'Laravel', 'slug' => 'laravel']);
        $mysql = Skill::factory()->create(['name' => 'MySQL', 'slug' => 'mysql']);

        $job->jobSkills()->create([
            'skill_id' => $php->id,
            'importance' => 'required',
        ]);

        $this->withToken($token)
            ->putJson('/api/v1/company/jobs/'.$job->id.'/skills', [
                'skills' => [
                    ['skill_id' => $laravel->id, 'importance' => 'required'],
                    ['skill_id' => $mysql->id, 'importance' => 'preferred'],
                ],
            ])
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->assertDatabaseMissing('job_skills', [
            'job_id' => $job->id,
            'skill_id' => $php->id,
        ]);
        $this->assertDatabaseHas('job_skills', [
            'job_id' => $job->id,
            'skill_id' => $laravel->id,
            'importance' => 'required',
        ]);
        $this->assertDatabaseHas('job_skills', [
            'job_id' => $job->id,
            'skill_id' => $mysql->id,
            'importance' => 'preferred',
        ]);
    }

    #[Test]
    public function company_can_detach_job_skill(): void
    {
        [, $company, $token] = $this->createCompanyActor();
        $job = Job::factory()->draft()->create(['company_id' => $company->id]);
        $skill = Skill::factory()->create();

        $job->jobSkills()->create([
            'skill_id' => $skill->id,
            'importance' => 'required',
        ]);

        $this->withToken($token)
            ->deleteJson('/api/v1/company/jobs/'.$job->id.'/skills/'.$skill->id)
            ->assertOk();

        $this->assertDatabaseMissing('job_skills', [
            'job_id' => $job->id,
            'skill_id' => $skill->id,
        ]);
    }

    #[Test]
    public function company_cannot_manage_skills_for_foreign_job(): void
    {
        [, , $token] = $this->createCompanyActor();
        $foreignCompany = Company::factory()->create();
        $foreignJob = Job::factory()->draft()->create(['company_id' => $foreignCompany->id]);
        $skill = Skill::factory()->create();

        $this->withToken($token)
            ->postJson('/api/v1/company/jobs/'.$foreignJob->id.'/skills', [
                'skill_id' => $skill->id,
                'importance' => 'required',
            ])
            ->assertNotFound();

        $this->assertDatabaseCount('job_skills', 0);
    }

    #[Test]
    public function candidate_cannot_access_company_job_skill_endpoints(): void
    {
        [, , $token] = $this->createCandidateActor();
        $company = Company::factory()->create();
        $job = Job::factory()->draft()->create(['company_id' => $company->id]);
        $skill = Skill::factory()->create();

        $this->withToken($token)
            ->getJson('/api/v1/company/jobs/'.$job->id.'/skills')
            ->assertForbidden();

        $this->withToken($token)
            ->postJson('/api/v1/company/jobs/'.$job->id.'/skills', [
                'skill_id' => $skill->id,
                'importance' => 'required',
            ])
            ->assertForbidden();
    }

    #[Test]
    public function duplicate_skill_attachment_is_rejected(): void
    {
        [, $company, $token] = $this->createCompanyActor();
        $job = Job::factory()->draft()->create(['company_id' => $company->id]);
        $skill = Skill::factory()->create();

        $job->jobSkills()->create([
            'skill_id' => $skill->id,
            'importance' => 'required',
        ]);

        $this->withToken($token)
            ->postJson('/api/v1/company/jobs/'.$job->id.'/skills', [
                'skill_id' => $skill->id,
                'importance' => 'preferred',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['skill_id']);
    }

    #[Test]
    public function invalid_skill_id_is_rejected(): void
    {
        [, $company, $token] = $this->createCompanyActor();
        $job = Job::factory()->draft()->create(['company_id' => $company->id]);

        $this->withToken($token)
            ->postJson('/api/v1/company/jobs/'.$job->id.'/skills', [
                'skill_id' => 999999,
                'importance' => 'required',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['skill_id']);
    }

    #[Test]
    public function invalid_importance_is_rejected(): void
    {
        [, $company, $token] = $this->createCompanyActor();
        $job = Job::factory()->draft()->create(['company_id' => $company->id]);
        $skill = Skill::factory()->create();

        $this->withToken($token)
            ->postJson('/api/v1/company/jobs/'.$job->id.'/skills', [
                'skill_id' => $skill->id,
                'importance' => 'critical',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['importance']);
    }

    #[Test]
    public function duplicate_skill_ids_in_bulk_sync_are_rejected(): void
    {
        [, $company, $token] = $this->createCompanyActor();
        $job = Job::factory()->draft()->create(['company_id' => $company->id]);
        $skill = Skill::factory()->create();

        $this->withToken($token)
            ->putJson('/api/v1/company/jobs/'.$job->id.'/skills', [
                'skills' => [
                    ['skill_id' => $skill->id, 'importance' => 'required'],
                    ['skill_id' => $skill->id, 'importance' => 'preferred'],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['skills']);
    }

    #[Test]
    public function scraped_job_skill_management_is_not_allowed(): void
    {
        [, $company, $token] = $this->createCompanyActor();
        $job = Job::factory()->scraped()->draft()->create(['company_id' => $company->id]);
        $skill = Skill::factory()->create();

        $this->withToken($token)
            ->postJson('/api/v1/company/jobs/'.$job->id.'/skills', [
                'skill_id' => $skill->id,
                'importance' => 'required',
            ])
            ->assertNotFound();
    }

    #[Test]
    public function company_job_detail_includes_skills_when_loaded(): void
    {
        [, $company, $token] = $this->createCompanyActor();
        $job = Job::factory()->draft()->create(['company_id' => $company->id]);
        $skill = Skill::factory()->create(['name' => 'React', 'slug' => 'react']);

        $job->jobSkills()->create([
            'skill_id' => $skill->id,
            'importance' => 'preferred',
        ]);

        $this->withToken($token)
            ->getJson('/api/v1/company/jobs/'.$job->id)
            ->assertOk()
            ->assertJsonPath('data.skills.0.slug', 'react')
            ->assertJsonPath('data.skills.0.importance', 'preferred');
    }

    #[Test]
    public function candidate_skill_flow_remains_unaffected(): void
    {
        [, $profile, $token] = $this->createCandidateActor();
        $skill = Skill::factory()->create(['name' => 'TypeScript', 'slug' => 'typescript']);

        $this->withToken($token)
            ->postJson('/api/v1/candidate/skills', [
                'skill_id' => $skill->id,
                'proficiency_level' => 'advanced',
            ])
            ->assertCreated()
            ->assertJsonPath('data.skill.slug', 'typescript');

        $this->assertDatabaseHas('candidate_skills', [
            'candidate_profile_id' => $profile->id,
            'skill_id' => $skill->id,
        ]);
    }

    #[Test]
    public function protected_fields_cannot_be_mass_assigned_on_skill_attach(): void
    {
        [, $company, $token] = $this->createCompanyActor();
        $job = Job::factory()->draft()->create(['company_id' => $company->id]);
        $skill = Skill::factory()->create();

        $this->withToken($token)
            ->postJson('/api/v1/company/jobs/'.$job->id.'/skills', [
                'skill_id' => $skill->id,
                'importance' => 'required',
                'job_id' => 999,
                'company_id' => 999,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['job_id', 'company_id']);
    }
}

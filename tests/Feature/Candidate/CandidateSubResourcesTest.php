<?php

namespace Tests\Feature\Candidate;

use App\Enums\EmploymentType;
use App\Enums\ProficiencyLevel;
use App\Models\Skill;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CandidateSubResourcesTest extends TestCase
{
    use CreatesCandidateUsers;

    #[Test]
    public function experience_crud_flow_works(): void
    {
        [, , $token] = $this->createCandidateActor();

        $create = $this->withToken($token)->postJson('/api/v1/candidate/experiences', [
            'company_name' => 'Acme',
            'position_title' => 'Developer',
            'employment_type' => EmploymentType::FullTime->value,
            'start_date' => '2020-01-01',
            'end_date' => '2022-01-01',
            'description' => 'Built APIs',
        ])->assertCreated();

        $experienceId = $create->json('data.id');

        $this->withToken($token)
            ->putJson('/api/v1/candidate/experiences/'.$experienceId, [
                'position_title' => 'Senior Developer',
            ])
            ->assertOk()
            ->assertJsonPath('data.position_title', 'Senior Developer');

        $this->withToken($token)
            ->deleteJson('/api/v1/candidate/experiences/'.$experienceId)
            ->assertOk();

        $this->withToken($token)
            ->getJson('/api/v1/candidate/experiences')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    #[Test]
    public function education_crud_flow_works(): void
    {
        [, , $token] = $this->createCandidateActor();

        $create = $this->withToken($token)->postJson('/api/v1/candidate/educations', [
            'school_name' => 'Tech University',
            'degree' => 'BSc',
            'start_date' => '2015-09-01',
            'end_date' => '2019-06-01',
        ])->assertCreated();

        $educationId = $create->json('data.id');

        $this->withToken($token)
            ->deleteJson('/api/v1/candidate/educations/'.$educationId)
            ->assertOk();
    }

    #[Test]
    public function certification_and_project_crud_flow_works(): void
    {
        [, , $token] = $this->createCandidateActor();

        $certification = $this->withToken($token)->postJson('/api/v1/candidate/certifications', [
            'name' => 'AWS Certified',
            'issuing_organization' => 'AWS',
        ])->assertCreated()->json('data.id');

        $project = $this->withToken($token)->postJson('/api/v1/candidate/projects', [
            'title' => 'FitCareer',
            'technologies' => ['PHP', 'Laravel'],
        ])->assertCreated()->json('data.id');

        $this->withToken($token)->deleteJson('/api/v1/candidate/certifications/'.$certification)->assertOk();
        $this->withToken($token)->deleteJson('/api/v1/candidate/projects/'.$project)->assertOk();
    }

    #[Test]
    public function experience_date_validation_is_enforced(): void
    {
        [, , $token] = $this->createCandidateActor();

        $this->withToken($token)->postJson('/api/v1/candidate/experiences', [
            'company_name' => 'Acme',
            'position_title' => 'Developer',
            'start_date' => '2022-01-01',
            'end_date' => '2020-01-01',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['end_date']);
    }

    #[Test]
    public function invalid_employment_type_is_rejected(): void
    {
        [, , $token] = $this->createCandidateActor();

        $this->withToken($token)->postJson('/api/v1/candidate/experiences', [
            'company_name' => 'Acme',
            'position_title' => 'Developer',
            'employment_type' => 'not-a-real-type',
            'start_date' => '2020-01-01',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['employment_type']);
    }

    #[Test]
    public function skill_attach_update_detach_and_duplicate_rejection_work(): void
    {
        [, , $token] = $this->createCandidateActor();
        $skill = Skill::factory()->create(['name' => 'PHP', 'slug' => 'php']);

        $attach = $this->withToken($token)->postJson('/api/v1/candidate/skills', [
            'skill_id' => $skill->id,
            'proficiency_level' => ProficiencyLevel::Advanced->value,
            'years_of_experience' => 4,
        ])->assertCreated();

        $candidateSkillId = $attach->json('data.id');

        $this->withToken($token)->postJson('/api/v1/candidate/skills', [
            'skill_id' => $skill->id,
            'proficiency_level' => ProficiencyLevel::Beginner->value,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['skill_id']);

        $this->withToken($token)->putJson('/api/v1/candidate/skills/'.$candidateSkillId, [
            'proficiency_level' => ProficiencyLevel::Expert->value,
            'years_of_experience' => 6,
        ])
            ->assertOk()
            ->assertJsonPath('data.proficiency_level', ProficiencyLevel::Expert->value);

        $this->withToken($token)->deleteJson('/api/v1/candidate/skills/'.$candidateSkillId)->assertOk();
    }

    #[Test]
    public function skill_lookup_returns_existing_catalog_skills(): void
    {
        Skill::factory()->create(['name' => 'Laravel', 'slug' => 'laravel']);

        $this->getJson('/api/v1/skills?q=lar')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Laravel');
    }
}

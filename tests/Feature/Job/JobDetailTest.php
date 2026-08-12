<?php

namespace Tests\Feature\Job;

use App\Enums\AiAnalysisStatus;
use App\Enums\AiAnalysisType;
use App\Enums\TrustAnalysisStatus;
use App\Models\AiAnalysis;
use App\Models\Job;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class JobDetailTest extends TestCase
{
    use CreatesJobActors;

    #[Test]
    public function guest_can_view_published_job_detail(): void
    {
        $job = Job::factory()->published()->create([
            'slug' => 'public-backend-role',
            'title' => 'Public Backend Role',
        ]);

        $this->getJson('/api/v1/jobs/public-backend-role')
            ->assertOk()
            ->assertJsonPath('data.slug', 'public-backend-role')
            ->assertJsonPath('data.fit_score', null)
            ->assertJsonPath('data.fit_analysis_status', null)
            ->assertJsonPath('data.fit_details', null);
    }

    #[Test]
    public function draft_job_detail_is_not_public(): void
    {
        Job::factory()->draft()->create(['slug' => 'hidden-draft-role']);

        $this->getJson('/api/v1/jobs/hidden-draft-role')
            ->assertNotFound();
    }

    #[Test]
    public function pending_trust_score_is_null_in_detail_response(): void
    {
        Job::factory()->published()->create([
            'slug' => 'pending-trust-role',
            'trust_score' => null,
            'trust_analysis_status' => TrustAnalysisStatus::Pending,
        ]);

        $this->getJson('/api/v1/jobs/pending-trust-role')
            ->assertOk()
            ->assertJsonPath('data.trust_score', null)
            ->assertJsonPath('data.trust_analysis_status', 'pending');
    }

    #[Test]
    public function candidate_sees_fit_score_only_for_own_profile(): void
    {
        [, $profile, $token] = $this->createCandidateActor();
        $profile->update([
            'work_preference' => \App\Enums\WorkPreference::Remote,
            'years_of_experience' => 5,
        ]);
        $job = Job::factory()->published()->create([
            'slug' => 'fit-score-role',
            'work_type' => \App\Enums\WorkType::Remote,
            'experience_level' => \App\Enums\ExperienceLevel::Mid,
        ]);

        $this->withToken($token)
            ->getJson('/api/v1/jobs/fit-score-role')
            ->assertOk()
            ->assertJsonPath('data.fit_analysis_status', 'completed');

        $this->assertNotNull($this->withToken($token)->getJson('/api/v1/jobs/fit-score-role')->json('data.fit_score'));
    }

    #[Test]
    public function candidate_completed_analysis_includes_fit_details(): void
    {
        [, $profile, $token] = $this->createCandidateActor();
        $profile->update([
            'work_preference' => \App\Enums\WorkPreference::Remote,
            'years_of_experience' => 5,
        ]);

        $job = Job::factory()->published()->create([
            'slug' => 'fit-details-role',
            'work_type' => \App\Enums\WorkType::Remote,
            'experience_level' => \App\Enums\ExperienceLevel::Mid,
        ]);

        $response = $this->withToken($token)
            ->getJson('/api/v1/jobs/fit-details-role')
            ->assertOk()
            ->assertJsonPath('data.fit_analysis_status', 'completed');

        $fitDetails = $response->json('data.fit_details');
        $this->assertIsArray($fitDetails);
        $this->assertArrayHasKey('signals', $fitDetails);
        $this->assertArrayHasKey('work_type', $fitDetails['signals']);
        $this->assertArrayHasKey('score', $fitDetails['signals']['work_type']);
        $this->assertArrayHasKey('evidence', $fitDetails['signals']['work_type']);
    }

    #[Test]
    public function company_user_does_not_receive_fit_details(): void
    {
        [, , $token] = $this->createCompanyActor();
        Job::factory()->published()->create(['slug' => 'company-fit-details-role']);

        $this->withToken($token)
            ->getJson('/api/v1/jobs/company-fit-details-role')
            ->assertOk()
            ->assertJsonPath('data.fit_score', null)
            ->assertJsonPath('data.fit_details', null);
    }

    #[Test]
    public function pending_fit_analysis_is_replaced_on_candidate_view(): void
    {
        [, $profile, $token] = $this->createCandidateActor();
        $profile->update(['work_preference' => \App\Enums\WorkPreference::Any]);
        $job = Job::factory()->published()->create(['slug' => 'pending-fit-role']);

        AiAnalysis::query()->create([
            'type' => AiAnalysisType::CvJobFit,
            'job_id' => $job->id,
            'candidate_profile_id' => $profile->id,
            'score' => 65,
            'status' => AiAnalysisStatus::Pending,
            'is_latest' => true,
        ]);

        $this->withToken($token)
            ->getJson('/api/v1/jobs/pending-fit-role')
            ->assertOk()
            ->assertJsonPath('data.fit_analysis_status', 'completed');
    }
}

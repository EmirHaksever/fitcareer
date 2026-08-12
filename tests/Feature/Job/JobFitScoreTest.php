<?php

namespace Tests\Feature\Job;

use App\Enums\AiAnalysisStatus;
use App\Enums\AiAnalysisType;
use App\Enums\ExperienceLevel;
use App\Enums\SkillImportance;
use App\Enums\WorkPreference;
use App\Enums\WorkType;
use App\Models\AiAnalysis;
use App\Models\CandidateSkill;
use App\Models\Job;
use App\Models\Skill;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class JobFitScoreTest extends TestCase
{
    use CreatesJobActors;

    #[Test]
    public function candidate_job_detail_computes_and_exposes_fit_score(): void
    {
        [, $profile, $token] = $this->createCandidateActor();
        $profile->update([
            'work_preference' => WorkPreference::Remote,
            'years_of_experience' => 5,
            'city' => 'Istanbul',
            'country' => 'Turkey',
        ]);

        $job = Job::factory()->published()->create([
            'slug' => 'fit-computed-role',
            'work_type' => WorkType::Remote,
            'experience_level' => ExperienceLevel::Mid,
            'city' => 'Istanbul',
            'country' => 'Turkey',
        ]);

        $skill = Skill::factory()->create(['name' => 'Laravel']);
        $job->skills()->attach($skill->id, ['importance' => SkillImportance::Required]);
        CandidateSkill::factory()->create([
            'candidate_profile_id' => $profile->id,
            'skill_id' => $skill->id,
        ]);

        $response = $this->withToken($token)
            ->getJson('/api/v1/jobs/fit-computed-role')
            ->assertOk()
            ->assertJsonPath('data.fit_analysis_status', 'completed');

        $this->assertNotNull($response->json('data.fit_score'));
        $this->assertDatabaseHas('ai_analyses', [
            'job_id' => $job->id,
            'candidate_profile_id' => $profile->id,
            'type' => AiAnalysisType::CvJobFit->value,
            'status' => AiAnalysisStatus::Completed->value,
            'is_latest' => true,
            'analysis_version' => 'fit-v1',
        ]);
    }

    #[Test]
    public function guest_job_detail_fit_score_is_null(): void
    {
        Job::factory()->published()->create(['slug' => 'guest-fit-role']);

        $this->getJson('/api/v1/jobs/guest-fit-role')
            ->assertOk()
            ->assertJsonPath('data.fit_score', null)
            ->assertJsonPath('data.fit_analysis_status', null);
    }

    #[Test]
    public function company_user_job_detail_fit_score_is_null(): void
    {
        [, , $token] = $this->createCompanyActor();
        Job::factory()->published()->create(['slug' => 'company-fit-role']);

        $this->withToken($token)
            ->getJson('/api/v1/jobs/company-fit-role')
            ->assertOk()
            ->assertJsonPath('data.fit_score', null)
            ->assertJsonPath('data.fit_analysis_status', null);
    }

    #[Test]
    public function repeated_analysis_flips_previous_latest_flag(): void
    {
        [, $profile, $token] = $this->createCandidateActor();
        $profile->update([
            'work_preference' => WorkPreference::Any,
            'years_of_experience' => 4,
        ]);

        $job = Job::factory()->published()->create([
            'slug' => 'repeat-fit-role',
            'work_type' => WorkType::Remote,
            'experience_level' => ExperienceLevel::Mid,
        ]);

        $oldAnalysis = AiAnalysis::query()->create([
            'type' => AiAnalysisType::CvJobFit,
            'job_id' => $job->id,
            'candidate_profile_id' => $profile->id,
            'score' => 40,
            'status' => AiAnalysisStatus::Completed,
            'is_latest' => true,
            'analysis_version' => 'fit-v0',
            'analyzed_at' => now()->subDay(),
        ]);

        $this->withToken($token)
            ->getJson('/api/v1/jobs/repeat-fit-role')
            ->assertOk()
            ->assertJsonPath('data.fit_analysis_status', 'completed');

        $this->assertFalse($oldAnalysis->fresh()->is_latest);
        $this->assertSame(1, AiAnalysis::query()
            ->where('job_id', $job->id)
            ->where('candidate_profile_id', $profile->id)
            ->where('type', AiAnalysisType::CvJobFit)
            ->where('is_latest', true)
            ->count());
    }

    #[Test]
    public function application_submit_snapshots_computed_fit_score(): void
    {
        [, $profile, $token] = $this->createCandidateActor();
        $profile->update([
            'work_preference' => WorkPreference::Remote,
            'years_of_experience' => 6,
        ]);

        $job = Job::factory()->published()->withTrustScore(88)->create([
            'work_type' => WorkType::Remote,
            'experience_level' => ExperienceLevel::Mid,
        ]);

        $response = $this->withToken($token)
            ->postJson('/api/v1/candidate/applications', ['job_id' => $job->id])
            ->assertCreated();

        $this->assertNotNull($response->json('data.match_score'));
        $this->assertSame(88, $response->json('data.trust_score'));
    }

    #[Test]
    public function profile_update_allows_new_analysis_on_next_view(): void
    {
        [, $profile, $token] = $this->createCandidateActor();
        $profile->update([
            'work_preference' => WorkPreference::Onsite,
            'years_of_experience' => 1,
        ]);

        $job = Job::factory()->published()->create([
            'slug' => 'profile-change-fit-role',
            'work_type' => WorkType::Remote,
            'experience_level' => ExperienceLevel::Senior,
        ]);

        $firstScore = $this->withToken($token)
            ->getJson('/api/v1/jobs/profile-change-fit-role')
            ->json('data.fit_score');

        $profile->update([
            'work_preference' => WorkPreference::Remote,
            'years_of_experience' => 10,
        ]);

        $secondScore = $this->withToken($token)
            ->getJson('/api/v1/jobs/profile-change-fit-role')
            ->json('data.fit_score');

        $this->assertNotSame($firstScore, $secondScore);
        $this->assertSame(2, AiAnalysis::query()
            ->where('job_id', $job->id)
            ->where('candidate_profile_id', $profile->id)
            ->where('type', AiAnalysisType::CvJobFit)
            ->count());
    }

    #[Test]
    public function min_fit_score_filter_uses_completed_analysis(): void
    {
        [, $profile, $token] = $this->createCandidateActor();
        $profile->update([
            'work_preference' => WorkPreference::Any,
            'years_of_experience' => 8,
        ]);

        $highFitJob = Job::factory()->published()->create(['title' => 'High Fit Job']);
        $lowFitJob = Job::factory()->published()->create(['title' => 'Low Fit Job']);

        AiAnalysis::query()->create([
            'type' => AiAnalysisType::CvJobFit,
            'job_id' => $highFitJob->id,
            'candidate_profile_id' => $profile->id,
            'score' => 85,
            'status' => AiAnalysisStatus::Completed,
            'is_latest' => true,
            'analysis_version' => 'fit-v1',
            'analyzed_at' => now(),
        ]);
        AiAnalysis::query()->create([
            'type' => AiAnalysisType::CvJobFit,
            'job_id' => $lowFitJob->id,
            'candidate_profile_id' => $profile->id,
            'score' => 40,
            'status' => AiAnalysisStatus::Completed,
            'is_latest' => true,
            'analysis_version' => 'fit-v1',
            'analyzed_at' => now(),
        ]);

        $this->withToken($token)
            ->getJson('/api/v1/jobs?min_fit_score=70')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.title', 'High Fit Job');
    }

    #[Test]
    public function sort_by_fit_score_orders_by_completed_analysis(): void
    {
        [, $profile, $token] = $this->createCandidateActor();

        $lower = Job::factory()->published()->create(['title' => 'Lower Fit Job']);
        $higher = Job::factory()->published()->create(['title' => 'Higher Fit Job']);

        AiAnalysis::query()->create([
            'type' => AiAnalysisType::CvJobFit,
            'job_id' => $lower->id,
            'candidate_profile_id' => $profile->id,
            'score' => 55,
            'status' => AiAnalysisStatus::Completed,
            'is_latest' => true,
            'analysis_version' => 'fit-v1',
            'analyzed_at' => now(),
        ]);
        AiAnalysis::query()->create([
            'type' => AiAnalysisType::CvJobFit,
            'job_id' => $higher->id,
            'candidate_profile_id' => $profile->id,
            'score' => 92,
            'status' => AiAnalysisStatus::Completed,
            'is_latest' => true,
            'analysis_version' => 'fit-v1',
            'analyzed_at' => now(),
        ]);

        $this->withToken($token)
            ->getJson('/api/v1/jobs?sort=fit_score')
            ->assertOk()
            ->assertJsonPath('data.items.0.title', 'Higher Fit Job')
            ->assertJsonPath('data.items.1.title', 'Lower Fit Job');
    }
}

<?php

namespace Tests\Feature\Job;

use App\Enums\AiAnalysisStatus;
use App\Enums\AiAnalysisType;
use App\Enums\ExperienceLevel;
use App\Enums\SkillImportance;
use App\Enums\WorkPreference;
use App\Enums\WorkType;
use App\Models\AiAnalysis;
use App\Models\CandidateExperience;
use App\Models\CandidateSkill;
use App\Models\Job;
use App\Models\Skill;
use App\Services\AI\CvJobFitAnalysisService;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class JobFitScoreCacheTest extends TestCase
{
    use CreatesJobActors;

    #[Test]
    public function first_analysis_is_calculated_and_persisted(): void
    {
        [, $profile, $token] = $this->createCandidateActor();
        $profile->update(['work_preference' => WorkPreference::Remote, 'years_of_experience' => 5]);

        $job = Job::factory()->published()->create([
            'slug' => 'cache-first-role',
            'work_type' => WorkType::Remote,
            'experience_level' => ExperienceLevel::Mid,
        ]);

        $this->withToken($token)
            ->getJson('/api/v1/jobs/cache-first-role')
            ->assertOk()
            ->assertJsonPath('data.fit_analysis_status', 'completed');

        $this->assertSame(1, $this->analysisCount($job->id, $profile->id));
        $this->assertNotNull(
            AiAnalysis::query()->where('job_id', $job->id)->value('details')['input_fingerprint'] ?? null,
        );
    }

    #[Test]
    public function repeat_view_reuses_existing_analysis_without_new_row(): void
    {
        [, $profile, $token] = $this->createCandidateActor();
        $profile->update(['work_preference' => WorkPreference::Any, 'years_of_experience' => 4]);

        $job = Job::factory()->published()->create([
            'slug' => 'cache-repeat-role',
            'work_type' => WorkType::Remote,
            'experience_level' => ExperienceLevel::Mid,
        ]);

        $this->withToken($token)->getJson('/api/v1/jobs/cache-repeat-role')->assertOk();
        $firstAnalysisId = AiAnalysis::query()->where('job_id', $job->id)->value('id');

        $this->withToken($token)->getJson('/api/v1/jobs/cache-repeat-role')->assertOk();

        $this->assertSame(1, $this->analysisCount($job->id, $profile->id));
        $this->assertSame($firstAnalysisId, AiAnalysis::query()->where('job_id', $job->id)->where('is_latest', true)->value('id'));
    }

    #[Test]
    public function candidate_profile_change_triggers_reanalysis(): void
    {
        [, $profile, $token] = $this->createCandidateActor();
        $profile->update(['work_preference' => WorkPreference::Onsite, 'years_of_experience' => 1]);

        $job = Job::factory()->published()->create([
            'slug' => 'cache-profile-role',
            'work_type' => WorkType::Remote,
            'experience_level' => ExperienceLevel::Senior,
        ]);

        $this->withToken($token)->getJson('/api/v1/jobs/cache-profile-role')->assertOk();
        $profile->update(['work_preference' => WorkPreference::Remote, 'years_of_experience' => 10]);
        $this->withToken($token)->getJson('/api/v1/jobs/cache-profile-role')->assertOk();

        $this->assertSame(2, $this->analysisCount($job->id, $profile->id));
    }

    #[Test]
    public function candidate_skill_change_triggers_reanalysis(): void
    {
        [, $profile, $token] = $this->createCandidateActor();
        $job = Job::factory()->published()->create(['slug' => 'cache-candidate-skill-role']);
        $skill = Skill::factory()->create();
        $candidateSkill = CandidateSkill::factory()->create([
            'candidate_profile_id' => $profile->id,
            'skill_id' => $skill->id,
        ]);

        $this->withToken($token)->getJson('/api/v1/jobs/cache-candidate-skill-role')->assertOk();
        $candidateSkill->update(['years_of_experience' => 4]);
        $this->withToken($token)->getJson('/api/v1/jobs/cache-candidate-skill-role')->assertOk();

        $this->assertSame(2, $this->analysisCount($job->id, $profile->id));
    }

    #[Test]
    public function candidate_experience_change_triggers_reanalysis(): void
    {
        [, $profile, $token] = $this->createCandidateActor();
        $job = Job::factory()->published()->create(['slug' => 'cache-experience-role']);
        $experience = CandidateExperience::factory()->create(['candidate_profile_id' => $profile->id]);

        $this->withToken($token)->getJson('/api/v1/jobs/cache-experience-role')->assertOk();
        $experience->update(['position_title' => 'Lead Engineer']);
        $this->withToken($token)->getJson('/api/v1/jobs/cache-experience-role')->assertOk();

        $this->assertSame(2, $this->analysisCount($job->id, $profile->id));
    }

    #[Test]
    public function job_change_triggers_reanalysis(): void
    {
        [, $profile, $token] = $this->createCandidateActor();
        $job = Job::factory()->published()->create([
            'slug' => 'cache-job-role',
            'experience_level' => ExperienceLevel::Mid,
        ]);

        $this->withToken($token)->getJson('/api/v1/jobs/cache-job-role')->assertOk();
        $job->update(['experience_level' => ExperienceLevel::Senior]);
        $this->withToken($token)->getJson('/api/v1/jobs/cache-job-role')->assertOk();

        $this->assertSame(2, $this->analysisCount($job->id, $profile->id));
    }

    #[Test]
    public function job_skill_change_triggers_reanalysis(): void
    {
        [, $profile, $token] = $this->createCandidateActor();
        $job = Job::factory()->published()->create(['slug' => 'cache-job-skill-role']);
        $skill = Skill::factory()->create();
        $job->skills()->attach($skill->id, ['importance' => SkillImportance::Required]);

        $this->withToken($token)->getJson('/api/v1/jobs/cache-job-skill-role')->assertOk();
        $job->skills()->updateExistingPivot($skill->id, ['importance' => SkillImportance::Preferred]);
        $this->withToken($token)->getJson('/api/v1/jobs/cache-job-skill-role')->assertOk();

        $this->assertSame(2, $this->analysisCount($job->id, $profile->id));
    }

    #[Test]
    public function fit_version_change_triggers_reanalysis(): void
    {
        [, $profile] = $this->createCandidateActor();
        $job = Job::factory()->published()->create();
        $service = app(CvJobFitAnalysisService::class);

        $first = $service->analyze($profile, $job);
        Config::set('fit_score.version', 'fit-v2');
        $second = $service->analyze($profile, $job);

        $this->assertFalse($first->fresh()->is_latest);
        $this->assertTrue($second->is_latest);
        $this->assertSame('fit-v2', $second->analysis_version);
    }

    #[Test]
    public function reanalysis_marks_previous_latest_as_false(): void
    {
        [, $profile, $token] = $this->createCandidateActor();
        $job = Job::factory()->published()->create(['slug' => 'cache-latest-role']);

        $this->withToken($token)->getJson('/api/v1/jobs/cache-latest-role')->assertOk();
        $first = AiAnalysis::query()->where('job_id', $job->id)->where('is_latest', true)->first();
        $job->update(['experience_level' => ExperienceLevel::Lead]);
        $this->withToken($token)->getJson('/api/v1/jobs/cache-latest-role')->assertOk();

        $this->assertFalse($first->fresh()->is_latest);
        $this->assertSame(1, AiAnalysis::query()
            ->where('job_id', $job->id)
            ->where('candidate_profile_id', $profile->id)
            ->where('is_latest', true)
            ->count());
    }

    #[Test]
    public function application_snapshot_continues_to_work_with_cached_analysis(): void
    {
        [, $profile, $token] = $this->createCandidateActor();
        $profile->update(['work_preference' => WorkPreference::Remote, 'years_of_experience' => 6]);

        $job = Job::factory()->published()->withTrustScore(88)->create([
            'work_type' => WorkType::Remote,
            'experience_level' => ExperienceLevel::Mid,
        ]);

        $this->withToken($token)->getJson('/api/v1/jobs/'.$job->slug)->assertOk();
        $analysisCountBeforeApply = $this->analysisCount($job->id, $profile->id);

        $response = $this->withToken($token)
            ->postJson('/api/v1/candidate/applications', ['job_id' => $job->id])
            ->assertCreated();

        $this->assertNotNull($response->json('data.match_score'));
        $this->assertSame($analysisCountBeforeApply, $this->analysisCount($job->id, $profile->id));
    }

    private function analysisCount(int $jobId, int $profileId): int
    {
        return AiAnalysis::query()
            ->where('job_id', $jobId)
            ->where('candidate_profile_id', $profileId)
            ->where('type', AiAnalysisType::CvJobFit)
            ->count();
    }
}

<?php

namespace Tests\Feature\Job;

use App\Enums\AiAnalysisType;
use App\Enums\SkillImportance;
use App\Models\AiAnalysis;
use App\Models\CandidateSkill;
use App\Models\Job;
use App\Models\Skill;
use App\Services\FitScore\FitScoreInputFingerprint;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Calibration tests for Fit Score cache / fingerprint behavior (scenarios 18–20).
 */
class FitScoreCalibrationTest extends TestCase
{
    use CreatesJobActors;

    #[Test]
    public function scenario_18_repeat_analyze_with_same_input_reuses_cache_without_new_row(): void
    {
        [, $profile, $token] = $this->createCandidateActor();
        $profile->update(['years_of_experience' => 4]);

        $job = Job::factory()->published()->create(['slug' => 'calibration-cache-hit']);

        $this->withToken($token)->getJson('/api/v1/jobs/calibration-cache-hit')->assertOk();
        $firstAnalysisId = AiAnalysis::query()
            ->where('job_id', $job->id)
            ->where('candidate_profile_id', $profile->id)
            ->where('type', AiAnalysisType::CvJobFit)
            ->value('id');

        $this->withToken($token)->getJson('/api/v1/jobs/calibration-cache-hit')->assertOk();

        $this->assertSame(1, $this->analysisCount($job->id, $profile->id));
        $this->assertSame(
            $firstAnalysisId,
            AiAnalysis::query()
                ->where('job_id', $job->id)
                ->where('candidate_profile_id', $profile->id)
                ->where('is_latest', true)
                ->value('id'),
        );
    }

    #[Test]
    public function scenario_19_candidate_skill_change_changes_fingerprint_and_creates_new_analysis(): void
    {
        [, $profile] = $this->createCandidateActor();
        $job = Job::factory()->published()->create();
        $skill = Skill::factory()->create();
        $candidateSkill = CandidateSkill::factory()->create([
            'candidate_profile_id' => $profile->id,
            'skill_id' => $skill->id,
            'years_of_experience' => 2,
        ]);

        $profile->loadMissing('candidateSkills');
        $job->loadMissing('skills');
        $originalFingerprint = FitScoreInputFingerprint::generate($profile, $job);

        $service = app(\App\Services\AI\CvJobFitAnalysisService::class);
        $first = $service->analyze($profile, $job);

        $candidateSkill->update(['years_of_experience' => 6]);
        $profile->refresh()->loadMissing('candidateSkills');
        $changedFingerprint = FitScoreInputFingerprint::generate($profile, $job);

        $this->assertNotSame($originalFingerprint, $changedFingerprint);

        $second = $service->analyze($profile, $job->fresh(['skills']));

        $this->assertSame(2, $this->analysisCount($job->id, $profile->id));
        $this->assertFalse($first->fresh()->is_latest);
        $this->assertTrue($second->is_latest);
    }

    #[Test]
    public function scenario_20_job_skill_importance_change_changes_fingerprint_and_creates_new_analysis(): void
    {
        [, $profile] = $this->createCandidateActor();
        $job = Job::factory()->published()->create();
        $skill = Skill::factory()->create();
        $job->skills()->attach($skill->id, ['importance' => SkillImportance::Required]);

        $profile->loadMissing('candidateSkills');
        $job->loadMissing('skills');
        $originalFingerprint = FitScoreInputFingerprint::generate($profile, $job);

        $service = app(\App\Services\AI\CvJobFitAnalysisService::class);
        $first = $service->analyze($profile, $job);

        $job->skills()->updateExistingPivot($skill->id, ['importance' => SkillImportance::Preferred]);
        $job->refresh()->loadMissing('skills');
        $changedFingerprint = FitScoreInputFingerprint::generate($profile, $job->fresh(['skills']));

        $this->assertNotSame($originalFingerprint, $changedFingerprint);

        $second = $service->analyze($profile->fresh(['candidateSkills']), $job->fresh(['skills']));

        $this->assertSame(2, $this->analysisCount($job->id, $profile->id));
        $this->assertFalse($first->fresh()->is_latest);
        $this->assertTrue($second->is_latest);
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

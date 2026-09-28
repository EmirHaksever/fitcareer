<?php

namespace Tests\Unit\FitScore;

use App\Enums\AiAnalysisStatus;
use App\Enums\AiAnalysisType;
use App\Enums\ExperienceLevel;
use App\Enums\SkillImportance;
use App\Enums\WorkPreference;
use App\Enums\WorkType;
use App\Models\AiAnalysis;
use App\Models\CandidateExperience;
use App\Models\CandidateProfile;
use App\Models\CandidateSkill;
use App\Models\Job;
use App\Models\Skill;
use App\Services\AI\CvJobFitAnalysisService;
use App\Services\FitScore\FitScoreInputFingerprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FitScoreInputFingerprintTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function fingerprint_changes_when_candidate_profile_fields_change(): void
    {
        [$candidate, $job] = $this->createPair();

        $before = FitScoreInputFingerprint::generate($candidate, $job);

        $candidate->update(['years_of_experience' => 9]);
        $candidate->refresh()->load(['candidateSkills', 'experiences', 'skills']);
        $job->load('skills');

        $this->assertNotSame($before, FitScoreInputFingerprint::generate($candidate, $job));
    }

    #[Test]
    public function fingerprint_changes_when_candidate_skill_changes(): void
    {
        [$candidate, $job] = $this->createPair();
        $skill = Skill::factory()->create();
        $candidateSkill = CandidateSkill::factory()->create([
            'candidate_profile_id' => $candidate->id,
            'skill_id' => $skill->id,
            'years_of_experience' => 1,
        ]);

        $candidate->refresh()->load(['candidateSkills', 'experiences', 'skills']);
        $before = FitScoreInputFingerprint::generate($candidate, $job);

        $candidateSkill->update(['years_of_experience' => 5]);
        $candidate->refresh()->load(['candidateSkills', 'experiences', 'skills']);

        $this->assertNotSame($before, FitScoreInputFingerprint::generate($candidate, $job));
    }

    #[Test]
    public function fingerprint_changes_when_job_skill_changes(): void
    {
        [$candidate, $job] = $this->createPair();
        $skill = Skill::factory()->create();
        $job->skills()->attach($skill->id, ['importance' => SkillImportance::Required]);
        $job->load('skills');

        $before = FitScoreInputFingerprint::generate($candidate, $job);

        $job->skills()->updateExistingPivot($skill->id, ['importance' => SkillImportance::Preferred]);
        $job->load('skills');

        $this->assertNotSame($before, FitScoreInputFingerprint::generate($candidate, $job));
    }

    #[Test]
    public function reusable_analysis_requires_matching_fingerprint_and_version(): void
    {
        [$candidate, $job] = $this->createPair();
        $fingerprint = FitScoreInputFingerprint::generate($candidate, $job);

        $analysis = AiAnalysis::query()->create([
            'type' => AiAnalysisType::CvJobFit,
            'job_id' => $job->id,
            'candidate_profile_id' => $candidate->id,
            'score' => 70,
            'status' => AiAnalysisStatus::Completed,
            'is_latest' => true,
            'analysis_version' => 'fit-v1',
            'details' => [
                'signals' => [],
                'confidence' => 1.0,
                'input_fingerprint' => $fingerprint,
                'fit_version' => 'fit-v1',
            ],
            'analyzed_at' => now(),
        ]);

        $this->assertTrue(FitScoreInputFingerprint::isReusable($analysis, $candidate, $job));

        Config::set('fit_score.version', 'fit-v2');
        $this->assertFalse(FitScoreInputFingerprint::isReusable($analysis->fresh(), $candidate, $job));
    }

    /**
     * @return array{0: CandidateProfile, 1: Job}
     */
    private function createPair(): array
    {
        $candidate = CandidateProfile::factory()->create([
            'work_preference' => WorkPreference::Remote,
            'years_of_experience' => 4,
        ]);
        $job = Job::factory()->published()->create([
            'work_type' => WorkType::Remote,
            'experience_level' => ExperienceLevel::Mid,
        ]);

        $candidate->load(['candidateSkills', 'experiences', 'skills']);
        $job->load('skills');

        return [$candidate, $job];
    }
}

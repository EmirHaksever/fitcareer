<?php

namespace Tests\Unit\FitScore;

use App\Enums\ExperienceLevel;
use App\Enums\SkillImportance;
use App\Enums\WorkPreference;
use App\Enums\WorkType;
use App\Models\CandidateProfile;
use App\Models\CandidateSkill;
use App\Models\Job;
use App\Models\Skill;
use App\Services\FitScore\Signals\ExperienceLevelSignal;
use App\Services\FitScore\Signals\LocationSignal;
use App\Services\FitScore\Signals\PreferredSkillsSignal;
use App\Services\FitScore\Signals\RequiredSkillsSignal;
use App\Services\FitScore\Signals\SalarySignal;
use App\Services\FitScore\Signals\WorkTypeSignal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FitSignalsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function required_skill_full_coverage_scores_100(): void
    {
        [$candidate, $job] = $this->createSkillMatchScenario(requiredCount: 2, matchedCount: 2);

        $result = app(RequiredSkillsSignal::class)->evaluate($candidate, $job);

        $this->assertSame(100, $result->score);
        $this->assertSame(2, $result->evidence['matched_count']);
    }

    #[Test]
    public function required_skill_half_coverage_scores_50(): void
    {
        [$candidate, $job] = $this->createSkillMatchScenario(requiredCount: 2, matchedCount: 1);

        $result = app(RequiredSkillsSignal::class)->evaluate($candidate, $job);

        $this->assertSame(50, $result->score);
        $this->assertSame(['Skill 2'], $result->evidence['missing_skills']);
    }

    #[Test]
    public function no_required_skills_returns_null(): void
    {
        $candidate = CandidateProfile::factory()->create();
        $job = Job::factory()->published()->create();
        $job->setRelation('skills', collect());

        $result = app(RequiredSkillsSignal::class)->evaluate($candidate, $job);

        $this->assertNull($result->score);
    }

    #[Test]
    public function preferred_skills_use_same_coverage_logic(): void
    {
        $candidate = CandidateProfile::factory()->create();
        $job = Job::factory()->published()->create();
        $skills = Skill::factory()->count(2)->sequence(['name' => 'Docker'], ['name' => 'Redis'])->create();

        $job->skills()->attach($skills[0]->id, ['importance' => SkillImportance::Preferred]);
        $job->skills()->attach($skills[1]->id, ['importance' => SkillImportance::Preferred]);
        $job->load('skills');

        CandidateSkill::factory()->create([
            'candidate_profile_id' => $candidate->id,
            'skill_id' => $skills[0]->id,
        ]);
        $candidate->load('candidateSkills');

        $result = app(PreferredSkillsSignal::class)->evaluate($candidate, $job);

        $this->assertSame(50, $result->score);
    }

    #[Test]
    public function experience_match_scores_high(): void
    {
        $candidate = CandidateProfile::factory()->make(['years_of_experience' => 8]);
        $job = Job::factory()->make(['experience_level' => ExperienceLevel::Senior]);

        $result = app(ExperienceLevelSignal::class)->evaluate($candidate, $job);

        $this->assertSame(100, $result->score);
    }

    #[Test]
    public function experience_mismatch_scores_low(): void
    {
        $candidate = CandidateProfile::factory()->make(['years_of_experience' => 0]);
        $job = Job::factory()->make(['experience_level' => ExperienceLevel::Senior]);

        $result = app(ExperienceLevelSignal::class)->evaluate($candidate, $job);

        $this->assertSame(20, $result->score);
    }

    #[Test]
    public function missing_experience_data_returns_null(): void
    {
        $candidate = CandidateProfile::factory()->make(['years_of_experience' => null]);
        $job = Job::factory()->make(['experience_level' => ExperienceLevel::Mid]);

        $result = app(ExperienceLevelSignal::class)->evaluate($candidate, $job);

        $this->assertNull($result->score);
    }

    #[Test]
    public function work_type_exact_match_scores_100(): void
    {
        $candidate = CandidateProfile::factory()->make(['work_preference' => WorkPreference::Remote]);
        $job = Job::factory()->make(['work_type' => WorkType::Remote]);

        $result = app(WorkTypeSignal::class)->evaluate($candidate, $job);

        $this->assertSame(100, $result->score);
    }

    #[Test]
    public function work_type_any_preference_scores_100(): void
    {
        $candidate = CandidateProfile::factory()->make(['work_preference' => WorkPreference::Any]);
        $job = Job::factory()->make(['work_type' => WorkType::Onsite]);

        $result = app(WorkTypeSignal::class)->evaluate($candidate, $job);

        $this->assertSame(100, $result->score);
    }

    #[Test]
    public function location_same_city_scores_100(): void
    {
        $candidate = CandidateProfile::factory()->make([
            'city' => 'Istanbul',
            'country' => 'Turkey',
        ]);
        $job = Job::factory()->make([
            'work_type' => WorkType::Onsite,
            'city' => 'Istanbul',
            'country' => 'Turkey',
        ]);

        $result = app(LocationSignal::class)->evaluate($candidate, $job);

        $this->assertSame(100, $result->score);
    }

    #[Test]
    public function remote_job_bypasses_location_penalty(): void
    {
        $candidate = CandidateProfile::factory()->make([
            'city' => 'Berlin',
            'country' => 'Germany',
        ]);
        $job = Job::factory()->make([
            'work_type' => WorkType::Remote,
            'city' => 'Istanbul',
            'country' => 'Turkey',
        ]);

        $result = app(LocationSignal::class)->evaluate($candidate, $job);

        $this->assertSame(100, $result->score);
        $this->assertSame('remote_job_bypass', $result->evidence['reason']);
    }

    #[Test]
    public function salary_overlap_scores_high(): void
    {
        $candidate = CandidateProfile::factory()->make([
            'desired_salary_min' => 60000,
            'desired_salary_max' => 90000,
        ]);
        $job = Job::factory()->make([
            'is_salary_visible' => true,
            'salary_min' => 70000,
            'salary_max' => 100000,
            'salary_currency' => 'TRY',
        ]);

        $result = app(SalarySignal::class)->evaluate($candidate, $job);

        $this->assertGreaterThan(0, $result->score);
    }

    #[Test]
    public function salary_missing_returns_null(): void
    {
        $candidate = CandidateProfile::factory()->make([
            'desired_salary_min' => null,
            'desired_salary_max' => null,
        ]);
        $job = Job::factory()->make([
            'is_salary_visible' => true,
            'salary_min' => 70000,
            'salary_max' => 100000,
            'salary_currency' => 'TRY',
        ]);

        $result = app(SalarySignal::class)->evaluate($candidate, $job);

        $this->assertNull($result->score);
    }

    /**
     * @return array{0: CandidateProfile, 1: Job}
     */
    private function createSkillMatchScenario(int $requiredCount, int $matchedCount): array
    {
        $candidate = CandidateProfile::factory()->create();
        $job = Job::factory()->published()->create();
        $skills = Skill::factory()->count($requiredCount)->sequence(
            ...collect(range(1, $requiredCount))->map(fn (int $i): array => ['name' => "Skill {$i}"])->all(),
        )->create();

        foreach ($skills as $skill) {
            $job->skills()->attach($skill->id, ['importance' => SkillImportance::Required]);
        }

        foreach ($skills->take($matchedCount) as $skill) {
            CandidateSkill::factory()->create([
                'candidate_profile_id' => $candidate->id,
                'skill_id' => $skill->id,
            ]);
        }

        $job->load('skills');
        $candidate->load('candidateSkills');

        return [$candidate, $job];
    }
}

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
use App\Services\FitScore\FitScoreCalculator;
use App\Services\FitScore\FitScoreResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Calibration tests for Fit Score V1 — validates existing product behavior without changing scoring logic.
 */
class FitScoreCalibrationTest extends TestCase
{
    use RefreshDatabase;

    private FitScoreCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculator = app(FitScoreCalculator::class);
    }

    #[Test]
    public function scenario_01_all_required_skills_match_produces_high_score(): void
    {
        [$candidate, $job] = $this->baselinePair(
            requiredCount: 2,
            matchedRequired: 2,
        );

        $result = $this->calculate($candidate, $job);

        $this->assertSame(100, $result->signals['required_skills']['score']);
        $this->assertGreaterThanOrEqual(90, $result->score);
    }

    #[Test]
    public function scenario_02_partial_required_skill_match_lowers_score(): void
    {
        [$fullCandidate, $fullJob] = $this->baselinePair(requiredCount: 2, matchedRequired: 2);
        [$partialCandidate, $partialJob] = $this->baselinePair(requiredCount: 2, matchedRequired: 1);

        $fullResult = $this->calculate($fullCandidate, $fullJob);
        $partialResult = $this->calculate($partialCandidate, $partialJob);

        $this->assertSame(50, $partialResult->signals['required_skills']['score']);
        $this->assertLessThan($fullResult->score, $partialResult->score);
    }

    #[Test]
    public function scenario_03_zero_required_skill_match_is_severely_low(): void
    {
        [$candidate, $job] = $this->baselinePair(requiredCount: 2, matchedRequired: 0);

        $result = $this->calculate($candidate, $job);

        $this->assertSame(0, $result->signals['required_skills']['score']);
        $this->assertLessThan(70, $result->score);
    }

    #[Test]
    public function scenario_04_no_required_skills_on_job_is_unknown_not_zero(): void
    {
        [$candidate, $job] = $this->baselinePair(requiredCount: 0, matchedRequired: 0);

        $result = $this->calculate($candidate, $job);

        $this->assertNull($result->signals['required_skills']['score']);
        $this->assertSame(0.0, $result->signals['required_skills']['confidence']);
        $this->assertNotNull($result->score);
    }

    #[Test]
    public function scenario_05_no_preferred_skills_on_job_is_unknown(): void
    {
        $candidate = $this->createCandidate([
            'work_preference' => WorkPreference::Remote,
            'years_of_experience' => 4,
        ]);
        $job = $this->createJob([
            'work_type' => WorkType::Remote,
            'experience_level' => ExperienceLevel::Mid,
        ]);

        $result = $this->calculate($candidate, $job);

        $this->assertNull($result->signals['preferred_skills']['score']);
        $this->assertSame('no_skills_defined', $result->signals['preferred_skills']['evidence']['reason']);
    }

    #[Test]
    public function scenario_06_aligned_experience_produces_high_signal(): void
    {
        $candidate = $this->createCandidate(['years_of_experience' => 8]);
        $job = $this->createJob(['experience_level' => ExperienceLevel::Senior]);

        $result = $this->calculate($candidate, $job);

        $this->assertSame(100, $result->signals['experience']['score']);
    }

    #[Test]
    public function scenario_07_experience_well_below_job_level_produces_low_signal(): void
    {
        $candidate = $this->createCandidate(['years_of_experience' => 0]);
        $job = $this->createJob(['experience_level' => ExperienceLevel::Senior]);

        $result = $this->calculate($candidate, $job);

        $this->assertSame(20, $result->signals['experience']['score']);
    }

    #[Test]
    public function scenario_08_remote_job_and_remote_candidate_produces_high_work_type_signal(): void
    {
        $candidate = $this->createCandidate(['work_preference' => WorkPreference::Remote]);
        $job = $this->createJob(['work_type' => WorkType::Remote]);

        $result = $this->calculate($candidate, $job);

        $this->assertSame(100, $result->signals['work_type']['score']);
        $this->assertSame('exact', $result->signals['work_type']['evidence']['match_type']);
    }

    #[Test]
    public function scenario_09_remote_job_bypasses_location_for_any_candidate_location(): void
    {
        $candidate = $this->createCandidate([
            'city' => 'Berlin',
            'country' => 'Germany',
            'work_preference' => WorkPreference::Remote,
        ]);
        $job = $this->createJob([
            'work_type' => WorkType::Remote,
            'city' => 'Istanbul',
            'country' => 'Turkey',
        ]);

        $result = $this->calculate($candidate, $job);

        $this->assertSame(100, $result->signals['location']['score']);
        $this->assertSame('remote_job_bypass', $result->signals['location']['evidence']['reason']);
    }

    #[Test]
    public function scenario_10_onsite_job_same_city_produces_high_location_signal(): void
    {
        $candidate = $this->createCandidate([
            'city' => 'Istanbul',
            'country' => 'Turkey',
            'work_preference' => WorkPreference::Onsite,
        ]);
        $job = $this->createJob([
            'work_type' => WorkType::Onsite,
            'city' => 'Istanbul',
            'country' => 'Turkey',
        ]);

        $result = $this->calculate($candidate, $job);

        $this->assertSame(100, $result->signals['location']['score']);
        $this->assertSame('same_city', $result->signals['location']['evidence']['match_type']);
    }

    #[Test]
    public function scenario_11_onsite_job_different_city_produces_lower_location_signal(): void
    {
        $sameCity = $this->calculate(
            $this->createCandidate([
                'city' => 'Istanbul',
                'country' => 'Turkey',
                'work_preference' => WorkPreference::Onsite,
            ]),
            $this->createJob([
                'work_type' => WorkType::Onsite,
                'city' => 'Istanbul',
                'country' => 'Turkey',
            ]),
        );

        $differentCity = $this->calculate(
            $this->createCandidate([
                'city' => 'Ankara',
                'country' => 'Turkey',
                'work_preference' => WorkPreference::Onsite,
            ]),
            $this->createJob([
                'work_type' => WorkType::Onsite,
                'city' => 'Istanbul',
                'country' => 'Turkey',
            ]),
        );

        $this->assertSame(50, $differentCity->signals['location']['score']);
        $this->assertSame('same_country', $differentCity->signals['location']['evidence']['match_type']);
        $this->assertLessThan($sameCity->signals['location']['score'], $differentCity->signals['location']['score']);
    }

    #[Test]
    public function scenario_12_hidden_job_salary_is_unknown_and_does_not_penalize(): void
    {
        $withoutSalary = $this->calculate(
            $this->createCandidate([
                'desired_salary_min' => null,
                'desired_salary_max' => null,
                'work_preference' => WorkPreference::Remote,
                'years_of_experience' => 4,
            ]),
            $this->createJob([
                'is_salary_visible' => false,
                'work_type' => WorkType::Remote,
                'experience_level' => ExperienceLevel::Mid,
            ]),
        );

        $this->assertNull($withoutSalary->signals['salary']['score']);
        $this->assertSame('salary_not_visible', $withoutSalary->signals['salary']['evidence']['reason']);

        $withVisibleButMissingCandidateSalary = $this->calculate(
            $this->createCandidate([
                'desired_salary_min' => null,
                'desired_salary_max' => null,
                'work_preference' => WorkPreference::Remote,
                'years_of_experience' => 4,
            ]),
            $this->createJob([
                'is_salary_visible' => true,
                'salary_min' => 70000,
                'salary_max' => 100000,
                'salary_currency' => 'TRY',
                'work_type' => WorkType::Remote,
                'experience_level' => ExperienceLevel::Mid,
            ]),
        );

        $this->assertNull($withVisibleButMissingCandidateSalary->signals['salary']['score']);
        $this->assertSame($withoutSalary->score, $withVisibleButMissingCandidateSalary->score);
    }

    #[Test]
    public function scenario_13_overlapping_salary_ranges_produce_positive_signal(): void
    {
        $candidate = $this->createCandidate([
            'desired_salary_min' => 60000,
            'desired_salary_max' => 90000,
            'work_preference' => WorkPreference::Remote,
            'years_of_experience' => 4,
        ]);
        $job = $this->createJob([
            'is_salary_visible' => true,
            'salary_min' => 70000,
            'salary_max' => 100000,
            'salary_currency' => 'TRY',
            'work_type' => WorkType::Remote,
            'experience_level' => ExperienceLevel::Mid,
        ]);

        $result = $this->calculate($candidate, $job);

        $this->assertGreaterThan(0, $result->signals['salary']['score']);
        $this->assertTrue($result->signals['salary']['evidence']['overlap']);
    }

    #[Test]
    public function scenario_14_disjoint_salary_ranges_produce_low_signal(): void
    {
        $candidate = $this->createCandidate([
            'desired_salary_min' => 100000,
            'desired_salary_max' => 120000,
            'work_preference' => WorkPreference::Remote,
            'years_of_experience' => 4,
        ]);
        $job = $this->createJob([
            'is_salary_visible' => true,
            'salary_min' => 30000,
            'salary_max' => 50000,
            'salary_currency' => 'TRY',
            'work_type' => WorkType::Remote,
            'experience_level' => ExperienceLevel::Mid,
        ]);

        $result = $this->calculate($candidate, $job);

        $this->assertSame(0, $result->signals['salary']['score']);
        $this->assertFalse($result->signals['salary']['evidence']['overlap']);
    }

    #[Test]
    public function scenario_15_multiple_unknown_signals_still_allow_final_score(): void
    {
        [$candidate, $job] = $this->baselinePair(requiredCount: 2, matchedRequired: 2, attachPreferred: false);

        $result = $this->calculate($candidate, $job);

        $this->assertNull($result->signals['preferred_skills']['score']);
        $this->assertNull($result->signals['salary']['score']);
        $this->assertNotNull($result->score);
    }

    #[Test]
    public function scenario_16_no_usable_signals_produces_null_final_score(): void
    {
        $candidate = $this->createCandidate([
            'work_preference' => null,
            'years_of_experience' => null,
            'city' => null,
            'country' => null,
            'desired_salary_min' => null,
            'desired_salary_max' => null,
        ]);
        $job = $this->createJob([
            'work_type' => WorkType::Onsite,
            'experience_level' => null,
            'city' => null,
            'country' => null,
            'is_salary_visible' => false,
        ]);

        $result = $this->calculate($candidate, $job);

        $this->assertNull($result->signals['required_skills']['score']);
        $this->assertNull($result->signals['preferred_skills']['score']);
        $this->assertNull($result->signals['experience']['score']);
        $this->assertNull($result->signals['work_type']['score']);
        $this->assertNull($result->signals['location']['score']);
        $this->assertNull($result->signals['salary']['score']);
        $this->assertNull($result->score);
    }

    #[Test]
    public function scenario_17_final_scores_remain_within_zero_and_one_hundred(): void
    {
        $results = [
            $this->calculate(...$this->baselinePair(requiredCount: 2, matchedRequired: 2)),
            $this->calculate(...$this->baselinePair(requiredCount: 2, matchedRequired: 1)),
            $this->calculate(...$this->baselinePair(requiredCount: 2, matchedRequired: 0)),
            $this->calculate(
                $this->createCandidate([
                    'desired_salary_min' => 60000,
                    'desired_salary_max' => 90000,
                    'work_preference' => WorkPreference::Remote,
                    'years_of_experience' => 4,
                ]),
                $this->createJob([
                    'is_salary_visible' => true,
                    'salary_min' => 70000,
                    'salary_max' => 100000,
                    'salary_currency' => 'TRY',
                    'work_type' => WorkType::Remote,
                    'experience_level' => ExperienceLevel::Mid,
                ]),
            ),
            $this->calculate(
                $this->createCandidate([
                    'desired_salary_min' => 100000,
                    'desired_salary_max' => 120000,
                    'work_preference' => WorkPreference::Remote,
                    'years_of_experience' => 4,
                ]),
                $this->createJob([
                    'is_salary_visible' => true,
                    'salary_min' => 30000,
                    'salary_max' => 50000,
                    'salary_currency' => 'TRY',
                    'work_type' => WorkType::Remote,
                    'experience_level' => ExperienceLevel::Mid,
                ]),
            ),
        ];

        foreach ($results as $result) {
            $this->assertNotNull($result->score);
            $this->assertGreaterThanOrEqual(0, $result->score);
            $this->assertLessThanOrEqual(100, $result->score);
        }
    }

    #[Test]
    public function scenario_custom_weights_change_score_vs_default_for_same_input(): void
    {
        $candidate = $this->createCandidate([
            'work_preference' => WorkPreference::Remote,
            'years_of_experience' => 8,
        ]);
        $job = $this->createJob([
            'work_type' => WorkType::Remote,
            'experience_level' => ExperienceLevel::Senior,
            'is_salary_visible' => false,
        ]);

        $skills = Skill::factory()->count(2)->create();
        foreach ($skills as $skill) {
            $job->skills()->attach($skill->id, ['importance' => SkillImportance::Required]);
        }

        $defaultResult = $this->calculate($candidate, $job);

        $job->forceFill([
            'fit_score_weights' => [
                'required_skills' => 60,
                'preferred_skills' => 10,
                'experience' => 10,
                'work_type' => 10,
                'location' => 5,
                'salary' => 5,
            ],
        ])->save();
        $job->refresh()->loadMissing('skills');

        $customResult = $this->calculate($candidate, $job);

        $this->assertSame(0, $defaultResult->signals['required_skills']['score']);
        $this->assertSame(100, $defaultResult->signals['experience']['score']);
        $this->assertNotSame($defaultResult->score, $customResult->score);
        $this->assertLessThan($defaultResult->score, $customResult->score);
    }

    private function calculate(CandidateProfile $candidate, Job $job): FitScoreResult
    {
        $candidate->loadMissing('candidateSkills');
        $job->loadMissing('skills');

        return $this->calculator->calculate($candidate, $job);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createCandidate(array $overrides = []): CandidateProfile
    {
        return CandidateProfile::factory()->create($overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createJob(array $overrides = []): Job
    {
        return Job::factory()->published()->create($overrides);
    }

    /**
     * @return array{0: CandidateProfile, 1: Job}
     */
    private function baselinePair(
        int $requiredCount,
        int $matchedRequired,
        bool $attachPreferred = true,
    ): array {
        $candidate = $this->createCandidate([
            'work_preference' => WorkPreference::Remote,
            'years_of_experience' => 4,
            'city' => 'Istanbul',
            'country' => 'Turkey',
            'desired_salary_min' => 60000,
            'desired_salary_max' => 90000,
        ]);

        $job = $this->createJob([
            'work_type' => WorkType::Remote,
            'experience_level' => ExperienceLevel::Mid,
            'city' => 'Istanbul',
            'country' => 'Turkey',
            'is_salary_visible' => $attachPreferred,
            'salary_min' => 70000,
            'salary_max' => 100000,
            'salary_currency' => 'TRY',
        ]);

        if ($requiredCount > 0) {
            $skills = Skill::factory()->count($requiredCount)->sequence(
                ...collect(range(1, $requiredCount))->map(fn (int $i): array => [
                    'name' => 'Required '.$i.'-'.uniqid(),
                ])->all(),
            )->create();

            foreach ($skills as $skill) {
                $job->skills()->attach($skill->id, ['importance' => SkillImportance::Required]);
            }

            foreach ($skills->take($matchedRequired) as $skill) {
                CandidateSkill::factory()->create([
                    'candidate_profile_id' => $candidate->id,
                    'skill_id' => $skill->id,
                ]);
            }
        }

        if ($attachPreferred) {
            $preferred = Skill::factory()->create(['name' => 'Docker-'.uniqid()]);
            $job->skills()->attach($preferred->id, ['importance' => SkillImportance::Preferred]);
            CandidateSkill::factory()->create([
                'candidate_profile_id' => $candidate->id,
                'skill_id' => $preferred->id,
            ]);
        }

        return [$candidate, $job];
    }
}

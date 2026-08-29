<?php

namespace Tests\Feature\Job;

use App\Enums\AiAnalysisStatus;
use App\Enums\AiAnalysisType;
use App\Enums\ExperienceLevel;
use App\Enums\JobStatus;
use App\Enums\SkillImportance;
use App\Enums\WorkPreference;
use App\Enums\WorkType;
use App\Jobs\AnalyzeCvJobFitJob;
use App\Models\AiAnalysis;
use App\Models\Application;
use App\Models\CandidateSkill;
use App\Models\Job;
use App\Models\Skill;
use App\Services\AI\CvJobFitAnalysisService;
use App\Services\FitScore\FitScoreCalculator;
use App\Services\FitScore\FitScoreInputFingerprint;
use App\Services\FitScore\FitScoreWeightResolver;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CompanyJobFitScoreSettingsTest extends TestCase
{
    use CreatesJobActors;

    /**
     * @return array<string, int>
     */
    private function defaultWeights(): array
    {
        return app(FitScoreWeightResolver::class)->defaultWeights();
    }

    /**
     * @return array<string, int>
     */
    private function customWeights(): array
    {
        return [
            'required_skills' => 45,
            'preferred_skills' => 10,
            'experience' => 25,
            'work_type' => 10,
            'location' => 5,
            'salary' => 5,
        ];
    }

    /**
     * @param  array<string, int>|null  $weights
     */
    private function setJobWeights(Job $job, ?array $weights): Job
    {
        $job->forceFill(['fit_score_weights' => $weights])->save();

        return $job->fresh(['skills']);
    }

    #[Test]
    public function get_returns_default_weights_when_no_custom_configuration(): void
    {
        [, $company, $token] = $this->createCompanyActor();
        $job = Job::factory()->draft()->create(['company_id' => $company->id]);

        $this->withToken($token)
            ->getJson('/api/v1/company/jobs/'.$job->id.'/fit-score-settings')
            ->assertOk()
            ->assertJsonPath('data.source', 'default')
            ->assertJsonPath('data.weights', $this->defaultWeights());
    }

    #[Test]
    public function company_can_set_custom_weights_on_draft_job(): void
    {
        [, $company, $token] = $this->createCompanyActor();
        $job = Job::factory()->draft()->create(['company_id' => $company->id]);

        $this->withToken($token)
            ->putJson('/api/v1/company/jobs/'.$job->id.'/fit-score-settings', [
                'weights' => $this->customWeights(),
            ])
            ->assertOk()
            ->assertJsonPath('data.source', 'custom')
            ->assertJsonPath('data.weights.required_skills', 45);

        $this->assertDatabaseHas('jobs', [
            'id' => $job->id,
        ]);
        $this->assertEqualsCanonicalizing($this->customWeights(), $job->fresh()->fit_score_weights);
    }

    #[Test]
    public function weights_must_sum_to_one_hundred(): void
    {
        [, $company, $token] = $this->createCompanyActor();
        $job = Job::factory()->draft()->create(['company_id' => $company->id]);
        $invalid = $this->customWeights();
        $invalid['salary'] = 10;

        $this->withToken($token)
            ->putJson('/api/v1/company/jobs/'.$job->id.'/fit-score-settings', [
                'weights' => $invalid,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['weights']);
    }

    #[Test]
    public function negative_weight_is_rejected(): void
    {
        [, $company, $token] = $this->createCompanyActor();
        $job = Job::factory()->draft()->create(['company_id' => $company->id]);
        $invalid = $this->customWeights();
        $invalid['experience'] = -5;

        $this->withToken($token)
            ->putJson('/api/v1/company/jobs/'.$job->id.'/fit-score-settings', [
                'weights' => $invalid,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['weights.experience']);
    }

    #[Test]
    public function unknown_signal_is_rejected(): void
    {
        [, $company, $token] = $this->createCompanyActor();
        $job = Job::factory()->draft()->create(['company_id' => $company->id]);
        $invalid = array_merge($this->customWeights(), ['unknown_signal' => 5]);

        $this->withToken($token)
            ->putJson('/api/v1/company/jobs/'.$job->id.'/fit-score-settings', [
                'weights' => $invalid,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['weights']);
    }

    #[Test]
    public function missing_signal_is_rejected(): void
    {
        [, $company, $token] = $this->createCompanyActor();
        $job = Job::factory()->draft()->create(['company_id' => $company->id]);
        $invalid = $this->customWeights();
        unset($invalid['salary']);

        $this->withToken($token)
            ->putJson('/api/v1/company/jobs/'.$job->id.'/fit-score-settings', [
                'weights' => $invalid,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['weights.salary']);
    }

    #[Test]
    public function all_zero_weights_are_rejected(): void
    {
        [, $company, $token] = $this->createCompanyActor();
        $job = Job::factory()->draft()->create(['company_id' => $company->id]);

        $this->withToken($token)
            ->putJson('/api/v1/company/jobs/'.$job->id.'/fit-score-settings', [
                'weights' => array_fill_keys(array_keys($this->defaultWeights()), 0),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['weights']);
    }

    #[Test]
    public function zero_weight_for_single_signal_is_allowed(): void
    {
        [, $company, $token] = $this->createCompanyActor();
        $job = Job::factory()->draft()->create(['company_id' => $company->id]);
        $weights = $this->customWeights();
        $weights['salary'] = 0;
        $weights['experience'] = 30;

        $this->withToken($token)
            ->putJson('/api/v1/company/jobs/'.$job->id.'/fit-score-settings', [
                'weights' => $weights,
            ])
            ->assertOk()
            ->assertJsonPath('data.weights.salary', 0);
    }

    #[Test]
    public function candidate_cannot_access_company_fit_score_settings(): void
    {
        [, , $token] = $this->createCandidateActor();
        $job = Job::factory()->draft()->create();

        $this->withToken($token)
            ->getJson('/api/v1/company/jobs/'.$job->id.'/fit-score-settings')
            ->assertForbidden();
    }

    #[Test]
    public function foreign_company_job_returns_not_found(): void
    {
        [, , $token] = $this->createCompanyActor();
        $job = Job::factory()->draft()->create();

        $this->withToken($token)
            ->getJson('/api/v1/company/jobs/'.$job->id.'/fit-score-settings')
            ->assertNotFound();
    }

    #[Test]
    public function scraped_job_returns_not_found(): void
    {
        [, $company, $token] = $this->createCompanyActor();
        $job = Job::factory()->scraped()->published()->create(['company_id' => $company->id]);

        $this->withToken($token)
            ->getJson('/api/v1/company/jobs/'.$job->id.'/fit-score-settings')
            ->assertNotFound();
    }

    #[Test]
    public function published_job_cannot_update_fit_score_settings(): void
    {
        [, $company, $token] = $this->createCompanyActor();
        $job = Job::factory()->published()->create(['company_id' => $company->id]);

        $this->withToken($token)
            ->putJson('/api/v1/company/jobs/'.$job->id.'/fit-score-settings', [
                'weights' => $this->customWeights(),
            ])
            ->assertForbidden();
    }

    #[Test]
    public function custom_weights_change_scoring_result(): void
    {
        [$candidate, $job] = $this->weightSensitivePair();

        $defaultScore = app(FitScoreCalculator::class)->calculate($candidate, $job)->score;

        $job = $this->setJobWeights($job, [
            'required_skills' => 60,
            'preferred_skills' => 10,
            'experience' => 10,
            'work_type' => 10,
            'location' => 5,
            'salary' => 5,
        ]);

        $customScore = app(FitScoreCalculator::class)->calculate($candidate, $job->fresh(['skills']))->score;

        $this->assertNotSame($defaultScore, $customScore);
        $this->assertLessThan($defaultScore, $customScore);
    }

    #[Test]
    public function same_input_and_weights_reuse_cached_analysis(): void
    {
        [$candidate, $job] = $this->weightSensitivePair();
        $job = $this->setJobWeights($job, $this->customWeights());
        $candidate->load(['candidateSkills', 'experiences', 'skills']);

        $service = app(CvJobFitAnalysisService::class);
        $first = $service->analyze($candidate, $job);
        $second = $service->analyze($candidate, $job);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, AiAnalysis::query()
            ->where('job_id', $job->id)
            ->where('candidate_profile_id', $candidate->id)
            ->where('type', AiAnalysisType::CvJobFit)
            ->count());
    }

    #[Test]
    public function same_input_with_different_weights_creates_new_analysis(): void
    {
        [$candidate, $job] = $this->weightSensitivePair();
        $candidate->load(['candidateSkills', 'experiences', 'skills']);
        $job->load('skills');

        $service = app(CvJobFitAnalysisService::class);
        $first = $service->analyze($candidate, $job);

        $job = $this->setJobWeights($job, $this->customWeights());

        $second = $service->analyze($candidate, $job);

        $this->assertNotSame($first->id, $second->id);
        $this->assertFalse($first->fresh()->is_latest);
        $this->assertTrue($second->is_latest);
    }

    #[Test]
    public function fingerprint_includes_custom_weights(): void
    {
        [$candidate, $job] = $this->weightSensitivePair();
        $before = FitScoreInputFingerprint::generate($candidate, $job);

        $job = $this->setJobWeights($job, $this->customWeights());
        $candidate->load(['candidateSkills', 'experiences', 'skills']);

        $this->assertNotSame($before, FitScoreInputFingerprint::generate($candidate, $job));
    }

    #[Test]
    public function analysis_details_include_weights_metadata(): void
    {
        [$candidate, $job] = $this->weightSensitivePair();
        $job = $this->setJobWeights($job, $this->customWeights());
        $candidate->load(['candidateSkills', 'experiences', 'skills']);

        $analysis = app(CvJobFitAnalysisService::class)->analyze($candidate, $job);

        $this->assertSame('custom', $analysis->details['weight_source']);
        $this->assertSame(45, $analysis->details['weights']['required_skills']);
    }

    #[Test]
    public function application_match_score_snapshot_does_not_change_when_weights_change(): void
    {
        [, $profile, $token] = $this->createCandidateActor();
        $profile->update(['work_preference' => WorkPreference::Remote, 'years_of_experience' => 8]);
        $job = Job::factory()->published()->withTrustScore(80)->create([
            'work_type' => WorkType::Remote,
            'experience_level' => ExperienceLevel::Senior,
        ]);

        $response = $this->withToken($token)
            ->postJson('/api/v1/candidate/applications', ['job_id' => $job->id])
            ->assertCreated();

        $snapshot = $response->json('data.match_score');
        $this->assertNotNull($snapshot);

        $this->setJobWeights($job, $this->customWeights());

        $this->assertSame($snapshot, Application::query()->find($response->json('data.id'))->match_score);
    }

    #[Test]
    public function clearing_custom_weights_changes_fingerprint(): void
    {
        [$candidate, $job] = $this->weightSensitivePair();
        $job = $this->setJobWeights($job, $this->customWeights());
        $candidate->load(['candidateSkills', 'experiences', 'skills']);

        $withCustom = FitScoreInputFingerprint::generate($candidate, $job);

        $job = $this->setJobWeights($job, null);

        $this->assertNotSame($withCustom, FitScoreInputFingerprint::generate($candidate, $job));
    }

    #[Test]
    public function queue_worker_uses_custom_weights(): void
    {
        [$candidate, $job] = $this->weightSensitivePair();
        $job = $this->setJobWeights($job, $this->customWeights());
        $candidate->load(['candidateSkills', 'experiences', 'skills']);

        (new AnalyzeCvJobFitJob($candidate->id, $job->id))
            ->handle(app(CvJobFitAnalysisService::class));

        $analysis = AiAnalysis::query()->where('job_id', $job->id)->where('is_latest', true)->first();
        $this->assertSame('custom', $analysis->details['weight_source']);
        $this->assertSame(AiAnalysisStatus::Completed, $analysis->status);
    }

    #[Test]
    public function job_detail_uses_custom_weights(): void
    {
        [, $profile, $token] = $this->createCandidateActor();
        $profile->update(['work_preference' => WorkPreference::Remote, 'years_of_experience' => 8]);
        $job = Job::factory()->published()->create([
            'slug' => 'custom-weight-detail',
            'work_type' => WorkType::Remote,
            'experience_level' => ExperienceLevel::Senior,
        ]);
        $this->setJobWeights($job, $this->customWeights());

        $this->withToken($token)
            ->getJson('/api/v1/jobs/custom-weight-detail')
            ->assertOk()
            ->assertJsonPath('data.fit_analysis_status', 'completed');

        $analysis = AiAnalysis::query()->where('job_id', $job->id)->where('is_latest', true)->first();
        $this->assertSame('custom', $analysis->details['weight_source']);
    }

    #[Test]
    public function job_list_uses_custom_weights_synchronously(): void
    {
        Queue::fake();

        [, $profile, $token] = $this->createCandidateActor();
        $profile->update(['work_preference' => WorkPreference::Remote, 'years_of_experience' => 8]);
        $category = 'custom-weight-list-'.uniqid();
        $job = Job::factory()->published()->create([
            'category' => $category,
            'work_type' => WorkType::Remote,
            'experience_level' => ExperienceLevel::Senior,
        ]);
        $this->setJobWeights($job, $this->customWeights());

        $this->withToken($token)
            ->getJson('/api/v1/jobs?category='.urlencode($category))
            ->assertOk()
            ->assertJsonPath('data.items.0.fit_analysis_status', 'completed');

        Queue::assertNothingPushed();

        $analysis = AiAnalysis::query()->where('job_id', $job->id)->where('is_latest', true)->first();
        $this->assertSame('custom', $analysis->details['weight_source']);
    }

    #[Test]
    public function legacy_default_analysis_remains_reusable_after_deploy(): void
    {
        [$candidate, $job] = $this->weightSensitivePair();
        $candidate->load(['candidateSkills', 'experiences', 'skills']);
        $job->load('skills');

        $legacyFingerprint = FitScoreInputFingerprint::generate($candidate, $job, legacy: true);
        $analysis = AiAnalysis::query()->create([
            'type' => AiAnalysisType::CvJobFit,
            'job_id' => $job->id,
            'candidate_profile_id' => $candidate->id,
            'score' => 70,
            'status' => AiAnalysisStatus::Completed,
            'is_latest' => true,
            'analysis_version' => 'fit-v1',
            'details' => [
                'input_fingerprint' => $legacyFingerprint,
                'fit_version' => 'fit-v1',
            ],
            'analyzed_at' => now(),
        ]);

        $this->assertTrue(FitScoreInputFingerprint::isReusable($analysis, $candidate, $job));
    }

    /**
     * @return array{0: \App\Models\CandidateProfile, 1: Job}
     */
    private function weightSensitivePair(): array
    {
        $candidate = \App\Models\CandidateProfile::factory()->create([
            'work_preference' => WorkPreference::Remote,
            'years_of_experience' => 8,
        ]);
        $job = Job::factory()->published()->create([
            'work_type' => WorkType::Remote,
            'experience_level' => ExperienceLevel::Senior,
            'is_salary_visible' => false,
        ]);

        $skills = Skill::factory()->count(2)->create();
        foreach ($skills as $skill) {
            $job->skills()->attach($skill->id, ['importance' => SkillImportance::Required]);
        }

        $job->load('skills');
        $candidate->load(['candidateSkills', 'experiences', 'skills']);

        return [$candidate, $job];
    }
}

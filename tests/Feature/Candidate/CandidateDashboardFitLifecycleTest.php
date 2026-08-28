<?php

declare(strict_types=1);

namespace Tests\Feature\Candidate;

use App\Enums\WorkPreference;
use App\Models\AiAnalysis;
use App\Models\Job;
use App\Services\AI\CvJobFitAnalysisService;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\CreatesReusableFitAnalysis;
use Tests\TestCase;

class CandidateDashboardFitLifecycleTest extends TestCase
{
    use CreatesCandidateUsers;
    use CreatesReusableFitAnalysis;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    #[Test]
    public function dashboard_excludes_stale_analysis_after_profile_input_changes(): void
    {
        [, $profile, $token] = $this->createCandidateActor();
        $this->seedCandidateCv($profile);
        $profile->update([
            'work_preference' => WorkPreference::Remote,
            'years_of_experience' => 3,
        ]);

        $job = Job::factory()->published()->create(['slug' => 'dashboard-stale-fit-role']);

        app(CvJobFitAnalysisService::class)->analyze($profile->fresh(), $job);

        $this->withToken($token)
            ->getJson('/api/v1/candidate/dashboard')
            ->assertOk()
            ->assertJsonPath('data.stats.analyzed_job_count', 1);

        $this->withToken($token)
            ->putJson('/api/v1/candidate/profile', [
                'years_of_experience' => 12,
            ])
            ->assertOk();

        $this->assertSame(12, $profile->fresh()->years_of_experience);

        $this->withToken($token)
            ->getJson('/api/v1/candidate/dashboard')
            ->assertOk()
            ->assertJsonPath('data.stats.analyzed_job_count', 0)
            ->assertJsonPath('data.stats.average_fit_score', null)
            ->assertJsonCount(0, 'data.analyzed_jobs');
    }

    #[Test]
    public function dashboard_does_not_show_fit_scores_after_cv_delete(): void
    {
        [, $profile, $token] = $this->createCandidateActor();
        $job = Job::factory()->published()->create(['slug' => 'dashboard-cv-delete-role']);

        $this->seedCandidateCv($profile);
        app(CvJobFitAnalysisService::class)->analyze($profile->fresh(), $job);

        $this->withToken($token)
            ->getJson('/api/v1/candidate/dashboard')
            ->assertOk()
            ->assertJsonPath('data.stats.analyzed_job_count', 1);

        $this->withToken($token)
            ->deleteJson('/api/v1/candidate/cv')
            ->assertOk();

        $this->withToken($token)
            ->getJson('/api/v1/candidate/dashboard')
            ->assertOk()
            ->assertJsonPath('data.stats.has_cv', false)
            ->assertJsonPath('data.stats.analyzed_job_count', 0)
            ->assertJsonPath('data.stats.average_fit_score', null)
            ->assertJsonCount(0, 'data.analyzed_jobs');

        $this->assertSame(
            1,
            AiAnalysis::query()
                ->where('candidate_profile_id', $profile->id)
                ->where('is_latest', true)
                ->count(),
            'Historical analysis rows remain in storage.',
        );
    }

    #[Test]
    public function job_detail_hides_fit_score_after_cv_delete(): void
    {
        [, $profile, $token] = $this->createCandidateActor();
        $job = Job::factory()->published()->create(['slug' => 'job-detail-cv-delete-role']);

        $this->seedCandidateCv($profile);
        app(CvJobFitAnalysisService::class)->analyze($profile->fresh(), $job);

        $this->withToken($token)
            ->getJson('/api/v1/jobs/job-detail-cv-delete-role')
            ->assertOk()
            ->assertJsonPath('data.fit_analysis_status', 'completed')
            ->assertJsonPath('data.fit_score', fn ($value) => $value !== null);

        $this->withToken($token)
            ->deleteJson('/api/v1/candidate/cv')
            ->assertOk();

        $this->withToken($token)
            ->getJson('/api/v1/jobs/job-detail-cv-delete-role')
            ->assertOk()
            ->assertJsonPath('data.fit_score', null)
            ->assertJsonPath('data.fit_analysis_status', null);
    }

    #[Test]
    public function stale_analysis_without_matching_fingerprint_is_not_counted(): void
    {
        [, $profile, $token] = $this->createCandidateActor();
        $job = Job::factory()->published()->create();

        AiAnalysis::query()->create([
            'type' => \App\Enums\AiAnalysisType::CvJobFit,
            'job_id' => $job->id,
            'candidate_profile_id' => $profile->id,
            'score' => 92,
            'status' => \App\Enums\AiAnalysisStatus::Completed,
            'is_latest' => true,
            'analysis_version' => config('fit_score.version'),
            'details' => ['input_fingerprint' => 'stale-fingerprint-value'],
            'analyzed_at' => now(),
        ]);

        $this->seedCandidateCv($profile);

        $this->withToken($token)
            ->getJson('/api/v1/candidate/dashboard')
            ->assertOk()
            ->assertJsonPath('data.stats.analyzed_job_count', 0)
            ->assertJsonPath('data.stats.average_fit_score', null);
    }

    #[Test]
    public function candidate_a_profile_change_does_not_affect_candidate_b_dashboard_fit(): void
    {
        [$userA, $profileA] = array_slice($this->createCandidateActor(), 0, 2);
        [$userB, $profileB] = array_slice($this->createCandidateActor(), 0, 2);

        $jobA = Job::factory()->published()->create();
        $jobB = Job::factory()->published()->create();

        $this->assertNotSame($profileA->id, $profileB->id);
        $this->assertNotSame($userA->id, $userB->id);

        $profileA->update(['years_of_experience' => 3]);

        $this->createReusableFitAnalysis($profileA->fresh(), $jobA, 85);
        $this->createReusableFitAnalysis($profileB->fresh(), $jobB, 55);

        $this->actingAs($userB, 'sanctum')
            ->getJson('/api/v1/candidate/dashboard')
            ->assertJsonPath('data.stats.analyzed_job_count', 1)
            ->assertJsonPath('data.stats.average_fit_score', 55);

        $this->actingAs($userA, 'sanctum')
            ->putJson('/api/v1/candidate/profile', ['years_of_experience' => 15])
            ->assertOk();

        $this->assertSame(15, $profileA->fresh()->years_of_experience);
        $this->assertNull($profileB->fresh()->years_of_experience);

        $this->actingAs($userA, 'sanctum')
            ->getJson('/api/v1/candidate/dashboard')
            ->assertJsonPath('data.stats.analyzed_job_count', 0);

        $this->actingAs($userB, 'sanctum')
            ->getJson('/api/v1/candidate/dashboard')
            ->assertJsonPath('data.stats.analyzed_job_count', 1)
            ->assertJsonPath('data.stats.average_fit_score', 55);
    }

    #[Test]
    public function dashboard_unpublished_job_exclusion_still_applies_with_current_analyses(): void
    {
        [, $profile, $token] = $this->createCandidateActor();

        $publishedJob = Job::factory()->published()->create(['status' => \App\Enums\JobStatus::Published]);
        $draftJob = Job::factory()->published()->create(['status' => \App\Enums\JobStatus::Draft]);

        $this->createReusableFitAnalysis($profile, $publishedJob, 80);
        $this->createReusableFitAnalysis($profile, $draftJob, 40);

        $this->withToken($token)
            ->getJson('/api/v1/candidate/dashboard')
            ->assertOk()
            ->assertJsonPath('data.stats.analyzed_job_count', 1)
            ->assertJsonPath('data.stats.average_fit_score', 80);
    }
}

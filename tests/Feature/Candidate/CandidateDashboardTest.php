<?php

declare(strict_types=1);

namespace Tests\Feature\Candidate;

use App\Enums\ApplicationStatus;
use App\Enums\JobStatus;
use App\Enums\TrustLabel;
use App\Models\Application;
use App\Models\Job;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\CreatesReusableFitAnalysis;
use Tests\TestCase;

class CandidateDashboardTest extends TestCase
{
    use CreatesCandidateUsers;
    use CreatesReusableFitAnalysis;

    #[Test]
    public function dashboard_requires_authentication(): void
    {
        $this->getJson('/api/v1/candidate/dashboard')
            ->assertUnauthorized();
    }

    #[Test]
    public function dashboard_returns_canonical_stats_for_candidate(): void
    {
        [, $profile, $token] = $this->createCandidateActor();

        Job::factory()->published()->create([
            'status' => JobStatus::Published,
            'trust_label' => TrustLabel::Verified,
            'trust_score' => 85,
        ]);

        Job::factory()->published()->create([
            'status' => JobStatus::Published,
            'trust_label' => TrustLabel::Suspicious,
            'trust_score' => 35,
        ]);

        Job::factory()->published()->create([
            'status' => JobStatus::Published,
            'trust_label' => TrustLabel::Unrated,
            'trust_score' => 60,
        ]);

        $appliedJob = Job::factory()->published()->create([
            'status' => JobStatus::Published,
            'trust_label' => TrustLabel::Verified,
            'trust_score' => 90,
        ]);

        Application::factory()->create([
            'candidate_profile_id' => $profile->id,
            'job_id' => $appliedJob->id,
        ]);

        $fitJob = Job::factory()->published()->create([
            'status' => JobStatus::Published,
            'trust_label' => TrustLabel::Verified,
            'trust_score' => 88,
        ]);

        $this->createReusableFitAnalysis($profile, $fitJob, 80);
        $this->createReusableFitAnalysis($profile, $appliedJob, 60);

        $response = $this->withToken($token)->getJson('/api/v1/candidate/dashboard');

        $response->assertOk()
            ->assertJsonPath('data.stats.trusted_jobs', 3)
            ->assertJsonPath('data.stats.suspicious_jobs', 1)
            ->assertJsonPath('data.stats.application_count', 1)
            ->assertJsonPath('data.stats.average_fit_score', 70)
            ->assertJsonPath('data.stats.analyzed_job_count', 2)
            ->assertJsonCount(4, 'data.recommended_jobs')
            ->assertJsonCount(2, 'data.analyzed_jobs');
    }

    #[Test]
    public function dashboard_fit_summary_excludes_unpublished_jobs(): void
    {
        [, $profile, $token] = $this->createCandidateActor();

        $publishedJob = Job::factory()->published()->create(['status' => JobStatus::Published]);
        $unpublishedJob = Job::factory()->published()->create(['status' => JobStatus::Draft]);

        $this->createReusableFitAnalysis($profile, $publishedJob, 80);
        $this->createReusableFitAnalysis($profile, $unpublishedJob, 40);

        $this->withToken($token)->getJson('/api/v1/candidate/dashboard')
            ->assertOk()
            ->assertJsonPath('data.stats.analyzed_job_count', 1)
            ->assertJsonPath('data.stats.average_fit_score', 80)
            ->assertJsonCount(1, 'data.analyzed_jobs');
    }

    #[Test]
    public function dashboard_recommendations_exclude_expired_jobs(): void
    {
        [, $profile, $token] = $this->createCandidateActor();

        $expiredJob = Job::factory()->published()->create([
            'status' => JobStatus::Published,
            'expires_at' => now()->subDay(),
        ]);

        $activeJob = Job::factory()->published()->create([
            'status' => JobStatus::Published,
            'expires_at' => now()->addMonth(),
        ]);

        $this->seedCandidateCv($profile);
        $this->createReusableFitAnalysis($profile, $expiredJob, 95);
        $this->createReusableFitAnalysis($profile, $activeJob, 70);

        $response = $this->withToken($token)->getJson('/api/v1/candidate/dashboard');

        $response->assertOk();
        $recommendedIds = collect($response->json('data.recommended_jobs'))->pluck('id')->all();

        $this->assertNotContains($expiredJob->id, $recommendedIds);
        $this->assertContains($activeJob->id, $recommendedIds);
    }

    #[Test]
    public function dashboard_is_isolated_between_candidates(): void
    {
        [, $profileA, $tokenA] = $this->createCandidateActor();
        [, $profileB, $tokenB] = $this->createCandidateActor();

        $job = Job::factory()->published()->create(['status' => JobStatus::Published]);

        $this->assertNotSame($profileA->id, $profileB->id);

        Application::query()->create([
            'candidate_profile_id' => $profileA->id,
            'job_id' => $job->id,
            'status' => ApplicationStatus::Submitted,
            'applied_at' => now(),
            'status_updated_at' => now(),
        ]);

        $this->assertSame(1, Application::query()->where('candidate_profile_id', $profileA->id)->count());
        $this->assertSame(0, Application::query()->where('candidate_profile_id', $profileB->id)->count());

        $this->actingAs($profileA->user, 'sanctum')->getJson('/api/v1/candidate/dashboard')
            ->assertJsonPath('data.stats.application_count', 1);

        $this->actingAs($profileB->user, 'sanctum')->getJson('/api/v1/candidate/dashboard')
            ->assertJsonPath('data.stats.application_count', 0);
    }

    #[Test]
    public function company_user_cannot_access_candidate_dashboard(): void
    {
        $token = $this->createCompanyActor();

        $this->withToken($token)->getJson('/api/v1/candidate/dashboard')
            ->assertForbidden();
    }
}

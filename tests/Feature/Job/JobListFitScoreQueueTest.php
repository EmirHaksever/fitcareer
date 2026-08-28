<?php

namespace Tests\Feature\Job;

use App\Enums\AiAnalysisStatus;
use App\Enums\AiAnalysisType;
use App\Enums\WorkPreference;
use App\Jobs\AnalyzeCvJobFitJob;
use App\Models\AiAnalysis;
use App\Models\Job;
use App\Services\AI\CvJobFitAnalysisService;
use App\Services\FitScore\FitScoreCalculator;
use Illuminate\Support\Facades\Queue;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class JobListFitScoreQueueTest extends TestCase
{
    use CreatesJobActors;

    #[Test]
    public function candidate_job_list_computes_fit_analyses_synchronously(): void
    {
        Queue::fake();

        [, $profile, $token] = $this->createCandidateActor();
        $profile->update(['work_preference' => WorkPreference::Remote, 'years_of_experience' => 4]);
        $category = 'queue-fit-list-'.uniqid();
        $jobs = Job::factory()->published()->count(2)->create([
            'category' => $category,
        ]);

        $response = $this->withToken($token)
            ->getJson('/api/v1/jobs?category='.urlencode($category))
            ->assertOk();

        Queue::assertNothingPushed();

        $items = $response->json('data.items');
        $this->assertCount(2, $items);
        $this->assertSame('completed', $items[0]['fit_analysis_status']);
        $this->assertNotNull($items[0]['fit_score']);
        $this->assertSame('completed', $items[1]['fit_analysis_status']);

        $this->assertSame(2, AiAnalysis::query()
            ->where('candidate_profile_id', $profile->id)
            ->whereIn('job_id', $jobs->pluck('id'))
            ->where('type', AiAnalysisType::CvJobFit)
            ->where('status', AiAnalysisStatus::Completed)
            ->where('is_latest', true)
            ->count());
    }

    #[Test]
    public function guest_job_list_does_not_dispatch_fit_analysis_jobs(): void
    {
        Queue::fake();

        Job::factory()->published()->count(2)->create();

        $this->getJson('/api/v1/jobs')->assertOk();

        Queue::assertNothingPushed();
    }

    #[Test]
    public function company_job_list_does_not_dispatch_fit_analysis_jobs(): void
    {
        Queue::fake();

        [, , $token] = $this->createCompanyActor();
        Job::factory()->published()->count(2)->create();

        $this->withToken($token)->getJson('/api/v1/jobs')->assertOk();

        Queue::assertNothingPushed();
    }

    #[Test]
    public function completed_reusable_analysis_is_not_requeued_on_job_list(): void
    {
        Queue::fake();

        [, $profile, $token] = $this->createCandidateActor();
        $profile->update(['work_preference' => WorkPreference::Remote, 'years_of_experience' => 4]);

        $category = 'queue-fit-reuse-'.uniqid();
        $job = Job::factory()->published()->create(['category' => $category]);
        app(CvJobFitAnalysisService::class)->analyze($profile, $job);

        $this->withToken($token)
            ->getJson('/api/v1/jobs?category='.urlencode($category))
            ->assertOk();

        Queue::assertNotPushed(AnalyzeCvJobFitJob::class, function (AnalyzeCvJobFitJob $queued) use ($profile, $job): bool {
            return $queued->candidateProfileId === $profile->id && $queued->jobId === $job->id;
        });
    }

    #[Test]
    public function repeated_candidate_job_list_requests_do_not_create_duplicate_pending_rows(): void
    {
        Queue::fake();

        [, $profile, $token] = $this->createCandidateActor();
        $category = 'queue-fit-repeat-'.uniqid();
        $job = Job::factory()->published()->create(['category' => $category]);

        $this->withToken($token)->getJson('/api/v1/jobs?category='.urlencode($category))->assertOk();
        $this->withToken($token)->getJson('/api/v1/jobs?category='.urlencode($category))->assertOk();

        $this->assertSame(1, AiAnalysis::query()
            ->where('candidate_profile_id', $profile->id)
            ->where('job_id', $job->id)
            ->where('type', AiAnalysisType::CvJobFit)
            ->where('is_latest', true)
            ->count());
    }

    #[Test]
    public function stale_completed_analysis_is_recomputed_on_job_list_when_fingerprint_changes(): void
    {
        Queue::fake();

        [, $profile, $token] = $this->createCandidateActor();
        $profile->update(['work_preference' => WorkPreference::Remote, 'years_of_experience' => 4]);

        $category = 'queue-fit-stale-'.uniqid();
        $job = Job::factory()->published()->create(['category' => $category]);
        $previous = app(CvJobFitAnalysisService::class)->analyze($profile, $job);

        $profile->update(['years_of_experience' => 10]);

        $response = $this->withToken($token)
            ->getJson('/api/v1/jobs?category='.urlencode($category))
            ->assertOk();

        Queue::assertNothingPushed();

        $this->assertSame('completed', $response->json('data.items.0.fit_analysis_status'));
        $this->assertNotNull($response->json('data.items.0.fit_score'));

        $latest = AiAnalysis::query()
            ->where('job_id', $job->id)
            ->where('candidate_profile_id', $profile->id)
            ->where('type', AiAnalysisType::CvJobFit)
            ->where('is_latest', true)
            ->first();

        $this->assertNotSame($previous->id, $latest?->id);
    }

    #[Test]
    public function failed_queue_job_preserves_completed_score_when_reanalysis_is_not_started(): void
    {
        [, $profile] = $this->createCandidateActor();
        $profile->update(['work_preference' => WorkPreference::Remote, 'years_of_experience' => 4]);

        $job = Job::factory()->published()->create();
        $completed = app(CvJobFitAnalysisService::class)->analyze($profile, $job);
        $completedScore = $completed->score;

        $this->assertNotNull($completedScore);

        $calculator = Mockery::mock(FitScoreCalculator::class);
        $calculator->shouldReceive('calculate')->andThrow(new \RuntimeException('Scoring failed'));
        $this->app->instance(FitScoreCalculator::class, $calculator);

        $jobInstance = new AnalyzeCvJobFitJob($profile->id, $job->id);

        try {
            $jobInstance->handle(app(CvJobFitAnalysisService::class));
        } catch (\RuntimeException) {
            // Expected for this test scenario.
        }

        $jobInstance->failed(new \RuntimeException('Scoring failed'));

        $completed->refresh();
        $this->assertTrue($completed->is_latest);
        $this->assertSame(AiAnalysisStatus::Completed, $completed->status);
        $this->assertSame($completedScore, $completed->score);
    }

    #[Test]
    public function paginated_job_list_computes_fit_analyses_for_visible_page_only(): void
    {
        Queue::fake();

        [, $profile, $token] = $this->createCandidateActor();
        $profile->update(['work_preference' => WorkPreference::Remote, 'years_of_experience' => 4]);
        $category = 'queue-fit-page-'.uniqid();
        Job::factory()->published()->count(3)->create(['category' => $category]);

        $response = $this->withToken($token)
            ->getJson('/api/v1/jobs?category='.urlencode($category).'&per_page=2&page=1')
            ->assertOk();

        $returnedJobIds = collect($response->json('data.items'))->pluck('id')->all();
        $this->assertCount(2, $returnedJobIds);

        Queue::assertNothingPushed();

        foreach ($response->json('data.items') as $item) {
            $this->assertSame('completed', $item['fit_analysis_status']);
            $this->assertNotNull($item['fit_score']);
        }

        $this->assertSame(2, AiAnalysis::query()
            ->where('candidate_profile_id', $profile->id)
            ->where('type', AiAnalysisType::CvJobFit)
            ->whereIn('job_id', $returnedJobIds)
            ->where('status', AiAnalysisStatus::Completed)
            ->count());
    }

    #[Test]
    public function existing_job_detail_sync_behavior_is_unchanged(): void
    {
        [, $profile, $token] = $this->createCandidateActor();
        $profile->update(['work_preference' => WorkPreference::Remote, 'years_of_experience' => 5]);

        $job = Job::factory()->published()->create(['slug' => 'detail-sync-role']);

        $this->withToken($token)
            ->getJson('/api/v1/jobs/detail-sync-role')
            ->assertOk()
            ->assertJsonPath('data.fit_analysis_status', 'completed');

        $this->assertNotNull($this->withToken($token)->getJson('/api/v1/jobs/detail-sync-role')->json('data.fit_score'));
    }

    #[Test]
    public function existing_application_snapshot_behavior_is_unchanged(): void
    {
        [, $profile, $token] = $this->createCandidateActor();
        $profile->update(['work_preference' => WorkPreference::Remote, 'years_of_experience' => 6]);

        $job = Job::factory()->published()->withTrustScore(88)->create();

        $this->withToken($token)->getJson('/api/v1/jobs/'.$job->slug)->assertOk();
        $analysisCountBeforeApply = AiAnalysis::query()
            ->where('job_id', $job->id)
            ->where('candidate_profile_id', $profile->id)
            ->where('type', AiAnalysisType::CvJobFit)
            ->count();

        $response = $this->withToken($token)
            ->postJson('/api/v1/candidate/applications', ['job_id' => $job->id])
            ->assertCreated();

        $this->assertNotNull($response->json('data.match_score'));
        $this->assertSame($analysisCountBeforeApply, AiAnalysis::query()
            ->where('job_id', $job->id)
            ->where('candidate_profile_id', $profile->id)
            ->where('type', AiAnalysisType::CvJobFit)
            ->count());
    }
}

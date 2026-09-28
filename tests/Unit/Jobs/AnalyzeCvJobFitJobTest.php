<?php

namespace Tests\Unit\Jobs;

use App\Enums\AiAnalysisStatus;
use App\Enums\AiAnalysisType;
use App\Jobs\AnalyzeCvJobFitJob;
use App\Models\AiAnalysis;
use App\Models\CandidateProfile;
use App\Models\Job;
use App\Services\AI\CvJobFitAnalysisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AnalyzeCvJobFitJobTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_is_unique_per_candidate_and_job_pair(): void
    {
        $job = new AnalyzeCvJobFitJob(10, 20);

        $this->assertSame('cv-job-fit:10:20', $job->uniqueId());
    }

    #[Test]
    public function it_marks_pending_analysis_as_failed_when_queue_job_fails(): void
    {
        $profile = CandidateProfile::factory()->create();
        $job = Job::factory()->published()->create();

        AiAnalysis::query()->create([
            'type' => AiAnalysisType::CvJobFit,
            'job_id' => $job->id,
            'candidate_profile_id' => $profile->id,
            'score' => null,
            'status' => AiAnalysisStatus::Pending,
            'is_latest' => true,
            'analysis_version' => 'fit-v1',
            'details' => ['input_fingerprint' => 'abc'],
        ]);

        (new AnalyzeCvJobFitJob($profile->id, $job->id))
            ->failed(new \RuntimeException('Worker failed'));

        $this->assertDatabaseHas('ai_analyses', [
            'job_id' => $job->id,
            'candidate_profile_id' => $profile->id,
            'status' => AiAnalysisStatus::Failed->value,
            'is_latest' => true,
        ]);
    }

    #[Test]
    public function duplicate_dispatches_are_prevented_by_unique_job_contract(): void
    {
        Queue::fake();

        AnalyzeCvJobFitJob::dispatch(1, 2);
        AnalyzeCvJobFitJob::dispatch(1, 2);

        Queue::assertPushed(AnalyzeCvJobFitJob::class, 1);
    }

    #[Test]
    public function handle_delegates_to_cv_job_fit_analysis_service(): void
    {
        $profile = CandidateProfile::factory()->create();
        $job = Job::factory()->published()->create();

        $service = $this->createMock(CvJobFitAnalysisService::class);
        $service->expects($this->once())
            ->method('analyze')
            ->with(
                $this->callback(fn (CandidateProfile $candidate): bool => $candidate->id === $profile->id),
                $this->callback(fn (Job $loadedJob): bool => $loadedJob->id === $job->id),
            );

        (new AnalyzeCvJobFitJob($profile->id, $job->id))->handle($service);
    }
}

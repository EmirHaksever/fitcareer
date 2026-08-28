<?php

namespace Tests\Feature\Job;

use App\Enums\CompanyVerificationStatus;
use App\Enums\JobStatus;
use App\Enums\TrustAnalysisStatus;
use App\Enums\TrustLabel;
use App\Events\JobTrustAnalysisFailed;
use App\Models\Company;
use App\Models\Job;
use App\Services\AI\JobTrustAnalysisService;
use App\Services\TrustScore\TrustScoreCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class JobTrustScoreTest extends TestCase
{
    use CreatesJobActors;
    use RefreshDatabase;

    #[Test]
    public function publish_triggers_trust_score_calculation(): void
    {
        [, $company, $token] = $this->createCompanyActor();

        $company->update([
            'is_verified' => true,
            'verification_status' => CompanyVerificationStatus::Verified,
            'contact_email' => 'hr@example.com',
            'website' => 'https://example.com',
        ]);

        $createResponse = $this->withToken($token)
            ->postJson('/api/v1/company/jobs', [
                'title' => 'Senior Backend Developer',
                'description' => str_repeat('Build reliable Laravel APIs for production systems. ', 4),
                'requirements' => 'PHP, Laravel, MySQL',
                'responsibilities' => 'Design APIs and review pull requests.',
                'employment_type' => 'full_time',
                'work_type' => 'remote',
                'category' => 'engineering',
                'city' => 'Istanbul',
                'country' => 'Turkey',
                'contact_email' => 'jobs@example.com',
                'is_salary_visible' => true,
                'salary_min' => 70000,
                'salary_max' => 95000,
            ])
            ->assertCreated();

        $jobId = $createResponse->json('data.id');

        $this->withToken($token)
            ->postJson('/api/v1/company/jobs/'.$jobId.'/publish')
            ->assertOk()
            ->assertJsonPath('data.trust_analysis_status', TrustAnalysisStatus::Completed->value)
            ->assertJsonPath('data.trust_label', TrustLabel::Verified->value);

        $job = Job::query()->findOrFail($jobId);

        $this->assertSame(TrustAnalysisStatus::Completed, $job->trust_analysis_status);
        $this->assertNotNull($job->trust_score);
        $this->assertGreaterThanOrEqual(75, $job->trust_score);
        $this->assertSame(TrustLabel::Verified, $job->trust_label);

        $this->getJson('/api/v1/jobs/'.$job->slug)
            ->assertOk()
            ->assertJsonPath('data.trust_score', $job->trust_score)
            ->assertJsonPath('data.trust_analysis_status', TrustAnalysisStatus::Completed->value);
    }

    #[Test]
    public function failed_trust_analysis_does_not_clear_existing_score(): void
    {
        Event::fake([JobTrustAnalysisFailed::class]);

        $job = Job::factory()->published()->withTrustScore(82)->create([
            'trust_label' => TrustLabel::Verified,
        ]);

        $calculator = \Mockery::mock(TrustScoreCalculator::class);
        $calculator->shouldReceive('calculate')->once()->andThrow(new \RuntimeException('Calculator failed'));

        $service = new JobTrustAnalysisService($calculator);

        $service->analyze($job->fresh(['company', 'sourceProvider']));

        Event::assertDispatched(JobTrustAnalysisFailed::class);

        $listener = app(\App\Listeners\UpdateJobTrustFieldsListener::class);
        $listener->handleJobTrustAnalysisFailed(new JobTrustAnalysisFailed($job->fresh()));

        $job->refresh();

        $this->assertSame(82, $job->trust_score);
        $this->assertSame(TrustLabel::Verified, $job->trust_label);
        $this->assertSame(TrustAnalysisStatus::Failed, $job->trust_analysis_status);
    }

    #[Test]
    public function unverified_company_publish_still_produces_trust_score(): void
    {
        [, $company, $token] = $this->createCompanyActor();

        $company->update([
            'is_verified' => false,
            'verification_status' => CompanyVerificationStatus::Unverified,
            'contact_email' => 'contact@example.com',
            'website' => 'https://example.com',
        ]);

        $createResponse = $this->withToken($token)
            ->postJson('/api/v1/company/jobs', [
                'title' => 'Backend Developer',
                'description' => str_repeat('Build reliable Laravel APIs for production systems. ', 4),
                'requirements' => 'PHP',
                'employment_type' => 'full_time',
                'work_type' => 'remote',
                'country' => 'Turkey',
                'contact_email' => 'jobs@example.com',
            ])
            ->assertCreated();

        $jobId = $createResponse->json('data.id');

        $this->withToken($token)
            ->postJson('/api/v1/company/jobs/'.$jobId.'/publish')
            ->assertOk();

        $job = Job::query()->findOrFail($jobId);

        $this->assertSame(TrustAnalysisStatus::Completed, $job->trust_analysis_status);
        $this->assertNotNull($job->trust_score);
        $this->assertNotSame(TrustLabel::Verified, $job->trust_label);
    }
}

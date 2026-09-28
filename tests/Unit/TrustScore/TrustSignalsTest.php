<?php

namespace Tests\Unit\TrustScore;

use App\Enums\CompanyVerificationStatus;
use App\Enums\JobOrigin;
use App\Enums\JobReportReason;
use App\Enums\JobReportStatus;
use App\Enums\JobStatus;
use App\Models\Company;
use App\Models\Job;
use App\Models\JobReport;
use App\Models\JobSource;
use App\Services\TrustScore\Signals\CompanyVerificationSignal;
use App\Services\TrustScore\Signals\ContactInformationSignal;
use App\Services\TrustScore\Signals\ContentCompletenessSignal;
use App\Services\TrustScore\Signals\JobFreshnessSignal;
use App\Services\TrustScore\Signals\ModerationSignal;
use App\Services\TrustScore\Signals\ReportPenaltySignal;
use App\Services\TrustScore\Signals\SalaryTransparencySignal;
use App\Services\TrustScore\Signals\SourceReliabilitySignal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TrustSignalsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function verified_company_signal_scores_high(): void
    {
        $company = Company::factory()->create([
            'is_verified' => true,
            'verification_status' => CompanyVerificationStatus::Verified,
        ]);

        $job = Job::factory()->make(['company_id' => $company->id]);
        $job->setRelation('company', $company);

        $result = app(CompanyVerificationSignal::class)->evaluate($job);

        $this->assertSame(95, $result->score);
    }

    #[Test]
    public function unverified_company_signal_scores_lower(): void
    {
        $company = Company::factory()->create([
            'is_verified' => false,
            'verification_status' => CompanyVerificationStatus::Unverified,
        ]);

        $job = Job::factory()->make(['company_id' => $company->id]);
        $job->setRelation('company', $company);

        $result = app(CompanyVerificationSignal::class)->evaluate($job);

        $this->assertSame(45, $result->score);
    }

    #[Test]
    public function flagged_job_moderation_signal_scores_low(): void
    {
        $job = Job::factory()->make(['status' => JobStatus::Flagged]);

        $result = app(ModerationSignal::class)->evaluate($job);

        $this->assertSame(10, $result->score);
    }

    #[Test]
    public function missing_contact_information_returns_unknown(): void
    {
        $company = Company::factory()->make([
            'contact_email' => null,
            'contact_phone' => null,
            'website' => null,
        ]);

        $job = Job::factory()->make([
            'contact_email' => null,
            'contact_phone' => null,
        ]);
        $job->setRelation('company', $company);

        $result = app(ContactInformationSignal::class)->evaluate($job);

        $this->assertNull($result->score);
    }

    #[Test]
    public function incomplete_content_scores_lower_than_complete_content(): void
    {
        $minimal = Job::factory()->make([
            'title' => 'Dev',
            'description' => 'Short description without enough detail.',
            'requirements' => null,
            'responsibilities' => null,
        ]);

        $complete = Job::factory()->make([
            'title' => 'Senior Backend Developer',
            'description' => str_repeat('Reliable Laravel APIs for production systems. ', 5),
            'requirements' => 'PHP, Laravel, MySQL',
            'responsibilities' => 'Build APIs and mentor the team.',
        ]);

        $minimalResult = app(ContentCompletenessSignal::class)->evaluate($minimal);
        $completeResult = app(ContentCompletenessSignal::class)->evaluate($complete);

        $this->assertLessThan($completeResult->score, $minimalResult->score);
    }

    #[Test]
    public function expired_job_freshness_signal_scores_low(): void
    {
        $job = Job::factory()->make([
            'published_at' => now()->subDays(10),
            'expires_at' => now()->subDay(),
        ]);

        $result = app(JobFreshnessSignal::class)->evaluate($job);

        $this->assertSame(15, $result->score);
    }

    #[Test]
    public function reported_job_penalty_signal_reduces_score(): void
    {
        $job = Job::factory()->create();

        JobReport::query()->create([
            'job_id' => $job->id,
            'reason' => JobReportReason::ScamSuspected,
            'status' => JobReportStatus::Reported,
        ]);

        $result = app(ReportPenaltySignal::class)->evaluate($job->fresh());

        $this->assertLessThan(100, $result->score);
    }

    #[Test]
    public function hidden_salary_returns_unknown(): void
    {
        $job = Job::factory()->make([
            'is_salary_visible' => false,
            'salary_min' => 50000,
            'salary_max' => 80000,
        ]);

        $result = app(SalaryTransparencySignal::class)->evaluate($job);

        $this->assertNull($result->score);
    }

    #[Test]
    public function visible_salary_returns_high_score(): void
    {
        $job = Job::factory()->make([
            'is_salary_visible' => true,
            'salary_min' => 50000,
            'salary_max' => 80000,
        ]);

        $result = app(SalaryTransparencySignal::class)->evaluate($job);

        $this->assertSame(90, $result->score);
    }

    #[Test]
    public function internal_source_scores_higher_than_scraped_source(): void
    {
        $internalJob = Job::factory()->make(['source' => JobOrigin::Internal]);
        $source = JobSource::factory()->create();
        $scrapedJob = Job::factory()->scraped()->make(['job_source_id' => $source->id]);
        $scrapedJob->setRelation('sourceProvider', $source);

        $internalScore = app(SourceReliabilitySignal::class)->evaluate($internalJob)->score;
        $scrapedScore = app(SourceReliabilitySignal::class)->evaluate($scrapedJob)->score;

        $this->assertGreaterThan($scrapedScore, $internalScore);
    }
}

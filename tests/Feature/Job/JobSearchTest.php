<?php

namespace Tests\Feature\Job;

use App\Enums\AiAnalysisStatus;
use App\Enums\AiAnalysisType;
use App\Enums\EmploymentType;
use App\Enums\ExperienceLevel;
use App\Enums\JobOrigin;
use App\Enums\TrustAnalysisStatus;
use App\Enums\WorkType;
use App\Models\AiAnalysis;
use App\Models\Company;
use App\Models\Job;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class JobSearchTest extends TestCase
{
    use CreatesJobActors;

    protected function setUp(): void
    {
        parent::setUp();

        config(['rate_limits.job_search.per_user_per_minute' => 30]);
    }

    #[Test]
    public function guest_can_list_published_jobs(): void
    {
        Job::factory()->published()->create([
            'title' => 'Visible Job',
            'category' => 'guest-listing-case',
        ]);
        Job::factory()->draft()->create([
            'title' => 'Hidden Draft',
            'category' => 'guest-listing-case',
        ]);

        $this->getJson('/api/v1/jobs?category=guest-listing-case')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.title', 'Visible Job');
    }

    #[Test]
    public function filters_apply_category_location_employment_work_experience_and_salary(): void
    {
        Job::factory()->published()->create([
            'title' => 'Matching Job',
            'category' => 'engineering',
            'city' => 'Istanbul',
            'country' => 'Turkey',
            'employment_type' => EmploymentType::FullTime,
            'work_type' => WorkType::Remote,
            'experience_level' => ExperienceLevel::Senior,
            'salary_min' => 70000,
            'salary_max' => 90000,
            'is_salary_visible' => true,
        ]);
        Job::factory()->published()->create([
            'title' => 'Other Job',
            'category' => 'marketing',
            'city' => 'Berlin',
            'country' => 'Germany',
            'employment_type' => EmploymentType::PartTime,
            'work_type' => WorkType::Onsite,
            'experience_level' => ExperienceLevel::Entry,
            'salary_min' => 30000,
            'salary_max' => 40000,
            'is_salary_visible' => true,
        ]);

        $this->getJson('/api/v1/jobs?'.http_build_query([
            'category' => 'engineering',
            'location' => 'Istanbul',
            'employment_type' => 'full_time',
            'work_type' => 'remote',
            'experience_level' => 'senior',
            'min_salary' => 60000,
            'max_salary' => 100000,
        ]))
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.title', 'Matching Job');
    }

    #[Test]
    public function min_trust_score_filters_completed_trust_scores(): void
    {
        Job::factory()->published()->withTrustScore(90)->create(['title' => 'Trusted Job']);
        Job::factory()->published()->create([
            'title' => 'Pending Trust Job',
            'trust_score' => null,
            'trust_analysis_status' => TrustAnalysisStatus::Pending,
        ]);

        $this->getJson('/api/v1/jobs?min_trust_score=80')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.title', 'Trusted Job');
    }

    #[Test]
    public function authenticated_candidate_can_filter_by_min_fit_score(): void
    {
        [, $profile, $token] = $this->createCandidateActor();
        $matchingJob = Job::factory()->published()->create(['title' => 'High Fit Job']);
        $lowFitJob = Job::factory()->published()->create(['title' => 'Low Fit Job']);

        AiAnalysis::query()->create([
            'type' => AiAnalysisType::CvJobFit,
            'job_id' => $matchingJob->id,
            'candidate_profile_id' => $profile->id,
            'score' => 85,
            'status' => AiAnalysisStatus::Completed,
            'is_latest' => true,
        ]);
        AiAnalysis::query()->create([
            'type' => AiAnalysisType::CvJobFit,
            'job_id' => $lowFitJob->id,
            'candidate_profile_id' => $profile->id,
            'score' => 40,
            'status' => AiAnalysisStatus::Completed,
            'is_latest' => true,
        ]);

        $this->withToken($token)
            ->getJson('/api/v1/jobs?min_fit_score=70')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.title', 'High Fit Job');
    }

    #[Test]
    public function guest_min_fit_score_returns_422(): void
    {
        $this->getJson('/api/v1/jobs?min_fit_score=50')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['min_fit_score']);
    }

    #[Test]
    public function authenticated_non_candidate_min_fit_score_returns_422(): void
    {
        [, , $token] = $this->createCompanyActor();

        $this->withToken($token)
            ->getJson('/api/v1/jobs?min_fit_score=50')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['min_fit_score']);
    }

    #[Test]
    public function candidate_profile_id_spoofing_is_rejected(): void
    {
        [, , $token] = $this->createCandidateActor();

        $this->withToken($token)
            ->getJson('/api/v1/jobs?candidate_profile_id=999&min_fit_score=10')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['candidate_profile_id']);
    }

    #[Test]
    public function pagination_and_sorting_work(): void
    {
        Job::factory()->published()->create([
            'title' => 'Older Job',
            'category' => 'pagination-sorting-case',
            'published_at' => now()->subDays(2),
            'salary_max' => 50000,
        ]);
        Job::factory()->published()->create([
            'title' => 'Newer Job',
            'category' => 'pagination-sorting-case',
            'published_at' => now()->subDay(),
            'salary_max' => 90000,
        ]);

        $this->getJson('/api/v1/jobs?category=pagination-sorting-case&per_page=1&page=1')
            ->assertOk()
            ->assertJsonPath('data.pagination.per_page', 1)
            ->assertJsonPath('data.pagination.total', 2)
            ->assertJsonCount(1, 'data.items');

        $this->getJson('/api/v1/jobs?category=pagination-sorting-case&sort=salary')
            ->assertOk()
            ->assertJsonPath('data.items.0.title', 'Newer Job');
    }

    #[Test]
    public function search_does_not_trigger_n_plus_one_queries(): void
    {
        $company = Company::factory()->create();
        Job::factory()->count(3)->published()->create(['company_id' => $company->id]);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson('/api/v1/jobs')->assertOk();
        $baselineQueries = count(DB::getQueryLog());

        Job::factory()->count(3)->published()->create(['company_id' => $company->id]);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson('/api/v1/jobs')->assertOk();
        $expandedQueries = count(DB::getQueryLog());

        $this->assertSame($baselineQueries, $expandedQueries);
    }

    #[Test]
    public function job_search_rate_limit_returns_429(): void
    {
        config(['rate_limits.job_search.per_user_per_minute' => 2]);
        Job::factory()->published()->create();

        $this->getJson('/api/v1/jobs')->assertOk();
        $this->getJson('/api/v1/jobs')->assertOk();
        $this->getJson('/api/v1/jobs')
            ->assertStatus(429)
            ->assertJsonPath('success', false);

        RateLimiter::clear('job-search');
    }

    #[Test]
    public function validation_errors_use_standard_api_format(): void
    {
        $this->getJson('/api/v1/jobs?employment_type=invalid')
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed.');
    }

    #[Test]
    public function default_search_excludes_foreign_and_unknown_jobs(): void
    {
        Job::factory()->published()->create([
            'title' => 'Turkey Job',
            'category' => 'turkey-scope-case',
            'source' => JobOrigin::Scraped,
            'city' => 'Istanbul',
            'country' => 'Turkey',
        ]);
        Job::factory()->published()->create([
            'title' => 'Foreign Job',
            'category' => 'turkey-scope-case',
            'source' => JobOrigin::Scraped,
            'city' => 'Berlin',
            'country' => 'Germany',
        ]);
        Job::factory()->published()->create([
            'title' => 'Unknown Job',
            'category' => 'turkey-scope-case',
            'source' => JobOrigin::Scraped,
            'city' => null,
            'country' => null,
            'work_type' => WorkType::Remote,
        ]);

        $this->getJson('/api/v1/jobs?category=turkey-scope-case')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.title', 'Turkey Job');
    }

    #[Test]
    public function include_global_search_returns_foreign_and_unknown_jobs(): void
    {
        Job::factory()->published()->create([
            'title' => 'Turkey Job Global Case',
            'category' => 'turkey-scope-global-case',
            'source' => JobOrigin::Scraped,
            'city' => 'Ankara',
            'country' => 'Turkey',
        ]);
        Job::factory()->published()->create([
            'title' => 'Foreign Job Global Case',
            'category' => 'turkey-scope-global-case',
            'source' => JobOrigin::Scraped,
            'city' => 'London',
            'country' => 'UK',
        ]);
        Job::factory()->published()->create([
            'title' => 'Unknown Job Global Case',
            'category' => 'turkey-scope-global-case',
            'source' => JobOrigin::Scraped,
            'city' => null,
            'country' => null,
            'work_type' => WorkType::Remote,
        ]);

        $this->getJson('/api/v1/jobs?category=turkey-scope-global-case&include_global=1')
            ->assertOk()
            ->assertJsonCount(3, 'data.items');
    }
}

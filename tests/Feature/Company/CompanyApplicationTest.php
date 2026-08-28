<?php

namespace Tests\Feature\Company;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\ApplicationStatusHistory;
use App\Models\CandidateProfile;
use App\Models\Company;
use App\Models\Job;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Job\CreatesJobActors;
use Tests\TestCase;

class CompanyApplicationTest extends TestCase
{
    use CreatesJobActors;

    /**
     * @return array{0: Job, 1: Application, 2: CandidateProfile}
     */
    private function createCompanyApplication(?Company $company = null, ?Job $job = null): array
    {
        if ($company === null || $job === null) {
            [$companyUser, $company] = $this->createCompanyActor();
            $job = $job ?? Job::factory()->published()->create([
                'company_id' => $company->id,
                'posted_by' => $companyUser->id,
            ]);
        }

        [, $profile] = $this->createCandidateActor();

        $application = Application::factory()->create([
            'candidate_profile_id' => $profile->id,
            'job_id' => $job->id,
            'status' => ApplicationStatus::Submitted,
        ]);

        ApplicationStatusHistory::query()->create([
            'application_id' => $application->id,
            'from_status' => null,
            'to_status' => ApplicationStatus::Submitted,
        ]);

        return [$job, $application, $profile];
    }

    #[Test]
    public function guest_cannot_access_company_applications(): void
    {
        $this->getJson('/api/v1/company/applications')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    #[Test]
    public function candidate_cannot_access_company_applications(): void
    {
        [, , $token] = $this->createCandidateActor();
        [, $application] = $this->createCompanyApplication();

        $this->withToken($token)
            ->getJson('/api/v1/company/applications')
            ->assertForbidden()
            ->assertJsonPath('message', 'Forbidden.');

        $this->withToken($token)
            ->getJson('/api/v1/company/applications/'.$application->id)
            ->assertForbidden();

        $this->withToken($token)
            ->patchJson('/api/v1/company/applications/'.$application->id.'/status', [
                'status' => ApplicationStatus::UnderReview->value,
            ])
            ->assertForbidden();
    }

    #[Test]
    public function company_can_list_applications_for_own_jobs(): void
    {
        [$companyUser, $company, $token] = $this->createCompanyActor();
        $job = Job::factory()->published()->create([
            'company_id' => $company->id,
            'posted_by' => $companyUser->id,
        ]);

        [, $application, $profile] = $this->createCompanyApplication($company, $job);

        $this->withToken($token)
            ->getJson('/api/v1/company/applications')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.items.0.id', $application->id)
            ->assertJsonPath('data.items.0.job.id', $job->id)
            ->assertJsonPath('data.items.0.candidate.id', $profile->id)
            ->assertJsonPath('data.items.0.candidate.user.name', $profile->user->name);
    }

    #[Test]
    public function company_cannot_view_another_companys_application(): void
    {
        [, $application] = $this->createCompanyApplication();
        [, , $foreignToken] = $this->createCompanyActor();

        $this->withToken($foreignToken)
            ->getJson('/api/v1/company/applications/'.$application->id)
            ->assertNotFound();
    }

    #[Test]
    public function company_can_view_application_detail_with_status_history(): void
    {
        [$companyUser, $company, $token] = $this->createCompanyActor();
        $job = Job::factory()->published()->create([
            'company_id' => $company->id,
            'posted_by' => $companyUser->id,
        ]);
        [, $application, $profile] = $this->createCompanyApplication($company, $job);

        ApplicationStatusHistory::query()->create([
            'application_id' => $application->id,
            'from_status' => ApplicationStatus::Submitted,
            'to_status' => ApplicationStatus::UnderReview,
        ]);

        $this->withToken($token)
            ->getJson('/api/v1/company/applications/'.$application->id)
            ->assertOk()
            ->assertJsonPath('data.id', $application->id)
            ->assertJsonPath('data.candidate.id', $profile->id)
            ->assertJsonPath('data.job.title', $job->title)
            ->assertJsonCount(2, 'data.status_history');
    }

    #[Test]
    public function company_can_transition_application_status(): void
    {
        [$companyUser, $company, $token] = $this->createCompanyActor();
        $job = Job::factory()->published()->create([
            'company_id' => $company->id,
            'posted_by' => $companyUser->id,
        ]);
        [, $application] = $this->createCompanyApplication($company, $job);

        $this->withToken($token)
            ->patchJson('/api/v1/company/applications/'.$application->id.'/status', [
                'status' => ApplicationStatus::UnderReview->value,
                'note' => 'Review started.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', ApplicationStatus::UnderReview->value);

        $this->assertDatabaseHas('applications', [
            'id' => $application->id,
            'status' => ApplicationStatus::UnderReview->value,
        ]);

        $this->assertDatabaseHas('application_status_history', [
            'application_id' => $application->id,
            'from_status' => ApplicationStatus::Submitted->value,
            'to_status' => ApplicationStatus::UnderReview->value,
            'note' => 'Review started.',
        ]);
    }

    #[Test]
    public function company_cannot_transition_with_invalid_status(): void
    {
        [$companyUser, $company, $token] = $this->createCompanyActor();
        $job = Job::factory()->published()->create([
            'company_id' => $company->id,
            'posted_by' => $companyUser->id,
        ]);
        [, $application] = $this->createCompanyApplication($company, $job);

        $this->withToken($token)
            ->patchJson('/api/v1/company/applications/'.$application->id.'/status', [
                'status' => ApplicationStatus::Interview->value,
            ])
            ->assertConflict()
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('applications', [
            'id' => $application->id,
            'status' => ApplicationStatus::Submitted->value,
        ]);
    }

    #[Test]
    public function company_cannot_transition_from_terminal_status(): void
    {
        [$companyUser, $company, $token] = $this->createCompanyActor();
        $job = Job::factory()->published()->create([
            'company_id' => $company->id,
            'posted_by' => $companyUser->id,
        ]);
        [, $application] = $this->createCompanyApplication($company, $job);

        $application->update(['status' => ApplicationStatus::Rejected]);

        $this->withToken($token)
            ->patchJson('/api/v1/company/applications/'.$application->id.'/status', [
                'status' => ApplicationStatus::UnderReview->value,
            ])
            ->assertConflict();

        $this->assertDatabaseHas('applications', [
            'id' => $application->id,
            'status' => ApplicationStatus::Rejected->value,
        ]);
    }

    #[Test]
    public function company_cannot_transition_another_companys_application(): void
    {
        [, $application] = $this->createCompanyApplication();
        [, , $foreignToken] = $this->createCompanyActor();

        $this->withToken($foreignToken)
            ->patchJson('/api/v1/company/applications/'.$application->id.'/status', [
                'status' => ApplicationStatus::UnderReview->value,
            ])
            ->assertNotFound();

        $this->assertDatabaseHas('applications', [
            'id' => $application->id,
            'status' => ApplicationStatus::Submitted->value,
        ]);
    }

    #[Test]
    public function company_application_list_supports_pagination_and_filters(): void
    {
        [$companyUser, $company, $token] = $this->createCompanyActor();
        $jobA = Job::factory()->published()->create([
            'company_id' => $company->id,
            'posted_by' => $companyUser->id,
        ]);
        $jobB = Job::factory()->published()->create([
            'company_id' => $company->id,
            'posted_by' => $companyUser->id,
        ]);

        foreach (range(1, 12) as $index) {
            [, $profile] = $this->createCandidateActor();
            Application::factory()->create([
                'candidate_profile_id' => $profile->id,
                'job_id' => $index <= 6 ? $jobA->id : $jobB->id,
                'status' => $index % 2 === 0
                    ? ApplicationStatus::UnderReview
                    : ApplicationStatus::Submitted,
            ]);
        }

        $this->withToken($token)
            ->getJson('/api/v1/company/applications?per_page=5')
            ->assertOk()
            ->assertJsonPath('data.pagination.per_page', 5)
            ->assertJsonPath('data.pagination.total', 12)
            ->assertJsonPath('data.pagination.last_page', 3)
            ->assertJsonCount(5, 'data.items');

        $this->withToken($token)
            ->getJson('/api/v1/company/applications?job_id='.$jobA->id)
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 6);

        $this->withToken($token)
            ->getJson('/api/v1/company/applications?status='.ApplicationStatus::UnderReview->value)
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 6);
    }

    #[Test]
    public function company_application_list_excludes_foreign_company_jobs(): void
    {
        [$companyUser, $company, $token] = $this->createCompanyActor();
        $foreignCompany = Company::factory()->create();
        $foreignJob = Job::factory()->published()->create(['company_id' => $foreignCompany->id]);

        $this->createCompanyApplication($company, Job::factory()->published()->create([
            'company_id' => $company->id,
            'posted_by' => $companyUser->id,
        ]));

        $this->createCompanyApplication($foreignCompany, $foreignJob);

        $this->withToken($token)
            ->getJson('/api/v1/company/applications')
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1);
    }

    #[Test]
    public function company_status_update_validates_required_fields(): void
    {
        [, , $token] = $this->createCompanyActor();
        [, $application] = $this->createCompanyApplication();

        $this->withToken($token)
            ->patchJson('/api/v1/company/applications/'.$application->id.'/status', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    #[Test]
    public function company_status_update_rejects_mass_assignment_fields(): void
    {
        [, , $token] = $this->createCompanyActor();
        [, $application] = $this->createCompanyApplication();

        $this->withToken($token)
            ->patchJson('/api/v1/company/applications/'.$application->id.'/status', [
                'status' => ApplicationStatus::UnderReview->value,
                'match_score' => 99,
                'candidate_profile_id' => 999,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['match_score', 'candidate_profile_id']);
    }

    #[Test]
    public function company_application_list_validates_query_parameters(): void
    {
        [, , $token] = $this->createCompanyActor();

        $this->withToken($token)
            ->getJson('/api/v1/company/applications?per_page=100')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page']);
    }

    #[Test]
    public function company_application_detail_exposes_completed_match_without_inventing_score(): void
    {
        [$companyUser, $company, $token] = $this->createCompanyActor();
        $job = Job::factory()->published()->create([
            'company_id' => $company->id,
            'posted_by' => $companyUser->id,
            'experience_level' => \App\Enums\ExperienceLevel::Entry,
        ]);
        [, $application, $profile] = $this->createCompanyApplication($company, $job);

        $application->update(['match_score' => 88]);

        \App\Models\AiAnalysis::query()->create([
            'type' => \App\Enums\AiAnalysisType::CvJobFit,
            'job_id' => $job->id,
            'candidate_profile_id' => $profile->id,
            'score' => 88,
            'status' => \App\Enums\AiAnalysisStatus::Completed,
            'is_latest' => true,
            'details' => [
                'signals' => [
                    'required_skills' => [
                        'score' => 75,
                        'confidence' => 0.9,
                        'evidence' => [
                            'matched_skills' => ['PHP', 'Laravel'],
                            'missing_skills' => ['Docker'],
                        ],
                    ],
                    'experience' => [
                        'score' => 80,
                        'confidence' => 0.9,
                        'evidence' => [
                            'job_experience_level' => 'entry',
                            'candidate_years_of_experience' => 2,
                        ],
                    ],
                ],
            ],
        ]);

        $this->withToken($token)
            ->getJson('/api/v1/company/applications/'.$application->id)
            ->assertOk()
            ->assertJsonPath('data.match_score', 88)
            ->assertJsonPath('data.match_analysis_status', 'completed')
            ->assertJsonPath('data.match_details.signals.required_skills.evidence.matched_skills.0', 'PHP')
            ->assertJsonPath('data.match_details.signals.required_skills.evidence.missing_skills.0', 'Docker')
            ->assertJsonPath('data.job.experience_level', 'entry');
    }

    #[Test]
    public function pending_match_does_not_expose_a_fake_percentage(): void
    {
        [$companyUser, $company, $token] = $this->createCompanyActor();
        $job = Job::factory()->published()->create([
            'company_id' => $company->id,
            'posted_by' => $companyUser->id,
        ]);
        [, $application, $profile] = $this->createCompanyApplication($company, $job);

        \App\Models\AiAnalysis::query()->create([
            'type' => \App\Enums\AiAnalysisType::CvJobFit,
            'job_id' => $job->id,
            'candidate_profile_id' => $profile->id,
            'score' => null,
            'status' => \App\Enums\AiAnalysisStatus::Pending,
            'is_latest' => true,
        ]);

        $this->withToken($token)
            ->getJson('/api/v1/company/applications/'.$application->id)
            ->assertOk()
            ->assertJsonPath('data.match_score', null)
            ->assertJsonPath('data.match_analysis_status', 'pending')
            ->assertJsonPath('data.match_details', null);
    }

    #[Test]
    public function null_match_without_analysis_is_unavailable_not_zero(): void
    {
        [$companyUser, $company, $token] = $this->createCompanyActor();
        $job = Job::factory()->published()->create([
            'company_id' => $company->id,
            'posted_by' => $companyUser->id,
        ]);
        [, $application] = $this->createCompanyApplication($company, $job);

        $this->withToken($token)
            ->getJson('/api/v1/company/applications/'.$application->id)
            ->assertOk()
            ->assertJsonPath('data.match_score', null)
            ->assertJsonPath('data.match_analysis_status', null)
            ->assertJsonPath('data.match_details', null);
    }

    #[Test]
    public function company_application_list_sorts_by_attention_then_match(): void
    {
        [$companyUser, $company, $token] = $this->createCompanyActor();
        $job = Job::factory()->published()->create([
            'company_id' => $company->id,
            'posted_by' => $companyUser->id,
        ]);

        $rejected = $this->createScoredApplication($job, ApplicationStatus::Rejected, 99, now()->subDays(1));
        $lowReview = $this->createScoredApplication($job, ApplicationStatus::Submitted, 50, now()->subHours(2));
        $highReview = $this->createScoredApplication($job, ApplicationStatus::Submitted, 90, now()->subHours(3));
        $pendingReview = $this->createScoredApplication($job, ApplicationStatus::UnderReview, null, now()->subHours(1));

        $response = $this->withToken($token)
            ->getJson('/api/v1/company/applications')
            ->assertOk();

        $this->assertSame(
            [$highReview->id, $lowReview->id, $pendingReview->id, $rejected->id],
            array_column($response->json('data.items'), 'id'),
        );

        $highestFirst = $this->withToken($token)
            ->getJson('/api/v1/company/applications?sort=match_score_desc')
            ->assertOk();

        $this->assertSame(
            [$rejected->id, $highReview->id, $lowReview->id, $pendingReview->id],
            array_column($highestFirst->json('data.items'), 'id'),
        );

        $this->assertArrayNotHasKey('match_details', $response->json('data.items.0'));
    }

    #[Test]
    public function company_job_list_includes_applications_count(): void
    {
        [$companyUser, $company, $token] = $this->createCompanyActor();
        $job = Job::factory()->published()->create([
            'company_id' => $company->id,
            'posted_by' => $companyUser->id,
            'applications_count' => 12,
        ]);

        $this->withToken($token)
            ->getJson('/api/v1/company/jobs')
            ->assertOk()
            ->assertJsonPath('data.items.0.id', $job->id)
            ->assertJsonPath('data.items.0.applications_count', 12);
    }

    #[Test]
    public function company_application_list_rejects_unknown_sort(): void
    {
        [, , $token] = $this->createCompanyActor();

        $this->withToken($token)
            ->getJson('/api/v1/company/applications?sort=not_a_sort')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sort']);
    }

    private function createScoredApplication(
        Job $job,
        ApplicationStatus $status,
        ?int $matchScore,
        \DateTimeInterface $appliedAt,
    ): Application {
        [, $profile] = $this->createCandidateActor();

        return Application::factory()->create([
            'candidate_profile_id' => $profile->id,
            'job_id' => $job->id,
            'status' => $status,
            'match_score' => $matchScore,
            'applied_at' => $appliedAt,
        ]);
    }
}

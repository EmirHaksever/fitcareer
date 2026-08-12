<?php

namespace Tests\Feature\Candidate;

use App\Enums\AiAnalysisStatus;
use App\Enums\AiAnalysisType;
use App\Enums\ApplicationStatus;
use App\Models\AiAnalysis;
use App\Services\FitScore\FitScoreInputFingerprint;
use App\Models\Application;
use App\Models\ApplicationStatusHistory;
use App\Models\Job;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Job\CreatesJobActors;
use Tests\TestCase;

class CandidateApplicationTest extends TestCase
{
    use CreatesJobActors;

    private function createPublishedJob(array $attributes = []): Job
    {
        [$user, $company] = $this->createCompanyActor();

        return Job::factory()->published()->create(array_merge([
            'company_id' => $company->id,
            'posted_by' => $user->id,
        ], $attributes));
    }

    #[Test]
    public function guest_cannot_list_applications(): void
    {
        $this->getJson('/api/v1/candidate/applications')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    #[Test]
    public function guest_cannot_submit_application(): void
    {
        $job = $this->createPublishedJob();

        $this->postJson('/api/v1/candidate/applications', ['job_id' => $job->id])
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    #[Test]
    public function guest_cannot_show_application(): void
    {
        $this->getJson('/api/v1/candidate/applications/1')
            ->assertUnauthorized();
    }

    #[Test]
    public function company_user_cannot_access_candidate_applications(): void
    {
        [, , $token] = $this->createCompanyActor();
        $job = $this->createPublishedJob();

        $this->withToken($token)
            ->getJson('/api/v1/candidate/applications')
            ->assertForbidden()
            ->assertJsonPath('message', 'Forbidden.');

        $this->withToken($token)
            ->postJson('/api/v1/candidate/applications', ['job_id' => $job->id])
            ->assertForbidden();

        $this->withToken($token)
            ->getJson('/api/v1/candidate/applications/1')
            ->assertForbidden();
    }

    #[Test]
    public function candidate_can_submit_application_successfully(): void
    {
        [, $profile, $token] = $this->createCandidateActor();
        $job = $this->createPublishedJob()->fresh('company');

        $response = $this->withToken($token)
            ->postJson('/api/v1/candidate/applications', [
                'job_id' => $job->id,
                'cover_letter' => 'I am excited to apply.',
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Application submitted.')
            ->assertJsonPath('data.job_id', $job->id)
            ->assertJsonPath('data.status', ApplicationStatus::Submitted->value)
            ->assertJsonPath('data.cover_letter', 'I am excited to apply.')
            ->assertJsonPath('data.job.id', $job->id)
            ->assertJsonPath('data.job.title', $job->title)
            ->assertJsonPath('data.job.company.id', $job->company->id)
            ->assertJsonPath('data.status_history.0.from_status', null)
            ->assertJsonPath('data.status_history.0.to_status', ApplicationStatus::Submitted->value);

        $this->assertDatabaseHas('applications', [
            'candidate_profile_id' => $profile->id,
            'job_id' => $job->id,
            'status' => ApplicationStatus::Submitted->value,
            'cover_letter' => 'I am excited to apply.',
        ]);

        $this->assertDatabaseHas('application_status_history', [
            'from_status' => null,
            'to_status' => ApplicationStatus::Submitted->value,
        ]);

        $this->assertSame(1, $job->fresh()->applications_count);
    }

    #[Test]
    public function candidate_cannot_apply_twice_to_same_job(): void
    {
        [, $profile, $token] = $this->createCandidateActor();
        $job = $this->createPublishedJob();

        $this->withToken($token)
            ->postJson('/api/v1/candidate/applications', ['job_id' => $job->id])
            ->assertCreated();

        $this->withToken($token)
            ->postJson('/api/v1/candidate/applications', ['job_id' => $job->id])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.job_id.0', 'You have already applied to this job.');

        $this->assertSame(1, Application::query()
            ->where('candidate_profile_id', $profile->id)
            ->where('job_id', $job->id)
            ->count());
    }

    #[Test]
    public function candidate_cannot_apply_to_invalid_job(): void
    {
        [, , $token] = $this->createCandidateActor();

        $this->withToken($token)
            ->postJson('/api/v1/candidate/applications', ['job_id' => 999999])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['job_id']);
    }

    #[Test]
    public function candidate_cannot_apply_to_unpublished_job(): void
    {
        [, , $token] = $this->createCandidateActor();
        [$user, $company] = $this->createCompanyActor();
        $job = Job::factory()->draft()->create([
            'company_id' => $company->id,
            'posted_by' => $user->id,
        ]);

        $this->withToken($token)
            ->postJson('/api/v1/candidate/applications', ['job_id' => $job->id])
            ->assertUnprocessable()
            ->assertJsonPath('errors.job_id.0', 'This job is not accepting applications.');
    }

    #[Test]
    public function candidate_cannot_apply_to_expired_job(): void
    {
        [, , $token] = $this->createCandidateActor();
        $job = $this->createPublishedJob([
            'expires_at' => now()->subDay(),
        ]);

        $this->withToken($token)
            ->postJson('/api/v1/candidate/applications', ['job_id' => $job->id])
            ->assertUnprocessable()
            ->assertJsonPath('errors.job_id.0', 'This job is not accepting applications.');
    }

    #[Test]
    public function candidate_cannot_apply_after_application_deadline(): void
    {
        [, , $token] = $this->createCandidateActor();
        $job = $this->createPublishedJob([
            'application_deadline' => now()->subDay()->toDateString(),
        ]);

        $this->withToken($token)
            ->postJson('/api/v1/candidate/applications', ['job_id' => $job->id])
            ->assertUnprocessable()
            ->assertJsonPath('errors.job_id.0', 'This job is not accepting applications.');
    }

    #[Test]
    public function candidate_cannot_access_another_candidates_application(): void
    {
        [, , $token] = $this->createCandidateActor();
        [, $otherProfile] = $this->createCandidateActor();
        $job = $this->createPublishedJob();

        $foreignApplication = Application::factory()->create([
            'candidate_profile_id' => $otherProfile->id,
            'job_id' => $job->id,
        ]);

        $this->withToken($token)
            ->getJson('/api/v1/candidate/applications/'.$foreignApplication->id)
            ->assertNotFound();
    }

    #[Test]
    public function candidate_can_list_only_own_applications_with_pagination(): void
    {
        [, $profile, $token] = $this->createCandidateActor();
        [, $otherProfile] = $this->createCandidateActor();

        $ownedJobs = collect(range(1, 16))->map(fn () => $this->createPublishedJob());

        foreach ($ownedJobs as $job) {
            Application::factory()->create([
                'candidate_profile_id' => $profile->id,
                'job_id' => $job->id,
            ]);
        }

        $foreignJob = $this->createPublishedJob();
        Application::factory()->create([
            'candidate_profile_id' => $otherProfile->id,
            'job_id' => $foreignJob->id,
        ]);

        $firstPage = $this->withToken($token)
            ->getJson('/api/v1/candidate/applications?per_page=10')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.pagination.per_page', 10)
            ->assertJsonPath('data.pagination.total', 16)
            ->assertJsonPath('data.pagination.last_page', 2)
            ->assertJsonCount(10, 'data.items');

        $secondPage = $this->withToken($token)
            ->getJson('/api/v1/candidate/applications?page=2&per_page=10')
            ->assertOk()
            ->assertJsonCount(6, 'data.items');

        $returnedIds = collect($firstPage->json('data.items'))
            ->merge($secondPage->json('data.items'))
            ->pluck('id');

        $this->assertCount(16, $returnedIds->unique());
        $this->assertTrue(
            Application::query()
                ->where('candidate_profile_id', $profile->id)
                ->whereIn('id', $returnedIds)
                ->count() === 16
        );
    }

    #[Test]
    public function candidate_can_show_application_with_relations_and_status_history(): void
    {
        [, $profile, $token] = $this->createCandidateActor();
        $job = $this->createPublishedJob();

        $application = Application::factory()->create([
            'candidate_profile_id' => $profile->id,
            'job_id' => $job->id,
        ]);

        ApplicationStatusHistory::query()->create([
            'application_id' => $application->id,
            'from_status' => null,
            'to_status' => ApplicationStatus::Submitted,
        ]);

        ApplicationStatusHistory::query()->create([
            'application_id' => $application->id,
            'from_status' => ApplicationStatus::Submitted,
            'to_status' => ApplicationStatus::UnderReview,
        ]);

        $this->withToken($token)
            ->getJson('/api/v1/candidate/applications/'.$application->id)
            ->assertOk()
            ->assertJsonPath('data.id', $application->id)
            ->assertJsonPath('data.job.id', $job->id)
            ->assertJsonPath('data.job.company.id', $job->company_id)
            ->assertJsonCount(2, 'data.status_history')
            ->assertJsonPath('data.status_history.0.to_status', ApplicationStatus::Submitted->value)
            ->assertJsonPath('data.status_history.1.to_status', ApplicationStatus::UnderReview->value);
    }

    #[Test]
    public function store_application_validates_required_fields(): void
    {
        [, , $token] = $this->createCandidateActor();

        $this->withToken($token)
            ->postJson('/api/v1/candidate/applications', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['job_id']);
    }

    #[Test]
    public function store_application_rejects_candidate_profile_id_spoofing(): void
    {
        [, , $token] = $this->createCandidateActor();
        [, $otherProfile] = $this->createCandidateActor();
        $job = $this->createPublishedJob();

        $this->withToken($token)
            ->postJson('/api/v1/candidate/applications', [
                'job_id' => $job->id,
                'candidate_profile_id' => $otherProfile->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['candidate_profile_id']);
    }

    #[Test]
    public function store_application_rejects_mass_assignment_fields(): void
    {
        [, , $token] = $this->createCandidateActor();
        $job = $this->createPublishedJob();

        $this->withToken($token)
            ->postJson('/api/v1/candidate/applications', [
                'job_id' => $job->id,
                'status' => ApplicationStatus::Offered->value,
                'match_score' => 99,
                'trust_score' => 99,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status', 'match_score', 'trust_score']);
    }

    #[Test]
    public function application_snapshots_fit_and_trust_scores_when_available(): void
    {
        [, $profile, $token] = $this->createCandidateActor();
        $job = $this->createPublishedJob([
            'trust_score' => 88,
        ])->fresh();
        $job->update([
            'trust_analysis_status' => \App\Enums\TrustAnalysisStatus::Completed,
            'trust_label' => \App\Enums\TrustLabel::Verified,
        ]);

        $job->load('skills');
        $profile->load(['candidateSkills', 'experiences', 'skills']);
        $fingerprint = FitScoreInputFingerprint::generate($profile, $job);

        AiAnalysis::query()->create([
            'type' => AiAnalysisType::CvJobFit,
            'job_id' => $job->id,
            'candidate_profile_id' => $profile->id,
            'score' => 76,
            'status' => AiAnalysisStatus::Completed,
            'is_latest' => true,
            'analysis_version' => 'fit-v1',
            'details' => [
                'signals' => [],
                'confidence' => 1.0,
                'input_fingerprint' => $fingerprint,
                'fit_version' => 'fit-v1',
                'candidate_updated_at' => $profile->updated_at?->toIso8601String(),
                'job_updated_at' => $job->updated_at?->toIso8601String(),
            ],
            'analyzed_at' => now(),
        ]);

        $this->withToken($token)
            ->postJson('/api/v1/candidate/applications', ['job_id' => $job->id])
            ->assertCreated()
            ->assertJsonPath('data.match_score', 76)
            ->assertJsonPath('data.trust_score', 88);
    }

    #[Test]
    public function application_snapshots_resume_when_candidate_has_cv(): void
    {
        Storage::fake('local');

        [, $profile, $token] = $this->createCandidateActor();
        $job = $this->createPublishedJob();

        $cvPath = UploadedFile::fake()->create('resume.pdf', 100, 'application/pdf')
            ->store((string) config('candidate.cv.storage_path'), 'local');

        $profile->update(['cv_file_path' => $cvPath]);

        $response = $this->withToken($token)
            ->postJson('/api/v1/candidate/applications', ['job_id' => $job->id])
            ->assertCreated();

        $snapshotPath = $response->json('data.resume_snapshot_path');

        $this->assertNotNull($snapshotPath);
        Storage::disk('local')->assertExists($snapshotPath);
    }

    #[Test]
    public function list_applications_validates_pagination_parameters(): void
    {
        [, , $token] = $this->createCandidateActor();

        $this->withToken($token)
            ->getJson('/api/v1/candidate/applications?per_page=100')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page']);
    }
}

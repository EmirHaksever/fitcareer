<?php

declare(strict_types=1);

namespace App\Services\Application;

use App\Enums\AiAnalysisStatus;
use App\Enums\ApplicationStatus;
use App\Enums\JobOrigin;
use App\Enums\JobStatus;
use App\Events\ApplicationStatusChanged;
use App\Exceptions\InvalidStatusTransitionException;
use App\Models\Application;
use App\Models\ApplicationStatusHistory;
use App\Models\CandidateProfile;
use App\Models\Job;
use App\Models\User;
use App\Services\AI\CvJobFitAnalysisService;
use App\Support\JobScorePresenter;
use App\Support\ResolvesCandidateProfile;
use App\Support\ResolvesCompany;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ApplicationService
{
    use ResolvesCandidateProfile;
    use ResolvesCompany;

    public function __construct(
        private readonly CvJobFitAnalysisService $cvJobFitAnalysisService,
    ) {}

    /** @var array<string, list<ApplicationStatus>> */
    private const ALLOWED_TRANSITIONS = [
        'submitted' => [
            ApplicationStatus::UnderReview,
            ApplicationStatus::Rejected,
            ApplicationStatus::Withdrawn,
        ],
        'under_review' => [
            ApplicationStatus::Shortlisted,
            ApplicationStatus::Rejected,
            ApplicationStatus::Withdrawn,
        ],
        'shortlisted' => [
            ApplicationStatus::Interview,
            ApplicationStatus::Rejected,
            ApplicationStatus::Withdrawn,
        ],
        'interview' => [
            ApplicationStatus::Offered,
            ApplicationStatus::Rejected,
            ApplicationStatus::Withdrawn,
        ],
        'offered' => [
            ApplicationStatus::Rejected,
            ApplicationStatus::Withdrawn,
        ],
    ];

    /**
     * @return list<string|array<string, mixed>>
     */
    private function companyApplicationRelations(): array
    {
        return [
            'job',
            'candidateProfile.user',
            'statusHistory' => fn ($query) => $query->orderBy('created_at'),
        ];
    }

    /**
     * @return LengthAwarePaginator<int, Application>
     */
    public function listForCompany(
        User $user,
        int $page = 1,
        int $perPage = 15,
        ?int $jobId = null,
        ?ApplicationStatus $status = null,
    ): LengthAwarePaginator {
        $company = $this->resolveCompany($user);

        return Application::query()
            ->whereHas('job', function ($query) use ($company): void {
                $query->where('company_id', $company->id)
                    ->where('source', JobOrigin::Internal);
            })
            ->when($jobId !== null, fn ($query) => $query->where('job_id', $jobId))
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->with($this->companyApplicationRelations())
            ->orderByDesc('applied_at')
            ->paginate(perPage: $perPage, page: $page);
    }

    public function getForCompany(User $user, int $applicationId): Application
    {
        $company = $this->resolveCompany($user);

        /** @var Application|null $application */
        $application = Application::query()
            ->whereKey($applicationId)
            ->whereHas('job', function ($query) use ($company): void {
                $query->where('company_id', $company->id)
                    ->where('source', JobOrigin::Internal);
            })
            ->with($this->companyApplicationRelations())
            ->first();

        if ($application === null) {
            abort(404, 'Application not found.');
        }

        return $application;
    }

    public function transitionForCompany(
        User $user,
        int $applicationId,
        ApplicationStatus $toStatus,
        ?string $note = null,
    ): Application {
        $this->getForCompany($user, $applicationId);

        $this->transitionStatus($applicationId, $toStatus, $user, $note);

        return $this->getForCompany($user, $applicationId);
    }

    /**
     * @return LengthAwarePaginator<int, Application>
     */
    public function listForUser(User $user, int $page = 1, int $perPage = 15): LengthAwarePaginator
    {
        $profile = $this->resolveCandidateProfile($user);

        return $profile->applications()
            ->with([
                'job.company',
                'statusHistory' => fn ($query) => $query->orderBy('created_at'),
            ])
            ->orderByDesc('applied_at')
            ->paginate(perPage: $perPage, page: $page);
    }

    public function getForUser(User $user, int $applicationId): Application
    {
        $profile = $this->resolveCandidateProfile($user);

        /** @var Application $application */
        $application = $this->findOwnedResource(
            $profile,
            'applications',
            $applicationId,
            Application::class,
        );

        return $application->load([
            'job.company',
            'statusHistory' => fn ($query) => $query->orderBy('created_at'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function submit(User $user, array $payload): Application
    {
        $profile = $this->resolveCandidateProfile($user);
        $jobId = (int) $payload['job_id'];
        $coverLetter = Arr::get($payload, 'cover_letter');

        return DB::transaction(function () use ($profile, $jobId, $coverLetter, $user): Application {
            /** @var Job $job */
            $job = Job::query()->whereKey($jobId)->lockForUpdate()->first();

            if ($job === null) {
                throw ValidationException::withMessages([
                    'job_id' => ['The selected job is invalid.'],
                ]);
            }

            $this->assertJobAcceptsApplications($job);
            $this->assertNoExistingApplication($profile->id, $jobId);

            $scores = $this->resolveSnapshotScores($job, $profile->id);
            $now = now();

            $application = Application::query()->create([
                'candidate_profile_id' => $profile->id,
                'job_id' => $job->id,
                'cover_letter' => $coverLetter,
                'status' => ApplicationStatus::Submitted,
                'match_score' => $scores['match_score'],
                'trust_score' => $scores['trust_score'],
                'applied_at' => $now,
                'status_updated_at' => $now,
            ]);

            $resumePath = $this->snapshotResume($profile, $application);

            if ($resumePath !== null) {
                $application->update(['resume_snapshot_path' => $resumePath]);
            }

            ApplicationStatusHistory::query()->create([
                'application_id' => $application->id,
                'from_status' => null,
                'to_status' => ApplicationStatus::Submitted,
                'note' => null,
                'changed_by' => $user->id,
            ]);

            $job->increment('applications_count');

            return $application->load([
                'job.company',
                'statusHistory' => fn ($query) => $query->orderBy('created_at'),
            ]);
        });
    }

    public function transitionStatus(
        int $applicationId,
        ApplicationStatus $toStatus,
        ?User $actor = null,
        ?string $note = null,
    ): Application {
        return DB::transaction(function () use ($applicationId, $toStatus, $actor, $note): Application {
            /** @var Application|null $application */
            $application = Application::query()
                ->whereKey($applicationId)
                ->lockForUpdate()
                ->first();

            if ($application === null) {
                abort(404, 'Application not found.');
            }

            $fromStatus = $application->status;

            if (! self::isTransitionAllowed($fromStatus, $toStatus)) {
                throw new InvalidStatusTransitionException($fromStatus, $toStatus);
            }

            $application->update([
                'status' => $toStatus,
                'status_updated_at' => now(),
            ]);

            ApplicationStatusHistory::query()->create([
                'application_id' => $application->id,
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'note' => $note,
                'changed_by' => $actor?->id,
            ]);

            event(new ApplicationStatusChanged($application, $fromStatus, $toStatus));

            return $application->fresh([
                'job.company',
                'statusHistory' => fn ($query) => $query->orderBy('created_at'),
            ]);
        });
    }

    public static function isTransitionAllowed(
        ApplicationStatus $fromStatus,
        ApplicationStatus $toStatus,
    ): bool {
        $allowed = self::ALLOWED_TRANSITIONS[$fromStatus->value] ?? [];

        return in_array($toStatus, $allowed, true);
    }

    private function assertJobAcceptsApplications(Job $job): void
    {
        if ($job->status !== JobStatus::Published) {
            throw ValidationException::withMessages([
                'job_id' => ['This job is not accepting applications.'],
            ]);
        }

        if ($job->expires_at !== null && $job->expires_at->isPast()) {
            throw ValidationException::withMessages([
                'job_id' => ['This job is not accepting applications.'],
            ]);
        }

        if ($job->application_deadline !== null && $job->application_deadline->isBefore(today())) {
            throw ValidationException::withMessages([
                'job_id' => ['This job is not accepting applications.'],
            ]);
        }
    }

    private function assertNoExistingApplication(int $candidateProfileId, int $jobId): void
    {
        $exists = Application::query()
            ->where('candidate_profile_id', $candidateProfileId)
            ->where('job_id', $jobId)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'job_id' => ['You have already applied to this job.'],
            ]);
        }
    }

    /**
     * @return array{match_score: ?int, trust_score: ?int}
     */
    private function resolveSnapshotScores(Job $job, int $candidateProfileId): array
    {
        $profile = CandidateProfile::query()->find($candidateProfileId);

        $fitAnalysis = $profile !== null
            ? $this->cvJobFitAnalysisService->analyze($profile, $job)
            : JobScorePresenter::resolveFitAnalysis($job, $candidateProfileId);

        $matchScore = ($fitAnalysis !== null && $fitAnalysis->status === AiAnalysisStatus::Completed)
            ? $fitAnalysis->score
            : null;

        $trust = JobScorePresenter::trustFields($job);

        return [
            'match_score' => $matchScore,
            'trust_score' => $trust['trust_score'],
        ];
    }

    private function snapshotResume($profile, Application $application): ?string
    {
        if (blank($profile->cv_file_path)) {
            return null;
        }

        $diskName = (string) config('candidate.cv.storage_disk');
        $disk = Storage::disk($diskName);

        if (! $disk->exists($profile->cv_file_path)) {
            return null;
        }

        $extension = pathinfo($profile->cv_file_path, PATHINFO_EXTENSION) ?: 'pdf';
        $destination = sprintf(
            'candidate/application-cvs/%d/%d.%s',
            $profile->id,
            $application->id,
            $extension,
        );

        $disk->copy($profile->cv_file_path, $destination);

        return $destination;
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Job;

use App\Enums\JobOrigin;
use App\Enums\JobStatus;
use App\Enums\TrustAnalysisStatus;
use App\Enums\TrustLabel;
use App\Models\CandidateProfile;
use App\Models\Company;
use App\Models\Job;
use App\Models\User;
use App\Services\AI\CvJobFitAnalysisService;
use App\Services\AI\JobTrustAnalysisService;
use App\Services\Job\InternalJobQualityGate;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class JobService
{
    public function __construct(
        private readonly JobTrustAnalysisService $jobTrustAnalysisService,
        private readonly CvJobFitAnalysisService $cvJobFitAnalysisService,
    ) {}

    /** @var list<string> */
    private const UPDATABLE_FIELDS = [
        'title',
        'description',
        'requirements',
        'responsibilities',
        'category',
        'employment_type',
        'work_type',
        'experience_level',
        'city',
        'country',
        'salary_min',
        'salary_max',
        'salary_currency',
        'is_salary_visible',
        'application_deadline',
        'contact_email',
        'contact_phone',
    ];

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(User $actor, array $payload): Job
    {
        $company = $this->resolveCompany($actor);
        $title = (string) $payload['title'];

        $job = new Job([
            'company_id' => $company->id,
            'posted_by' => $actor->id,
            'source' => JobOrigin::Internal,
            'title' => $title,
            'slug' => $this->generateUniqueSlug($title),
            'description' => $payload['description'],
            'employment_type' => $payload['employment_type'],
            'work_type' => $payload['work_type'],
            'status' => JobStatus::Draft,
            'trust_label' => TrustLabel::Unrated,
            'trust_analysis_status' => TrustAnalysisStatus::Pending,
            'trust_score' => null,
        ]);

        $job->fill(Arr::only($payload, self::UPDATABLE_FIELDS));
        $job->save();

        return $job->fresh(['company', 'sourceProvider', 'skills']);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(Job $job, array $payload): Job
    {
        $this->assertInternalMutable($job);

        $job->fill(Arr::only($payload, self::UPDATABLE_FIELDS));

        if (array_key_exists('title', $payload) && filled($payload['title'])) {
            $job->slug = $this->generateUniqueSlug((string) $payload['title'], $job->id);
        }

        $job->save();

        return $job->fresh(['company', 'sourceProvider', 'skills']);
    }

    public function publish(Job $job): Job
    {
        $this->assertInternalMutable($job);

        if ($job->status !== JobStatus::Draft) {
            throw ValidationException::withMessages([
                'status' => ['Only draft jobs can be published.'],
            ]);
        }

        InternalJobQualityGate::assertJobPublishable($job);

        $job->status = JobStatus::Published;
        $job->published_at = now();
        $job->save();

        $publishedJob = $job->fresh(['company', 'sourceProvider', 'skills']);

        $this->jobTrustAnalysisService->analyze($publishedJob);

        return $publishedJob->fresh(['company', 'sourceProvider', 'skills']);
    }

    public function getPublishedBySlug(string $slug, ?int $candidateProfileId = null): Job
    {
        $job = Job::query()
            ->with([
                'company',
                'sourceProvider',
                'skills',
                'analyses' => function ($query) use ($candidateProfileId): void {
                    if ($candidateProfileId !== null) {
                        $query->where('candidate_profile_id', $candidateProfileId)
                            ->where('is_latest', true);
                    }
                },
            ])
            ->where('slug', $slug)
            ->where('status', JobStatus::Published)
            ->where(function ($query): void {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->first();

        if ($job === null) {
            abort(404, 'Job not found.');
        }

        if ($candidateProfileId !== null) {
            $profile = CandidateProfile::query()->find($candidateProfileId);

            if ($profile !== null && $profile->cv_file_path !== null) {
                $analysis = $this->cvJobFitAnalysisService->analyze($profile, $job);
                $job->setRelation('analyses', collect([$analysis]));
            } else {
                $job->setRelation('analyses', collect());
            }
        }

        return $job;
    }

    /**
     * @return LengthAwarePaginator<int, Job>
     */
    public function listForCompany(User $actor, int $page = 1, int $perPage = 15)
    {
        $company = $this->resolveCompany($actor);

        return Job::query()
            ->with(['company', 'sourceProvider', 'skills'])
            ->where('company_id', $company->id)
            ->where('source', JobOrigin::Internal)
            ->orderByDesc('created_at')
            ->paginate(perPage: $perPage, page: $page);
    }

    public function getForCompany(User $actor, Job $job): Job
    {
        $company = $this->resolveCompany($actor);

        if ($job->company_id !== $company->id || $job->source !== JobOrigin::Internal) {
            abort(404, 'Job not found.');
        }

        return $job->load(['company', 'sourceProvider', 'skills']);
    }

    private function resolveCompany(User $actor): Company
    {
        $company = $actor->company;

        if ($company === null) {
            abort(404, 'Company profile not found.');
        }

        return $company;
    }

    private function assertInternalMutable(Job $job): void
    {
        if ($job->source !== JobOrigin::Internal) {
            throw ValidationException::withMessages([
                'job' => ['Only internal jobs can be modified.'],
            ]);
        }

        if (! in_array($job->status, [JobStatus::Draft, JobStatus::PendingReview], true)) {
            throw ValidationException::withMessages([
                'status' => ['Published or closed jobs cannot be modified.'],
            ]);
        }
    }

    private function generateUniqueSlug(string $title, ?int $ignoreJobId = null): string
    {
        $baseSlug = Str::slug($title);
        $slug = $baseSlug;
        $suffix = 1;

        while (
            Job::query()
                ->when($ignoreJobId !== null, fn ($query) => $query->where('id', '!=', $ignoreJobId))
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = "{$baseSlug}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}

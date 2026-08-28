<?php

declare(strict_types=1);

namespace App\Services\Candidate;

use App\Enums\AiAnalysisStatus;
use App\Enums\AiAnalysisType;
use App\Enums\JobStatus;
use App\Enums\TrustAnalysisStatus;
use App\Enums\TrustLabel;
use App\Http\Resources\Job\JobListResource;
use App\Models\AiAnalysis;
use App\Models\CandidateProfile;
use App\Models\Job;
use App\Models\User;
use App\Services\FitScore\FitScoreInputFingerprint;
use App\Support\ResolvesCandidateProfile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CandidateDashboardService
{
    use ResolvesCandidateProfile;

    private const RECOMMENDATION_LIMIT = 4;

    private const ANALYZED_JOBS_LIMIT = 20;

    /**
     * @return array<string, mixed>
     */
    public function buildForUser(User $user): array
    {
        $profile = $this->resolveCandidateProfile($user);
        $publishedQuery = $this->publishedJobsQuery();

        $totalJobs = (clone $publishedQuery)->count();
        $trustedJobs = (clone $publishedQuery)->where('trust_label', TrustLabel::Verified)->count();
        $suspiciousJobs = (clone $publishedQuery)->whereIn('trust_label', [
            TrustLabel::Suspicious,
            TrustLabel::LowTrust,
        ])->count();

        $trustDistribution = $this->trustDistribution($publishedQuery);
        $applicationCount = $profile->applications()->count();
        $currentAnalyses = $this->currentFitAnalyses($profile);
        $fitSummary = $this->fitSummaryFromAnalyses($currentAnalyses);
        $recommendedJobs = $this->recommendedJobs($profile, $currentAnalyses);
        $analyzedJobs = $this->analyzedJobs($currentAnalyses);
        $profileId = $profile->id;

        return [
            'stats' => [
                'total_jobs' => $totalJobs,
                'trusted_jobs' => $trustedJobs,
                'suspicious_jobs' => $suspiciousJobs,
                'application_count' => $applicationCount,
                'average_fit_score' => $fitSummary['average'],
                'analyzed_job_count' => $fitSummary['count'],
                'has_cv' => $profile->cv_file_path !== null,
                'profile_strength_score' => $profile->profile_strength_score,
            ],
            'trust_distribution' => $trustDistribution,
            'recommended_jobs' => $recommendedJobs
                ->map(fn (Job $job) => (new JobListResource($job, $profileId))->resolve())
                ->values()
                ->all(),
            'analyzed_jobs' => $analyzedJobs
                ->map(fn (Job $job) => (new JobListResource($job, $profileId))->resolve())
                ->values()
                ->all(),
            'career_assistant' => [
                'has_cv' => $profile->cv_file_path !== null,
                'average_fit_score' => $fitSummary['average'],
                'analyzed_job_count' => $fitSummary['count'],
            ],
        ];
    }

    /**
     * @return Builder<Job>
     */
    private function publishedJobsQuery(): Builder
    {
        $query = Job::query();
        $this->applyPublishedScope($query);

        return $query;
    }

    /**
     * @param  Builder<Job>  $publishedQuery
     * @return list<array<string, mixed>>
     */
    private function trustDistribution(Builder $publishedQuery): array
    {
        $labels = [
            TrustLabel::Verified->value => 'Güvenilir',
            TrustLabel::Unrated->value => 'Değerlendirilmedi',
            TrustLabel::Suspicious->value => 'Şüpheli',
            TrustLabel::LowTrust->value => 'Düşük Güven',
        ];

        $counts = (clone $publishedQuery)
            ->selectRaw('trust_label, COUNT(*) as aggregate')
            ->groupBy('trust_label')
            ->pluck('aggregate', 'trust_label');

        $pendingCount = (clone $publishedQuery)
            ->whereIn('trust_analysis_status', [
                TrustAnalysisStatus::Pending,
                TrustAnalysisStatus::Analyzing,
            ])
            ->count();

        $total = max(1, (clone $publishedQuery)->count());
        $distribution = [];

        foreach ($labels as $key => $label) {
            $count = (int) ($counts[$key] ?? 0);
            $distribution[] = [
                'id' => $key,
                'label' => $label,
                'count' => $count,
                'percentage' => round(($count / $total) * 100, 1),
            ];
        }

        $distribution[] = [
            'id' => 'pending_analysis',
            'label' => 'Analiz bekliyor',
            'count' => $pendingCount,
            'percentage' => round(($pendingCount / $total) * 100, 1),
        ];

        return $distribution;
    }

    /**
     * Completed analyses that match the current candidate/job fingerprint and published scope.
     *
     * @return Collection<int, AiAnalysis>
     */
    private function currentFitAnalyses(CandidateProfile $profile): Collection
    {
        $profile = $profile->fresh();

        if ($profile === null || $profile->cv_file_path === null) {
            return collect();
        }

        $profile->loadMissing(['candidateSkills', 'skills', 'experiences']);

        return AiAnalysis::query()
            ->where('candidate_profile_id', $profile->id)
            ->where('type', AiAnalysisType::CvJobFit)
            ->where('status', AiAnalysisStatus::Completed)
            ->where('is_latest', true)
            ->whereHas('job', fn (Builder $query) => $this->applyPublishedScope($query))
            ->with(['job.skills'])
            ->get()
            ->filter(function (AiAnalysis $analysis) use ($profile): bool {
                $job = $analysis->job;

                return $job !== null
                    && FitScoreInputFingerprint::isReusable($analysis, $profile, $job);
            })
            ->values();
    }

    /**
     * @param  Collection<int, AiAnalysis>  $analyses
     * @return array{average: ?int, count: int}
     */
    private function fitSummaryFromAnalyses(Collection $analyses): array
    {
        $count = $analyses->count();

        return [
            'average' => $count > 0 ? (int) round((float) $analyses->avg('score')) : null,
            'count' => $count,
        ];
    }

    /**
     * @param  Builder<Job>  $query
     */
    private function applyPublishedScope(Builder $query): void
    {
        $query
            ->where('status', JobStatus::Published)
            ->where(function (Builder $inner): void {
                $inner->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }

    /**
     * @param  Collection<int, AiAnalysis>  $currentAnalyses
     * @return Collection<int, Job>
     */
    private function analyzedJobs(Collection $currentAnalyses): Collection
    {
        if ($currentAnalyses->isEmpty()) {
            return collect();
        }

        $ordered = $currentAnalyses->sortByDesc('score')->take(self::ANALYZED_JOBS_LIMIT)->values();
        $order = $ordered->pluck('job_id')->flip();

        return $ordered
            ->map(function (AiAnalysis $analysis): Job {
                $job = $analysis->job;
                $job->setRelation('analyses', collect([$analysis]));

                return $job;
            })
            ->sortBy(fn (Job $job): int => $order[$job->id] ?? 999)
            ->values();
    }

    /**
     * @param  Collection<int, AiAnalysis>  $currentAnalyses
     * @return Collection<int, Job>
     */
    private function recommendedJobs(CandidateProfile $profile, Collection $currentAnalyses): Collection
    {
        if ($currentAnalyses->isNotEmpty()) {
            $ordered = $currentAnalyses->sortByDesc('score')->take(self::RECOMMENDATION_LIMIT)->values();

            if ($ordered->count() >= self::RECOMMENDATION_LIMIT) {
                return $ordered->map(function (AiAnalysis $analysis): Job {
                    $job = $analysis->job;
                    $job->setRelation('analyses', collect([$analysis]));

                    return $job;
                })->values();
            }

            $jobs = $ordered->map(function (AiAnalysis $analysis): Job {
                $job = $analysis->job;
                $job->setRelation('analyses', collect([$analysis]));

                return $job;
            })->values();

            if ($jobs->count() >= self::RECOMMENDATION_LIMIT) {
                return $jobs;
            }
        }

        return $this->publishedJobsQuery()
            ->with(['company', 'sourceProvider'])
            ->orderByDesc('trust_score')
            ->orderByDesc('published_at')
            ->limit(self::RECOMMENDATION_LIMIT)
            ->get();
    }
}

<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\DTOs\JobSearchQuery;
use App\Enums\AiAnalysisStatus;
use App\Enums\AiAnalysisType;
use App\Enums\JobStatus;
use App\Enums\TrustAnalysisStatus;
use App\Models\Job;
use App\Repositories\Contracts\JobSearchRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class MySqlFulltextJobSearchRepository implements JobSearchRepositoryInterface
{
    public function search(JobSearchQuery $query): LengthAwarePaginator
    {
        $builder = Job::query()
            ->with(['company', 'sourceProvider'])
            ->where('jobs.status', JobStatus::Published)
            ->where(function (Builder $visibilityQuery): void {
                $visibilityQuery
                    ->whereNull('jobs.expires_at')
                    ->orWhere('jobs.expires_at', '>', now());
            });

        if ($query->candidateProfileId !== null) {
            $builder->with([
                'analyses' => function ($analysisQuery) use ($query): void {
                    $analysisQuery
                        ->where('candidate_profile_id', $query->candidateProfileId)
                        ->where('is_latest', true);
                },
            ]);
        }

        $this->applyKeywordFilter($builder, $query);
        $this->applyLocationFilter($builder, $query);
        $this->applyEnumFilters($builder, $query);
        $this->applySalaryFilters($builder, $query);
        $this->applyTrustScoreFilter($builder, $query);
        $this->applyFitScoreFilter($builder, $query);
        $this->applySorting($builder, $query);

        return $builder->paginate(
            perPage: $query->perPage,
            page: $query->page,
        );
    }

    private function applyKeywordFilter(Builder $builder, JobSearchQuery $query): void
    {
        if ($query->keyword === null) {
            return;
        }

        $keyword = trim($query->keyword);

        if ($keyword === '') {
            return;
        }

        $builder->whereFullText(['title', 'description'], $keyword);
    }

    private function applyLocationFilter(Builder $builder, JobSearchQuery $query): void
    {
        if ($query->location === null) {
            return;
        }

        $location = trim($query->location);

        $builder->where(function (Builder $locationQuery) use ($location): void {
            $locationQuery
                ->where('city', 'like', '%'.$location.'%')
                ->orWhere('country', 'like', '%'.$location.'%');
        });
    }

    private function applyEnumFilters(Builder $builder, JobSearchQuery $query): void
    {
        if ($query->category !== null) {
            $builder->where('category', $query->category);
        }

        if ($query->employmentType !== null) {
            $builder->where('employment_type', $query->employmentType);
        }

        if ($query->workType !== null) {
            $builder->where('work_type', $query->workType);
        }

        if ($query->experienceLevel !== null) {
            $builder->where('experience_level', $query->experienceLevel);
        }
    }

    private function applySalaryFilters(Builder $builder, JobSearchQuery $query): void
    {
        if ($query->minSalary !== null) {
            $builder->where(function (Builder $salaryQuery) use ($query): void {
                $salaryQuery
                    ->where('salary_max', '>=', $query->minSalary)
                    ->orWhere('salary_min', '>=', $query->minSalary);
            });
        }

        if ($query->maxSalary !== null) {
            $builder->where(function (Builder $salaryQuery) use ($query): void {
                $salaryQuery
                    ->where('salary_min', '<=', $query->maxSalary)
                    ->orWhere('salary_max', '<=', $query->maxSalary);
            });
        }
    }

    private function applyTrustScoreFilter(Builder $builder, JobSearchQuery $query): void
    {
        if ($query->minTrustScore === null) {
            return;
        }

        $builder
            ->where('trust_analysis_status', TrustAnalysisStatus::Completed)
            ->where('trust_score', '>=', $query->minTrustScore);
    }

    private function applyFitScoreFilter(Builder $builder, JobSearchQuery $query): void
    {
        if ($query->minFitScore === null || $query->candidateProfileId === null) {
            return;
        }

        $builder->whereHas('analyses', function (Builder $analysisQuery) use ($query): void {
            $analysisQuery
                ->where('type', AiAnalysisType::CvJobFit)
                ->where('candidate_profile_id', $query->candidateProfileId)
                ->where('is_latest', true)
                ->where('status', AiAnalysisStatus::Completed)
                ->where('score', '>=', $query->minFitScore);
        });
    }

    private function applySorting(Builder $builder, JobSearchQuery $query): void
    {
        $sort = $query->sort ?? 'published_at';

        match ($sort) {
            'salary' => $builder->orderByDesc('salary_max')->orderByDesc('published_at'),
            'trust_score' => $builder
                ->orderByRaw('CASE WHEN trust_score IS NULL THEN 1 ELSE 0 END')
                ->orderByDesc('trust_score')
                ->orderByDesc('published_at'),
            'fit_score' => $this->applyFitScoreSorting($builder, $query),
            default => $builder
                ->orderByDesc('last_scraped_at')
                ->orderByDesc('published_at')
                ->orderByDesc('id'),
        };
    }

    private function applyFitScoreSorting(Builder $builder, JobSearchQuery $query): void
    {
        if ($query->candidateProfileId === null) {
            $builder->orderByDesc('published_at')->orderByDesc('id');

            return;
        }

        $builder
            ->select('jobs.*')
            ->leftJoin('ai_analyses as fit_analyses', function ($join) use ($query): void {
                $join->on('jobs.id', '=', 'fit_analyses.job_id')
                    ->where('fit_analyses.type', AiAnalysisType::CvJobFit->value)
                    ->where('fit_analyses.candidate_profile_id', $query->candidateProfileId)
                    ->where('fit_analyses.is_latest', true)
                    ->where('fit_analyses.status', AiAnalysisStatus::Completed->value);
            })
            ->orderByRaw('CASE WHEN fit_analyses.score IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('fit_analyses.score')
            ->orderByDesc('jobs.published_at');
    }
}

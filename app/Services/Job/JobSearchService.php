<?php

declare(strict_types=1);

namespace App\Services\Job;

use App\DTOs\JobSearchQuery;
use App\Models\CandidateProfile;
use App\Repositories\Contracts\JobSearchRepositoryInterface;
use App\Services\AI\CvJobFitAnalysisService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class JobSearchService
{
    public function __construct(
        private readonly JobSearchRepositoryInterface $jobSearchRepository,
        private readonly CvJobFitAnalysisService $cvJobFitAnalysisService,
    ) {}

    public function search(JobSearchQuery $query): LengthAwarePaginator
    {
        $paginator = $this->jobSearchRepository->search($query);

        if ($query->candidateProfileId !== null) {
            $this->resolveFitAnalysesForList($query->candidateProfileId, collect($paginator->items()));
        }

        return $paginator;
    }

    /**
     * @param  Collection<int, \App\Models\Job>  $jobs
     */
    private function resolveFitAnalysesForList(int $candidateProfileId, Collection $jobs): void
    {
        if ($jobs->isEmpty()) {
            return;
        }

        $candidateProfile = CandidateProfile::query()->find($candidateProfileId);

        if ($candidateProfile === null || $candidateProfile->cv_file_path === null) {
            foreach ($jobs as $job) {
                $job->setRelation('analyses', collect());
            }

            return;
        }

        foreach ($jobs as $job) {
            $analysis = $this->cvJobFitAnalysisService->analyze($candidateProfile, $job);
            $job->setRelation('analyses', collect([$analysis]));
        }
    }
}

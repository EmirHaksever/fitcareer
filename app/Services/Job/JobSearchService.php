<?php

declare(strict_types=1);

namespace App\Services\Job;

use App\DTOs\JobSearchQuery;
use App\Models\CandidateProfile;
use App\Repositories\Contracts\JobSearchRepositoryInterface;
use App\Services\AI\CvJobFitAnalysisQueueDispatcher;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class JobSearchService
{
    public function __construct(
        private readonly JobSearchRepositoryInterface $jobSearchRepository,
        private readonly CvJobFitAnalysisQueueDispatcher $cvJobFitAnalysisQueueDispatcher,
    ) {}

    public function search(JobSearchQuery $query): LengthAwarePaginator
    {
        $paginator = $this->jobSearchRepository->search($query);

        if ($query->candidateProfileId !== null) {
            $this->queueMissingFitAnalyses($query->candidateProfileId, collect($paginator->items()));
        }

        return $paginator;
    }

    /**
     * @param  Collection<int, \App\Models\Job>  $jobs
     */
    private function queueMissingFitAnalyses(int $candidateProfileId, Collection $jobs): void
    {
        if ($jobs->isEmpty()) {
            return;
        }

        $candidateProfile = CandidateProfile::query()->find($candidateProfileId);

        if ($candidateProfile === null) {
            return;
        }

        $this->cvJobFitAnalysisQueueDispatcher->dispatchForJobs($candidateProfile, $jobs);
    }
}

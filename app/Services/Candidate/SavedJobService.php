<?php

declare(strict_types=1);

namespace App\Services\Candidate;

use App\Models\CandidateProfile;
use App\Models\Job;
use App\Models\SavedJob;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class SavedJobService
{
    /**
     * @return LengthAwarePaginator<int, SavedJob>
     */
    public function listForUser(User $user, int $page = 1, int $perPage = 15): LengthAwarePaginator
    {
        $profile = $this->resolveProfile($user);

        return SavedJob::query()
            ->with(['job.company', 'job.sourceProvider'])
            ->where('candidate_profile_id', $profile->id)
            ->orderByDesc('saved_at')
            ->paginate(perPage: $perPage, page: $page);
    }

    /**
     * @return list<int>
     */
    public function listJobIdsForUser(User $user): array
    {
        $profile = $this->resolveProfile($user);

        return SavedJob::query()
            ->where('candidate_profile_id', $profile->id)
            ->pluck('job_id')
            ->all();
    }

    public function save(User $user, Job $job): SavedJob
    {
        $profile = $this->resolveProfile($user);

        if ($job->status->value !== 'published') {
            throw ValidationException::withMessages([
                'job_id' => ['Only published jobs can be saved.'],
            ]);
        }

        return SavedJob::query()->firstOrCreate(
            [
                'candidate_profile_id' => $profile->id,
                'job_id' => $job->id,
            ],
            [
                'saved_at' => Carbon::now(),
            ],
        );
    }

    public function remove(User $user, Job $job): void
    {
        $profile = $this->resolveProfile($user);

        $saved = SavedJob::query()
            ->where('candidate_profile_id', $profile->id)
            ->where('job_id', $job->id)
            ->first();

        if ($saved === null) {
            throw new ModelNotFoundException('Saved job not found.');
        }

        $saved->delete();
    }

    private function resolveProfile(User $user): CandidateProfile
    {
        $profile = $user->candidateProfile;

        if ($profile === null) {
            abort(404, 'Candidate profile not found.');
        }

        return $profile;
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Job;

use App\Enums\JobOrigin;
use App\Enums\JobStatus;
use App\Enums\SkillImportance;
use App\Models\Job;
use App\Models\JobSkill;
use App\Models\Skill;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class JobSkillService
{
    /**
     * @return Collection<int, JobSkill>
     */
    public function list(Job $job): Collection
    {
        return $job->jobSkills()
            ->with('skill')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  array{skill_id: int, importance: string}  $payload
     */
    public function attach(Job $job, array $payload): JobSkill
    {
        $this->assertManageable($job);

        if ($job->jobSkills()->where('skill_id', $payload['skill_id'])->exists()) {
            throw ValidationException::withMessages([
                'skill_id' => ['This skill has already been added to the job.'],
            ]);
        }

        $jobSkill = $job->jobSkills()->create([
            'skill_id' => $payload['skill_id'],
            'importance' => SkillImportance::from($payload['importance']),
        ]);

        return $jobSkill->load('skill');
    }

    /**
     * @param  list<array{skill_id: int, importance: string}>  $skills
     * @return Collection<int, JobSkill>
     */
    public function sync(Job $job, array $skills): Collection
    {
        $this->assertManageable($job);

        $skillIds = collect($skills)->pluck('skill_id');

        if ($skillIds->count() !== $skillIds->unique()->count()) {
            throw ValidationException::withMessages([
                'skills' => ['Duplicate skill_id values are not allowed.'],
            ]);
        }

        $existingSkillIds = Skill::query()
            ->whereIn('id', $skillIds)
            ->pluck('id');

        if ($existingSkillIds->count() !== $skillIds->unique()->count()) {
            throw ValidationException::withMessages([
                'skills' => ['One or more skills are invalid.'],
            ]);
        }

        DB::transaction(function () use ($job, $skills): void {
            $job->jobSkills()->delete();

            foreach ($skills as $skillPayload) {
                $job->jobSkills()->create([
                    'skill_id' => $skillPayload['skill_id'],
                    'importance' => SkillImportance::from($skillPayload['importance']),
                ]);
            }
        });

        return $this->list($job);
    }

    public function detach(Job $job, int $skillId): void
    {
        $this->assertManageable($job);

        $jobSkill = $job->jobSkills()->where('skill_id', $skillId)->first();

        if ($jobSkill === null) {
            abort(404, 'Job skill not found.');
        }

        $jobSkill->delete();
    }

    private function assertManageable(Job $job): void
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
}

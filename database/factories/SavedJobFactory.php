<?php

namespace Database\Factories;

use App\Models\CandidateProfile;
use App\Models\Job;
use App\Models\SavedJob;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SavedJob>
 */
class SavedJobFactory extends Factory
{
    protected $model = SavedJob::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'candidate_profile_id' => CandidateProfile::factory(),
            'job_id' => Job::factory()->published(),
            'saved_at' => now(),
        ];
    }
}

<?php

namespace Database\Factories;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\CandidateProfile;
use App\Models\Job;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Application>
 */
class ApplicationFactory extends Factory
{
    protected $model = Application::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $now = now();

        return [
            'candidate_profile_id' => CandidateProfile::factory(),
            'job_id' => Job::factory()->published(),
            'status' => ApplicationStatus::Submitted,
            'applied_at' => $now,
            'status_updated_at' => $now,
        ];
    }
}

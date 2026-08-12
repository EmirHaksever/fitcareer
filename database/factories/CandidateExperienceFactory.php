<?php

namespace Database\Factories;

use App\Enums\EmploymentType;
use App\Models\CandidateExperience;
use App\Models\CandidateProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CandidateExperience>
 */
class CandidateExperienceFactory extends Factory
{
    protected $model = CandidateExperience::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'candidate_profile_id' => CandidateProfile::factory(),
            'company_name' => fake()->company(),
            'position_title' => fake()->jobTitle(),
            'employment_type' => EmploymentType::FullTime,
            'location' => fake()->city(),
            'is_current' => false,
            'start_date' => now()->subYears(2)->toDateString(),
            'end_date' => now()->subYear()->toDateString(),
            'description' => fake()->sentence(),
        ];
    }
}

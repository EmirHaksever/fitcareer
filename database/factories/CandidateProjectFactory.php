<?php

namespace Database\Factories;

use App\Models\CandidateProfile;
use App\Models\CandidateProject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CandidateProject>
 */
class CandidateProjectFactory extends Factory
{
    protected $model = CandidateProject::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'candidate_profile_id' => CandidateProfile::factory(),
            'title' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'technologies' => ['PHP', 'Laravel'],
        ];
    }
}

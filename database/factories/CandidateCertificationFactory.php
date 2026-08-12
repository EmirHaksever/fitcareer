<?php

namespace Database\Factories;

use App\Models\CandidateCertification;
use App\Models\CandidateProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CandidateCertification>
 */
class CandidateCertificationFactory extends Factory
{
    protected $model = CandidateCertification::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'candidate_profile_id' => CandidateProfile::factory(),
            'name' => 'AWS Certified Developer',
            'issuing_organization' => 'Amazon Web Services',
            'issue_date' => now()->subYear()->toDateString(),
        ];
    }
}

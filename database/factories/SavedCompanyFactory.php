<?php

namespace Database\Factories;

use App\Models\CandidateProfile;
use App\Models\Company;
use App\Models\SavedCompany;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SavedCompany>
 */
class SavedCompanyFactory extends Factory
{
    protected $model = SavedCompany::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'candidate_profile_id' => CandidateProfile::factory(),
            'company_id' => Company::factory(),
            'saved_at' => now(),
        ];
    }
}

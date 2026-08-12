<?php

namespace Database\Factories;

use App\Enums\CompanyVerificationStatus;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    protected $model = Company::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->company();

        return [
            'user_id' => User::factory()->company(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 9999),
            'website' => fake()->url(),
            'industry' => 'Technology',
            'description' => fake()->paragraph(),
            'city' => fake()->city(),
            'country' => fake()->country(),
            'is_verified' => false,
            'verification_status' => CompanyVerificationStatus::Unverified,
        ];
    }
}

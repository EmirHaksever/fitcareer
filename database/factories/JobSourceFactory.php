<?php

namespace Database\Factories;

use App\Enums\JobSourceType;
use App\Models\JobSource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobSource>
 */
class JobSourceFactory extends Factory
{
    protected $model = JobSource::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company().' Jobs',
            'base_url' => fake()->url(),
            'type' => JobSourceType::Scraper,
            'is_active' => true,
            'config' => ['schedule' => 'daily'],
        ];
    }
}

<?php

namespace Database\Factories;

use App\Enums\EmploymentType;
use App\Enums\ExperienceLevel;
use App\Enums\JobOrigin;
use App\Enums\JobStatus;
use App\Enums\TrustAnalysisStatus;
use App\Enums\TrustLabel;
use App\Enums\WorkType;
use App\Models\Company;
use App\Models\Job;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Job>
 */
class JobFactory extends Factory
{
    protected $model = Job::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->jobTitle();

        return [
            'company_id' => Company::factory(),
            'posted_by' => User::factory()->company(),
            'source' => JobOrigin::Internal,
            'title' => $title,
            'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(1, 9999),
            'description' => fake()->paragraphs(3, true),
            'requirements' => fake()->paragraph(),
            'responsibilities' => fake()->paragraph(),
            'category' => 'engineering',
            'employment_type' => EmploymentType::FullTime,
            'work_type' => WorkType::Remote,
            'experience_level' => ExperienceLevel::Mid,
            'city' => 'Istanbul',
            'country' => 'Turkey',
            'salary_min' => 50000,
            'salary_max' => 80000,
            'salary_currency' => 'TRY',
            'is_salary_visible' => true,
            'status' => JobStatus::Draft,
            'trust_label' => TrustLabel::Unrated,
            'trust_analysis_status' => TrustAnalysisStatus::Pending,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => JobStatus::Published,
            'published_at' => now(),
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => JobStatus::Draft,
            'published_at' => null,
        ]);
    }

    public function scraped(): static
    {
        return $this->state(fn (array $attributes): array => [
            'source' => JobOrigin::Scraped,
            'source_company_name' => fake()->company(),
            'external_url' => fake()->url(),
            'external_id' => (string) fake()->unique()->numberBetween(1000, 999999),
        ]);
    }

    public function withTrustScore(int $score = 85, TrustLabel $label = TrustLabel::Verified): static
    {
        return $this->state(fn (array $attributes): array => [
            'trust_score' => $score,
            'trust_label' => $label,
            'trust_analysis_status' => TrustAnalysisStatus::Completed,
        ]);
    }
}

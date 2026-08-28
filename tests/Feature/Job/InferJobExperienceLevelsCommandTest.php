<?php

declare(strict_types=1);

namespace Tests\Feature\Job;

use App\Enums\ExperienceLevel;
use App\Models\Job;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InferJobExperienceLevelsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_does_not_write_inferred_levels(): void
    {
        $job = Job::factory()->published()->create([
            'title' => 'Junior Backend Engineer',
            'experience_level' => null,
        ]);

        $this->artisan('jobs:infer-experience-levels', [
            '--dry-run' => true,
            '--limit' => 50,
        ])->assertSuccessful();

        $this->assertNull($job->fresh()->experience_level);
    }

    public function test_command_fills_only_missing_high_confidence_levels(): void
    {
        $junior = Job::factory()->published()->create([
            'title' => 'Junior Backend Engineer',
            'experience_level' => null,
        ]);
        $generic = Job::factory()->published()->create([
            'title' => 'Software Engineer',
            'experience_level' => null,
        ]);
        $existing = Job::factory()->published()->create([
            'title' => 'Senior Software Engineer',
            'experience_level' => ExperienceLevel::Mid,
        ]);

        $this->artisan('jobs:infer-experience-levels', [
            '--limit' => 50,
        ])->assertSuccessful();

        $this->assertSame(ExperienceLevel::Entry, $junior->fresh()->experience_level);
        $this->assertNull($generic->fresh()->experience_level);
        $this->assertSame(ExperienceLevel::Mid, $existing->fresh()->experience_level);
    }

    public function test_command_is_idempotent_for_already_inferred_rows(): void
    {
        $job = Job::factory()->published()->create([
            'title' => 'Senior Software Engineer',
            'experience_level' => null,
        ]);

        $this->artisan('jobs:infer-experience-levels', ['--limit' => 50])->assertSuccessful();
        $this->artisan('jobs:infer-experience-levels', ['--limit' => 50])->assertSuccessful();

        $this->assertSame(ExperienceLevel::Senior, $job->fresh()->experience_level);
    }
}

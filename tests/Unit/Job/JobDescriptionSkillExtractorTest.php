<?php

declare(strict_types=1);

namespace Tests\Unit\Job;

use App\Models\Job;
use App\Models\Skill;
use App\Services\Job\JobDescriptionSkillExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class JobDescriptionSkillExtractorTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_extracts_catalog_skills_from_description_text(): void
    {
        Skill::factory()->create(['name' => 'Java', 'slug' => 'java']);
        Skill::factory()->create(['name' => 'React', 'slug' => 'react']);
        Skill::factory()->create(['name' => 'Spring Boot', 'slug' => 'spring-boot']);

        $extractor = app(JobDescriptionSkillExtractor::class);

        $skills = $extractor->extract(
            'We need Java 17, Spring Boot and React experience for this role.',
        );

        $this->assertTrue($skills->pluck('name')->contains('Java'));
        $this->assertTrue($skills->pluck('name')->contains('React'));
        $this->assertTrue($skills->pluck('name')->contains('Spring Boot'));
    }
}

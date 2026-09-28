<?php

namespace Tests\Unit\Candidate;

use App\Models\CandidateProfile;
use App\Models\Skill;
use App\Services\Candidate\ProfileStrengthCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProfileStrengthCalculatorTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_calculates_deterministic_score_from_config_weights(): void
    {
        $profile = CandidateProfile::factory()->create([
            'headline' => 'Backend Developer',
            'summary' => 'Experienced engineer',
            'city' => 'Istanbul',
            'country' => 'Turkey',
            'desired_position' => 'Senior Backend Developer',
            'work_preference' => 'remote',
            'years_of_experience' => 5,
            'linkedin_url' => 'https://linkedin.com/in/test',
            'cv_file_path' => 'candidate/cvs/test.pdf',
        ]);

        $profile->experiences()->create([
            'company_name' => 'Acme',
            'position_title' => 'Developer',
            'start_date' => '2020-01-01',
            'is_current' => true,
        ]);

        $profile->educations()->create([
            'school_name' => 'University',
            'start_date' => '2015-01-01',
            'end_date' => '2019-01-01',
        ]);

        $skill = Skill::factory()->create();
        $profile->candidateSkills()->create([
            'skill_id' => $skill->id,
            'proficiency_level' => 'intermediate',
        ]);

        $profile->loadCount(['experiences', 'educations', 'skills']);

        $score = app(ProfileStrengthCalculator::class)->calculate($profile);

        $this->assertSame(100, $score);
    }
}

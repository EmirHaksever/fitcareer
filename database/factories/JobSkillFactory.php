<?php

namespace Database\Factories;

use App\Enums\SkillImportance;
use App\Models\Job;
use App\Models\JobSkill;
use App\Models\Skill;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobSkill>
 */
class JobSkillFactory extends Factory
{
    protected $model = JobSkill::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'job_id' => Job::factory(),
            'skill_id' => Skill::factory(),
            'importance' => SkillImportance::Required,
        ];
    }
}

<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Skill;
use Database\Seeders\Support\DemoDataCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Creates the master list of skills referenced by both job postings and
 * candidate profiles. Uses firstOrCreate so the seeder can be re-run safely.
 */
class SkillSeeder extends Seeder
{
    public function run(): void
    {
        foreach (DemoDataCatalog::allSkills() as $name) {
            Skill::query()->firstOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'category' => 'general',
                ],
            );
        }
    }
}

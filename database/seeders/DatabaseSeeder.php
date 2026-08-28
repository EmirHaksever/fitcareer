<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Demo data seeder for FitCareer.
 *
 * Order matters:
 *   1. Skills must exist before jobs/candidates reference them.
 *   2. Companies + jobs must exist before candidates apply to them.
 *   3. Candidates must exist before applications are created.
 *   4. Applications are created last, driven through the real
 *      ApplicationService state machine (see ApplicationSeeder).
 *
 * Run with: php artisan migrate:fresh --seed
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            SkillSeeder::class,
            CompanySeeder::class,
            CandidateSeeder::class,
            ApplicationSeeder::class,
        ]);
    }
}

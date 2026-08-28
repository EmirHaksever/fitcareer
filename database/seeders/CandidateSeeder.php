<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ProficiencyLevel;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Enums\WorkPreference;
use App\Models\CandidateProfile;
use App\Models\Skill;
use App\Models\User;
use Database\Seeders\Support\DemoDataCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Creates demo candidate accounts with fully filled out CV profiles
 * (skills, experiences, education, certifications, projects) so the Fit
 * Score signals have real data to work with.
 */
class CandidateSeeder extends Seeder
{
    /** @var list<string> */
    private const UNIVERSITIES = [
        'İstanbul Teknik Üniversitesi',
        'Orta Doğu Teknik Üniversitesi',
        'Boğaziçi Üniversitesi',
        'Ege Üniversitesi',
        'Bilkent Üniversitesi',
        'Marmara Üniversitesi',
    ];

    public function run(): void
    {
        $tracks = array_keys(DemoDataCatalog::tracks());

        // The one "well known" demo candidate account requested in the task.
        $this->createCandidate(
            email: 'demo.aday@fitcareer.test',
            name: 'Ahmet Yılmaz',
            trackKey: 'backend',
            yearsOfExperience: 4,
            city: 'İstanbul',
        );

        $names = [
            'Elif Kaya', 'Mehmet Demir', 'Zeynep Şahin', 'Burak Çelik', 'Ayşe Yıldız',
            'Emre Aydın', 'Deniz Arslan', 'Selin Koç', 'Can Öztürk',
        ];

        foreach ($names as $index => $name) {
            $trackKey = $tracks[$index % count($tracks)];
            $years = random_int(0, 12);
            $city = DemoDataCatalog::turkishCities()[$index % count(DemoDataCatalog::turkishCities())];

            $this->createCandidate(
                email: Str::slug($name).'@fitcareer.test',
                name: $name,
                trackKey: $trackKey,
                yearsOfExperience: $years,
                city: $city,
            );
        }
    }

    private function createCandidate(
        string $email,
        string $name,
        string $trackKey,
        int $yearsOfExperience,
        string $city,
    ): void {
        $track = DemoDataCatalog::tracks()[$trackKey];

        $user = User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'role' => UserRole::Candidate,
                'status' => UserStatus::Active,
                'locale' => 'tr',
            ],
        );

        [$salaryMin, $salaryMax] = $this->desiredSalaryFor($yearsOfExperience);
        $workPreferences = [WorkPreference::Remote, WorkPreference::Hybrid, WorkPreference::Onsite, WorkPreference::Any];

        $profile = CandidateProfile::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'headline' => $track['headlines'][array_rand($track['headlines'])],
                'summary' => sprintf(
                    '%d yıllık deneyime sahip %s. %s alanlarında pratik proje deneyimi bulunmaktadır.',
                    $yearsOfExperience,
                    mb_strtolower($track['headlines'][0]),
                    implode(', ', array_slice($track['skills'], 0, 3)),
                ),
                'city' => $city,
                'country' => 'Türkiye',
                'open_to_work' => true,
                'desired_position' => $track['job_titles'][0],
                'desired_salary_min' => $salaryMin,
                'desired_salary_max' => $salaryMax,
                'work_preference' => $workPreferences[array_rand($workPreferences)],
                'years_of_experience' => $yearsOfExperience,
                'linkedin_url' => 'https://linkedin.com/in/'.Str::slug($name),
                'github_url' => 'https://github.com/'.Str::slug($name),
                'portfolio_url' => null,
                'profile_strength_score' => min(100, 45 + $yearsOfExperience * 3 + random_int(0, 15)),
            ],
        );

        $this->attachSkills($profile, $track['skills'], $yearsOfExperience);
        $this->createExperiences($profile, $track, $yearsOfExperience);
        $this->createEducation($profile, $track);
        $this->createCertifications($profile, $track, $yearsOfExperience);
        $this->createProject($profile, $track);
    }

    /**
     * @param  list<string>  $trackSkills
     */
    private function attachSkills(CandidateProfile $profile, array $trackSkills, int $years): void
    {
        $proficiency = match (true) {
            $years >= 9 => ProficiencyLevel::Expert,
            $years >= 5 => ProficiencyLevel::Advanced,
            $years >= 2 => ProficiencyLevel::Intermediate,
            default => ProficiencyLevel::Beginner,
        };

        $ownSkills = collect($trackSkills)->shuffle()->take(random_int(4, count($trackSkills)));

        // Sprinkle in a couple of cross-track skills for realism.
        $crossSkills = collect(DemoDataCatalog::allSkills())
            ->diff($trackSkills)
            ->shuffle()
            ->take(2);

        $ownSkills->concat($crossSkills)->unique()->each(function (string $name) use ($profile, $proficiency, $years): void {
            $skill = Skill::query()->where('slug', Str::slug($name))->first();

            if ($skill === null || $profile->candidateSkills()->where('skill_id', $skill->id)->exists()) {
                return;
            }

            $profile->candidateSkills()->create([
                'skill_id' => $skill->id,
                'proficiency_level' => $proficiency,
                'years_of_experience' => min($years, random_int(1, max(1, $years))),
            ]);
        });
    }

    /**
     * @param  array{job_titles: list<string>}  $track
     */
    private function createExperiences(CandidateProfile $profile, array $track, int $years): void
    {
        if ($years <= 0) {
            return;
        }

        $count = min(3, (int) floor($years / 3) + 1);
        $cursor = 0;

        for ($i = 0; $i < $count; $i++) {
            $duration = (int) max(1, floor($years / $count));
            $startsAgo = $years - $cursor;
            $endsAgo = max(0, $startsAgo - $duration);
            $isCurrent = $i === $count - 1;

            $profile->experiences()->create([
                'company_name' => fake('tr_TR')->company(),
                'position_title' => $track['job_titles'][array_rand($track['job_titles'])],
                'employment_type' => 'full_time',
                'location' => $profile->city,
                'is_current' => $isCurrent,
                'start_date' => now()->subYears($startsAgo)->toDateString(),
                'end_date' => $isCurrent ? null : now()->subYears($endsAgo)->toDateString(),
                'description' => 'Takım içinde ürün geliştirme süreçlerine aktif olarak katkı sağladı.',
            ]);

            $cursor += $duration;
        }
    }

    /**
     * @param  array{field_of_study: string}  $track
     */
    private function createEducation(CandidateProfile $profile, array $track): void
    {
        $profile->educations()->create([
            'school_name' => self::UNIVERSITIES[array_rand(self::UNIVERSITIES)],
            'degree' => 'Lisans',
            'field_of_study' => $track['field_of_study'],
            'start_date' => now()->subYears(8)->toDateString(),
            'end_date' => now()->subYears(4)->toDateString(),
            'is_current' => false,
            'grade' => null,
        ]);
    }

    /**
     * @param  array{certification: array{name: string, org: string}}  $track
     */
    private function createCertifications(CandidateProfile $profile, array $track, int $years): void
    {
        if ($years < 1) {
            return;
        }

        $profile->certifications()->create([
            'name' => $track['certification']['name'],
            'issuing_organization' => $track['certification']['org'],
            'issue_date' => now()->subYears(random_int(1, max(1, min($years, 3))))->toDateString(),
        ]);
    }

    /**
     * @param  array{project_title: string, skills: list<string>}  $track
     */
    private function createProject(CandidateProfile $profile, array $track): void
    {
        $profile->projects()->create([
            'title' => $track['project_title'],
            'description' => sprintf('%s kapsamında uçtan uca sorumluluk üstlenildi.', $track['project_title']),
            'project_url' => null,
            'repository_url' => 'https://github.com/'.Str::slug($profile->user->name ?? 'demo').'/'.Str::slug($track['project_title']),
            'technologies' => array_slice($track['skills'], 0, 4),
        ]);
    }

    /**
     * @return array{0: float, 1: float}
     */
    private function desiredSalaryFor(int $years): array
    {
        return match (true) {
            $years <= 0 => [15000, 22000],
            $years <= 2 => [25000, 38000],
            $years <= 5 => [45000, 68000],
            $years <= 9 => [70000, 105000],
            default => [100000, 150000],
        };
    }
}

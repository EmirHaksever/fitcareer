<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\CompanyVerificationStatus;
use App\Enums\EmploymentType;
use App\Enums\ExperienceLevel;
use App\Enums\JobOrigin;
use App\Enums\JobStatus;
use App\Enums\SkillImportance;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Enums\WorkType;
use App\Models\Company;
use App\Models\Job;
use App\Models\Skill;
use App\Models\User;
use App\Services\AI\JobTrustAnalysisService;
use Database\Seeders\Support\DemoDataCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Creates demo company accounts and their job postings.
 *
 * Every job's trust score is computed with the *real* TrustScoreCalculator
 * (via JobTrustAnalysisService), the same service the application uses for
 * real job postings, so the numbers shown in the demo are not made up.
 */
class CompanySeeder extends Seeder
{
    /** @var list<ExperienceLevel> */
    private const EXPERIENCE_LEVELS = [
        ExperienceLevel::Intern,
        ExperienceLevel::Entry,
        ExperienceLevel::Mid,
        ExperienceLevel::Senior,
        ExperienceLevel::Lead,
    ];

    /** @var list<WorkType> */
    private const WORK_TYPES = [
        WorkType::Remote,
        WorkType::Remote,
        WorkType::Hybrid,
        WorkType::Hybrid,
        WorkType::Onsite,
    ];

    public function __construct(
        private readonly JobTrustAnalysisService $jobTrustAnalysisService,
    ) {}

    public function run(): void
    {
        // The one "well known" demo company account requested in the task.
        $this->createCompany(
            email: 'demo.sirket@fitcareer.test',
            name: 'Demo Şirket A.Ş.',
            industry: 'Yazılım / Bilişim',
            city: 'İstanbul',
            size: '51-200',
            foundedYear: 2016,
            tracks: ['backend', 'frontend'],
            isVerified: true,
            verificationStatus: CompanyVerificationStatus::Verified,
            jobCount: 4,
        );

        foreach (DemoDataCatalog::companies() as $company) {
            $slug = Str::slug($company['name']);

            $this->createCompany(
                email: $slug.'@fitcareer.test',
                name: $company['name'],
                industry: $company['industry'],
                city: $company['city'],
                size: $company['size'],
                foundedYear: $company['founded_year'],
                tracks: $company['tracks'],
                isVerified: $company['is_verified'],
                verificationStatus: CompanyVerificationStatus::from($company['verification_status']),
                jobCount: random_int(2, 4),
            );
        }
    }

    /**
     * @param  list<string>  $tracks
     */
    private function createCompany(
        string $email,
        string $name,
        string $industry,
        string $city,
        string $size,
        int $foundedYear,
        array $tracks,
        bool $isVerified,
        CompanyVerificationStatus $verificationStatus,
        int $jobCount,
    ): void {
        $user = User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'role' => UserRole::Company,
                'status' => UserStatus::Active,
                'locale' => 'tr',
            ],
        );

        $company = Company::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'name' => $name,
                'slug' => Str::slug($name).'-'.$user->id,
                'website' => 'https://www.'.Str::slug($name).'.com',
                'industry' => $industry,
                'company_size' => $size,
                'founded_year' => $foundedYear,
                'description' => sprintf(
                    '%s, %s sektöründe faaliyet gösteren, çalışan deneyimine ve teknolojiye önem veren bir şirkettir.',
                    $name,
                    $industry,
                ),
                'city' => $city,
                'country' => 'Türkiye',
                'is_verified' => $isVerified,
                'verification_status' => $verificationStatus,
                'contact_email' => 'ik@'.Str::slug($name).'.com',
                'contact_phone' => '+90'.random_int(500, 559).random_int(1000000, 9999999),
            ],
        );

        $tracksCatalog = DemoDataCatalog::tracks();

        for ($i = 0; $i < $jobCount; $i++) {
            $trackKey = $tracks[array_rand($tracks)];
            $track = $tracksCatalog[$trackKey];

            $this->createJob($company, $user, $track, $trackKey, $city);
        }
    }

    /**
     * @param  array{skills: list<string>, job_titles: list<string>, category: string}  $track
     */
    private function createJob(Company $company, User $postedBy, array $track, string $trackKey, string $companyCity): void
    {
        $title = $track['job_titles'][array_rand($track['job_titles'])];
        $experienceLevel = self::EXPERIENCE_LEVELS[array_rand(self::EXPERIENCE_LEVELS)];
        $workType = self::WORK_TYPES[array_rand(self::WORK_TYPES)];
        $employmentType = $experienceLevel === ExperienceLevel::Intern
            ? EmploymentType::Internship
            : (random_int(1, 10) === 1 ? EmploymentType::Contract : EmploymentType::FullTime);

        [$salaryMin, $salaryMax] = $this->salaryRangeFor($experienceLevel);
        $isSalaryVisible = random_int(1, 10) > 2; // most jobs show salary, a few hide it

        $publishedAt = now()->subDays(random_int(1, 45));

        $slugBase = Str::slug($title).'-'.Str::slug($company->name);

        $job = Job::query()->create([
            'company_id' => $company->id,
            'posted_by' => $postedBy->id,
            'source' => JobOrigin::Internal,
            'title' => $title,
            'slug' => $slugBase.'-'.random_int(1000, 999999),
            'description' => $this->descriptionFor($title, $company->name, $track),
            'requirements' => $this->requirementsFor($track),
            'responsibilities' => $this->responsibilitiesFor($track),
            'category' => $track['category'],
            'employment_type' => $employmentType,
            'work_type' => $workType,
            'experience_level' => $experienceLevel,
            'city' => $workType === WorkType::Remote ? $companyCity : $companyCity,
            'country' => 'Türkiye',
            'salary_min' => $salaryMin,
            'salary_max' => $salaryMax,
            'salary_currency' => 'TRY',
            'is_salary_visible' => $isSalaryVisible,
            'contact_email' => $company->contact_email,
            'contact_phone' => $company->contact_phone,
            'status' => JobStatus::Published,
            'published_at' => $publishedAt,
        ]);

        $this->attachSkills($job, $track['skills']);

        // Compute the trust score with the real scoring service, exactly like
        // production jobs get analyzed after being published.
        $this->jobTrustAnalysisService->analyze($job);
    }

    /**
     * @param  list<string>  $skillNames
     */
    private function attachSkills(Job $job, array $skillNames): void
    {
        $skillCount = random_int(min(4, count($skillNames)), count($skillNames));
        $selected = collect($skillNames)->shuffle()->take($skillCount)->values();
        $requiredCount = max(2, (int) ceil($skillCount / 2));

        $selected->each(function (string $name, int $index) use ($job, $requiredCount): void {
            $skill = Skill::query()->where('slug', Str::slug($name))->first();

            if ($skill === null) {
                return;
            }

            $job->jobSkills()->create([
                'skill_id' => $skill->id,
                'importance' => $index < $requiredCount ? SkillImportance::Required : SkillImportance::Preferred,
            ]);
        });
    }

    /**
     * @return array{0: float, 1: float}
     */
    private function salaryRangeFor(ExperienceLevel $level): array
    {
        return match ($level) {
            ExperienceLevel::Intern => [15000, 20000],
            ExperienceLevel::Entry => [25000, 35000],
            ExperienceLevel::Mid => [45000, 65000],
            ExperienceLevel::Senior => [70000, 100000],
            ExperienceLevel::Lead => [100000, 140000],
            ExperienceLevel::Executive => [150000, 220000],
        };
    }

    /**
     * @param  array{skills: list<string>}  $track
     */
    private function descriptionFor(string $title, string $companyName, array $track): string
    {
        $skills = implode(', ', array_slice($track['skills'], 0, 4));

        return sprintf(
            "%s, %s bünyesinde ekibimize katılacak bir %s arıyoruz. Bu pozisyonda %s gibi teknolojilerle çalışacak, ".
            "ürün ekibiyle birlikte gerçek kullanıcı problemlerine çözüm üretecek, kod kalitesine ve takım çalışmasına önem veren biri arıyoruz. ".
            "Şirketimiz esnek çalışma saatleri, özel sağlık sigortası ve sürekli öğrenme kültürü sunmaktadır.",
            $title,
            $companyName,
            $title,
            $skills,
        );
    }

    /**
     * @param  array{skills: list<string>}  $track
     */
    private function requirementsFor(array $track): string
    {
        $skills = implode(', ', $track['skills']);

        return sprintf(
            "- %s alanlarından en az birkaçında deneyim\n".
            "- Takım çalışmasına yatkınlık ve iyi iletişim becerileri\n".
            "- Sorun çözme odaklı çalışma tarzı\n".
            '- Sürekli öğrenmeye açık olmak',
            $skills,
        );
    }

    /**
     * @param  array{category: string}  $track
     */
    private function responsibilitiesFor(array $track): string
    {
        return "- Günlük görevleri takım ile birlikte planlamak ve yürütmek\n".
            "- Kod/iş çıktısının kalitesinden ve zamanında teslim edilmesinden sorumlu olmak\n".
            '- Süreç iyileştirme önerileri sunmak';
    }
}

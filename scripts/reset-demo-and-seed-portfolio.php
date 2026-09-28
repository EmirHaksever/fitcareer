<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Enums\ProficiencyLevel;
use App\Enums\ApplicationStatus;
use App\Enums\CompanySize;
use App\Enums\CompanyVerificationStatus;
use App\Enums\EmploymentType;
use App\Enums\ExperienceLevel;
use App\Enums\JobOrigin;
use App\Enums\JobStatus;
use App\Enums\SkillImportance;
use App\Enums\TrustAnalysisStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Enums\WorkPreference;
use App\Enums\WorkType;
use App\Models\CandidateProfile;
use App\Models\Company;
use App\Models\Job;
use App\Models\Skill;
use App\Models\User;
use App\Services\Candidate\ProfileStrengthCalculator;
use App\Services\Application\ApplicationService;
use App\Services\AI\CvJobFitAnalysisService;
use App\Services\AI\JobTrustAnalysisService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

if (in_array('--help', $argv, true)) {
    echo "Dry-run varsayılandır. Gerçek temizleme ve demo oluşturma için: php scripts/reset-demo-and-seed-portfolio.php --apply\n";
    exit(0);
}

$apply = in_array('--apply', $argv, true);
$targetEmail = 'demo.aday@fitcareer.test';
$excludedEmails = ['admin@fitcareer.test'];
$localDisk = Storage::disk((string) config('candidate.cv.storage_disk'));
$persona = require __DIR__.'/demo-persona.php';
$stableCvPath = 'candidate/cvs/demo-candidate.docx';
$legacyStableCvPaths = [
    'candidate/cvs/demo-candidate.docx',
    'candidate/cvs/demo-emir-haksever.pdf',
    'candidate/cvs/demo-emir-haksever.docx',
];

if (app()->environment('production')) {
    throw new RuntimeException('Bu script production ortamında çalıştırılamaz.');
}

/**
 * Store the fictional demo CV (see scripts/demo-persona.php).
 *
 * @param  array<string, mixed>  $persona
 */
function createSanitizedDemoCv($disk, string $stablePath, array $persona): void
{
    $templatePath = base_path('storage/framework/testing/upload-resume.docx');

    if (! is_file($templatePath)) {
        throw new RuntimeException('Demo CV şablonu bulunamadı.');
    }

    if (! $disk->put($stablePath, buildDemoCvDocx($persona['cv_lines'], $templatePath))) {
        throw new RuntimeException('Demo CV kaydedilemedi.');
    }
}

/**
 * @param  array<string, mixed>  $persona
 * @return array<string, mixed>
 */
function sanitizedDemoCvData(array $persona): array
{
    return [
        'text' => implode("\n", $persona['cv_lines']),
        'sections' => [
            'summary' => $persona['profile']['summary'],
        ],
        'source_filename' => $persona['cv_filename'],
        'parsed_at' => now()->toIso8601String(),
        'parser_version' => 'demo-persona-2.0.0',
    ];
}

$demoUsers = User::withTrashed()
    ->where('email', 'like', '%@fitcareer.test')
    ->whereNotIn('email', $excludedEmails)
    ->get();

$demoUserIds = $demoUsers->pluck('id')->all();
$candidateProfileIds = CandidateProfile::withTrashed()
    ->whereIn('user_id', $demoUserIds)
    ->pluck('id')
    ->all();
$companyIds = Company::withTrashed()
    ->whereIn('user_id', $demoUserIds)
    ->pluck('id')
    ->all();
$demoJobIds = Job::withTrashed()
    ->where('source', 'internal')
    ->where(function ($query) use ($companyIds, $demoUserIds): void {
        $query
            ->when($companyIds !== [], fn ($inner) => $inner->whereIn('company_id', $companyIds))
            ->when($demoUserIds !== [], fn ($inner) => $inner->orWhereIn('posted_by', $demoUserIds));
    })
    ->pluck('id')
    ->all();

$applicationSnapshotPaths = DB::table('applications')
    ->whereIn('candidate_profile_id', $candidateProfileIds)
    ->whereNotNull('resume_snapshot_path')
    ->pluck('resume_snapshot_path')
    ->all();
$candidateFilePaths = CandidateProfile::withTrashed()
    ->whereIn('id', $candidateProfileIds)
    ->get(['cv_file_path', 'profile_photo_path'])
    ->flatMap(fn (CandidateProfile $profile): array => array_filter([
        $profile->cv_file_path,
        $profile->profile_photo_path,
    ]))
    ->values()
    ->all();

echo "DEMO TEMİZLEME ÖNİZLEMESİ\n";
echo "- Demo kullanıcıları: {$demoUsers->count()}\n";
echo "- Demo aday profilleri: ".count($candidateProfileIds)."\n";
echo "- Demo şirketleri: ".count($companyIds)."\n";
echo "- Demo internal ilanları: ".count($demoJobIds)."\n";
echo "- Korunacak scraped ilanlar: ".Job::query()->where('source', 'scraped')->count()."\n";
echo "- Kullanılacak aday: kurgusal demo kişi ({$persona['user_name']})\n";

if (! $apply) {
    echo "\nDry-run tamamlandı. Gerçek işlem için --apply kullanın.\n";
    exit(0);
}

$parsedCv = sanitizedDemoCvData($persona);

DB::transaction(function () use (
    $applicationSnapshotPaths,
    $candidateFilePaths,
    $candidateProfileIds,
    $companyIds,
    $demoJobIds,
    $demoUserIds,
): void {
    if ($demoUserIds !== []) {
        DB::table('notifications')
            ->where('notifiable_type', User::class)
            ->whereIn('notifiable_id', $demoUserIds)
            ->delete();
        DB::table('personal_access_tokens')
            ->where('tokenable_type', User::class)
            ->whereIn('tokenable_id', $demoUserIds)
            ->delete();
    }

    if ($demoJobIds !== []) {
        Job::withTrashed()->whereIn('id', $demoJobIds)->forceDelete();
    }

    if ($candidateProfileIds !== []) {
        CandidateProfile::withTrashed()->whereIn('id', $candidateProfileIds)->forceDelete();
    }

    if ($companyIds !== []) {
        Company::withTrashed()->whereIn('id', $companyIds)->forceDelete();
    }

    if ($demoUserIds !== []) {
        User::withTrashed()->whereIn('id', $demoUserIds)->forceDelete();
    }
});

foreach (array_unique(array_merge($applicationSnapshotPaths, $candidateFilePaths, $legacyStableCvPaths)) as $path) {
    if (is_string($path) && $path !== '') {
        $localDisk->delete($path);
    }
}

createSanitizedDemoCv($localDisk, $stableCvPath, $persona);

$user = User::query()->create([
    'name' => $persona['user_name'],
    'email' => $targetEmail,
    'email_verified_at' => now(),
    'password' => Hash::make('password'),
    'role' => UserRole::Candidate,
    'status' => UserStatus::Active,
    'locale' => 'tr',
]);

$profile = CandidateProfile::query()->create(array_merge($persona['profile'], [
    'user_id' => $user->id,
    'cv_file_path' => $stableCvPath,
    'cv_parsed_data' => $parsedCv,
    'work_preference' => WorkPreference::from($persona['profile']['work_preference']),
]));

$skillNames = ['PHP', 'Laravel', 'MySQL', 'REST API', 'JavaScript', 'Git', 'SQL'];
$skills = Skill::query()->whereIn('name', $skillNames)->get()->keyBy('name');

foreach ($skillNames as $skillName) {
    $skill = $skills->get($skillName);

    if ($skill === null) {
        continue;
    }

    $profile->candidateSkills()->create([
        'skill_id' => $skill->id,
        'proficiency_level' => in_array($skillName, ['PHP', 'Laravel', 'MySQL', 'REST API'], true)
            ? ProficiencyLevel::Advanced
            : ProficiencyLevel::Intermediate,
        'years_of_experience' => in_array($skillName, ['PHP', 'Laravel'], true) ? 2 : 1,
    ]);
}

$profile->experiences()->createMany($persona['experiences']);
$profile->educations()->createMany($persona['educations']);
$profile->projects()->createMany($persona['projects']);

$profile->loadCount(['experiences', 'educations', 'skills']);
$profile->profile_strength_score = app(ProfileStrengthCalculator::class)->calculate($profile);
$profile->save();

$admin = User::query()->firstOrCreate(
    ['email' => 'admin@fitcareer.test'],
    [
        'name' => 'FitCareer Admin',
        'email_verified_at' => now(),
        'password' => Hash::make('password'),
        'role' => UserRole::Admin,
        'status' => UserStatus::Active,
        'locale' => 'tr',
    ],
);

$companyUser = User::query()->create([
    'name' => 'FitCareer Demo Şirketi',
    'email' => 'demo.sirket@fitcareer.test',
    'email_verified_at' => now(),
    'password' => Hash::make('password'),
    'role' => UserRole::Company,
    'status' => UserStatus::Active,
    'locale' => 'tr',
]);

$company = Company::query()->create([
    'user_id' => $companyUser->id,
    'name' => 'FitCareer Demo Şirketi',
    'slug' => Str::slug('FitCareer Demo Şirketi'),
    'website' => 'https://emirhaksever.com',
    'industry' => 'İnsan Kaynakları Teknolojileri',
    'company_size' => CompanySize::OneToTen,
    'founded_year' => 2026,
    'description' => 'FitCareer ürün akışlarını ve aday değerlendirme deneyimini göstermek için hazırlanmış demo şirket hesabı.',
    'city' => 'İstanbul',
    'country' => 'Türkiye',
    'is_verified' => true,
    'verification_status' => CompanyVerificationStatus::Verified,
    'trust_score' => 92,
    'contact_email' => 'demo.sirket@fitcareer.test',
]);

$demoJob = Job::query()->create([
    'company_id' => $company->id,
    'posted_by' => $companyUser->id,
    'source' => JobOrigin::Internal,
    'title' => 'Laravel Backend Developer — Demo',
    'slug' => 'laravel-backend-developer-demo',
    'description' => 'FitCareer demo ekibinde Laravel ve PHP tabanlı ürünler geliştir. Aday pipeline, kişisel eşleşme ve ilan güveni akışlarını gerçek ürün verisiyle iyileştir.',
    'requirements' => 'PHP, Laravel, MySQL ve REST API deneyimi. Test yazma ve ekip içi iletişim becerisi.',
    'responsibilities' => 'REST API geliştirmek, veri modellerini iyileştirmek ve ürün kalitesini ölçülebilir hale getirmek.',
    'category' => 'Yazılım Geliştirme',
    'employment_type' => EmploymentType::FullTime,
    'work_type' => WorkType::Remote,
    'experience_level' => ExperienceLevel::Mid,
    'city' => 'İstanbul',
    'country' => 'Türkiye',
    'salary_min' => 60000,
    'salary_max' => 90000,
    'salary_currency' => 'TRY',
    'is_salary_visible' => true,
    'status' => JobStatus::Published,
    'trust_analysis_status' => TrustAnalysisStatus::Pending,
    'published_at' => now(),
    'expires_at' => now()->addMonths(3),
]);

foreach (['PHP', 'Laravel', 'MySQL', 'REST API'] as $skillName) {
    $skill = $skills->get($skillName);
    if ($skill !== null) {
        $demoJob->skills()->attach($skill->id, ['importance' => SkillImportance::Required]);
    }
}

app(JobTrustAnalysisService::class)->analyze($demoJob->fresh());

$demoApplication = app(ApplicationService::class)->submit($user, [
    'job_id' => $demoJob->id,
    'cover_letter' => $persona['cover_letter'],
]);
app(ApplicationService::class)->transitionStatus(
    $demoApplication->id,
    ApplicationStatus::UnderReview,
    $companyUser,
    'Demo aday pipeline görünümünü göstermek için oluşturuldu.',
);

$profile->load(['candidateSkills', 'skills', 'experiences']);
$candidateSkillIds = $profile->skills->pluck('id');
$analysisJobs = Job::query()
    ->where('source', 'scraped')
    ->where('status', 'published')
    ->with('skills')
    ->get()
    ->filter(fn (Job $job): bool => $job->skills->pluck('id')->intersect($candidateSkillIds)->isNotEmpty())
    ->sortByDesc(function (Job $job) use ($candidateSkillIds): int {
        $overlap = $job->skills->pluck('id')->intersect($candidateSkillIds)->count();

        return ($overlap * 100) + (int) ($job->trust_score ?? 0);
    })
    ->take(8)
    ->values();

foreach ($analysisJobs as $job) {
    app(CvJobFitAnalysisService::class)->analyze($profile->fresh(), $job);
}

echo "\nDemo temizlendi ve portfolio adayı oluşturuldu.\n";
echo "Login: {$targetEmail}\n";
echo "Password: password\n";
echo "Profil gücü: {$profile->fresh()->profile_strength_score}\n";
echo "Fit Score analizi oluşturulan ilan: {$analysisJobs->count()}\n";
echo "Company Login: demo.sirket@fitcareer.test\n";
echo "Admin Login: admin@fitcareer.test\n";
echo "Demo aday pipeline başvurusu: #{$demoApplication->id}\n";

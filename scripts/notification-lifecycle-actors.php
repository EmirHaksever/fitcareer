<?php

declare(strict_types=1);

/**
 * Create ephemeral notification QA actors via Eloquent (avoids auth rate limits).
 * Outputs JSON to stdout for Playwright QA scripts.
 */

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Enums\ApplicationStatus;
use App\Enums\UserRole;
use App\Models\Application;
use App\Models\ApplicationStatusHistory;
use App\Models\CandidateProfile;
use App\Models\Company;
use App\Models\Job;
use App\Models\User;
use App\Models\UserSetting;

$suffix = (string) time();

$companyUser = User::factory()->company()->create([
    'email' => "notif-qa-co-{$suffix}@example.test",
]);
$company = Company::factory()->create(['user_id' => $companyUser->id]);
UserSetting::query()->create(['user_id' => $companyUser->id]);

$candidateUser = User::factory()->create([
    'role' => UserRole::Candidate,
    'email' => "notif-qa-ca-{$suffix}@example.test",
]);
$profile = CandidateProfile::factory()->create(['user_id' => $candidateUser->id]);
UserSetting::query()->create(['user_id' => $candidateUser->id]);

$job = Job::factory()->published()->create([
    'company_id' => $company->id,
    'posted_by' => $companyUser->id,
    'title' => 'Uzun Başlıklı Senior Backend Developer ve Platform Mühendisi',
]);

$application = Application::factory()->create([
    'candidate_profile_id' => $profile->id,
    'job_id' => $job->id,
    'status' => ApplicationStatus::Submitted,
]);

ApplicationStatusHistory::query()->create([
    'application_id' => $application->id,
    'from_status' => null,
    'to_status' => ApplicationStatus::Submitted,
]);

echo json_encode([
    'password' => 'password',
    'candidate' => [
        'email' => $candidateUser->email,
        'token' => $candidateUser->createToken('qa')->plainTextToken,
        'id' => $candidateUser->id,
    ],
    'company' => [
        'token' => $companyUser->createToken('qa')->plainTextToken,
    ],
    'application_id' => $application->id,
    'job_id' => $job->id,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;

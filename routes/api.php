<?php

use App\Http\Controllers\Api\V1\Auth\CurrentUserController;
use App\Http\Controllers\Api\V1\Auth\EmailVerificationController;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\PasswordResetController;
use App\Http\Controllers\Api\V1\Auth\PasswordUpdateController;
use App\Http\Controllers\Api\V1\Auth\RegisterController;
use App\Http\Controllers\Api\V1\Candidate\NotificationController;
use App\Http\Controllers\Api\V1\Candidate\DashboardController;
use App\Http\Controllers\Api\V1\Candidate\ApplicationController;
use App\Http\Controllers\Api\V1\Candidate\CertificationController;
use App\Http\Controllers\Api\V1\Candidate\CvController;
use App\Http\Controllers\Api\V1\Candidate\EducationController;
use App\Http\Controllers\Api\V1\Candidate\ExperienceController;
use App\Http\Controllers\Api\V1\Candidate\ProfileController;
use App\Http\Controllers\Api\V1\Candidate\ProjectController;
use App\Http\Controllers\Api\V1\Candidate\SavedJobController;
use App\Http\Controllers\Api\V1\Candidate\SkillController;
use App\Http\Controllers\Api\V1\Candidate\SkillLookupController;
use App\Http\Controllers\Api\V1\Company\ApplicationController as CompanyApplicationController;
use App\Http\Controllers\Api\V1\Company\JobController as CompanyJobController;
use App\Http\Controllers\Api\V1\Company\JobFitScoreSettingsController as CompanyJobFitScoreSettingsController;
use App\Http\Controllers\Api\V1\Company\JobSkillController as CompanyJobSkillController;
use App\Http\Controllers\Api\V1\Company\ProfileController as CompanyProfileController;
use App\Http\Controllers\Api\V1\Company\PublicCompanyController;
use App\Http\Controllers\Api\V1\Company\VerificationController as CompanyVerificationController;
use App\Http\Controllers\Api\V1\Job\JobController;
use App\Http\Controllers\Api\V1\Job\JobSearchController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/health', fn () => response()->json([
        'success' => true,
        'message' => 'FitCareer API is available.',
        'data' => null,
        'errors' => null,
    ]));

    Route::get('/skills', [SkillLookupController::class, 'index']);
    Route::get('/companies/{slug}', [PublicCompanyController::class, 'show']);

    Route::middleware(['optional.sanctum', 'throttle:job-search'])->group(function (): void {
        Route::get('/jobs', [JobSearchController::class, 'index']);
        Route::get('/jobs/{slug}', [JobController::class, 'show']);
    });

    Route::prefix('auth')->group(function (): void {
        Route::post('register', [RegisterController::class, 'store'])
            ->middleware('throttle:auth-register');

        Route::post('login', [LoginController::class, 'store'])
            ->middleware('throttle:auth-login');

        Route::post('forgot-password', [PasswordResetController::class, 'sendResetLink'])
            ->middleware('throttle:auth-password-reset');

        Route::post('reset-password', [PasswordResetController::class, 'reset'])
            ->middleware('throttle:auth-password-reset');

        Route::get('email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
            ->name('auth.email.verify');

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::post('logout', [LogoutController::class, 'destroy']);
            Route::get('me', [CurrentUserController::class, 'show']);
            Route::put('password', [PasswordUpdateController::class, 'update']);
            Route::post('email/verification-notification', [EmailVerificationController::class, 'send']);
        });
    });

    Route::prefix('candidate')
        ->middleware(['auth:sanctum', 'role:candidate'])
        ->group(function (): void {
            Route::get('dashboard', [DashboardController::class, 'show']);

            Route::get('profile', [ProfileController::class, 'show']);
            Route::put('profile', [ProfileController::class, 'update']);
            Route::get('profile/photo', [ProfileController::class, 'showPhoto']);
            Route::post('profile/photo', [ProfileController::class, 'uploadPhoto']);
            Route::delete('profile/photo', [ProfileController::class, 'deletePhoto']);

            Route::get('cv', [CvController::class, 'show']);
            Route::get('cv/download', [CvController::class, 'download']);
            Route::post('cv', [CvController::class, 'store']);
            Route::delete('cv', [CvController::class, 'destroy']);

            Route::get('experiences', [ExperienceController::class, 'index']);
            Route::post('experiences', [ExperienceController::class, 'store']);
            Route::put('experiences/{experience}', [ExperienceController::class, 'update']);
            Route::delete('experiences/{experience}', [ExperienceController::class, 'destroy']);

            Route::get('educations', [EducationController::class, 'index']);
            Route::post('educations', [EducationController::class, 'store']);
            Route::put('educations/{education}', [EducationController::class, 'update']);
            Route::delete('educations/{education}', [EducationController::class, 'destroy']);

            Route::get('certifications', [CertificationController::class, 'index']);
            Route::post('certifications', [CertificationController::class, 'store']);
            Route::put('certifications/{certification}', [CertificationController::class, 'update']);
            Route::delete('certifications/{certification}', [CertificationController::class, 'destroy']);

            Route::get('projects', [ProjectController::class, 'index']);
            Route::post('projects', [ProjectController::class, 'store']);
            Route::put('projects/{project}', [ProjectController::class, 'update']);
            Route::delete('projects/{project}', [ProjectController::class, 'destroy']);

            Route::get('skills', [SkillController::class, 'index']);
            Route::post('skills', [SkillController::class, 'store']);
            Route::put('skills/{candidateSkill}', [SkillController::class, 'update']);
            Route::delete('skills/{candidateSkill}', [SkillController::class, 'destroy']);

            Route::get('applications', [ApplicationController::class, 'index']);
            Route::post('applications', [ApplicationController::class, 'store']);
            Route::get('applications/{application}', [ApplicationController::class, 'show']);

            Route::get('saved-jobs', [SavedJobController::class, 'index']);
            Route::get('saved-jobs/ids', [SavedJobController::class, 'ids']);
            Route::post('saved-jobs/{job}', [SavedJobController::class, 'store']);
            Route::delete('saved-jobs/{job}', [SavedJobController::class, 'destroy']);

            Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount']);
            Route::post('notifications/mark-all-read', [NotificationController::class, 'markAllRead']);
            Route::patch('notifications/{notification}/read', [NotificationController::class, 'markRead']);
            Route::get('notifications', [NotificationController::class, 'index']);
        });

    Route::prefix('company')
        ->middleware(['auth:sanctum', 'role:company'])
        ->group(function (): void {
            Route::get('profile', [CompanyProfileController::class, 'show']);
            Route::put('profile', [CompanyProfileController::class, 'update']);
            Route::post('profile/logo', [CompanyProfileController::class, 'uploadLogo']);
            Route::delete('profile/logo', [CompanyProfileController::class, 'deleteLogo']);
            Route::post('verification/request', [CompanyVerificationController::class, 'request']);

            Route::get('jobs', [CompanyJobController::class, 'index']);
            Route::post('jobs', [CompanyJobController::class, 'store']);
            Route::get('jobs/{job}', [CompanyJobController::class, 'show']);
            Route::put('jobs/{job}', [CompanyJobController::class, 'update']);
            Route::post('jobs/{job}/publish', [CompanyJobController::class, 'publish']);

            Route::get('jobs/{job}/skills', [CompanyJobSkillController::class, 'index']);
            Route::post('jobs/{job}/skills', [CompanyJobSkillController::class, 'store']);
            Route::put('jobs/{job}/skills', [CompanyJobSkillController::class, 'sync']);
            Route::delete('jobs/{job}/skills/{skill}', [CompanyJobSkillController::class, 'destroy']);

            Route::get('jobs/{job}/fit-score-settings', [CompanyJobFitScoreSettingsController::class, 'show']);
            Route::put('jobs/{job}/fit-score-settings', [CompanyJobFitScoreSettingsController::class, 'update']);

            Route::get('applications', [CompanyApplicationController::class, 'index']);
            Route::get('applications/{application}', [CompanyApplicationController::class, 'show']);
            Route::patch('applications/{application}/status', [CompanyApplicationController::class, 'updateStatus']);
        });
});

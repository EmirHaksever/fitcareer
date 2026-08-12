<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\CandidateProfile;
use App\Models\User;

$email = $argv[1] ?? 'dev.candidate.20260811000532@fitcareer.test';
$user = User::where('email', $email)->first();

if (! $user) {
    fwrite(STDERR, "User not found\n");
    exit(1);
}

$profile = CandidateProfile::where('user_id', $user->id)->first();
echo json_encode([
    'profile_id' => $profile?->id,
    'has_cv' => $profile?->cv_file_path !== null,
    'cv_parsed_data' => $profile?->cv_parsed_data,
    'headline' => $profile?->headline,
    'linkedin_url' => $profile?->linkedin_url,
    'github_url' => $profile?->github_url,
    'portfolio_url' => $profile?->portfolio_url,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

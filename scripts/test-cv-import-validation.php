<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Requests\Candidate\UpdateProfileRequest;
use App\Models\CandidateProfile;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

$user = User::where('email', 'dev.candidate.20260811000532@fitcareer.test')->first();
$profile = CandidateProfile::where('user_id', $user->id)->first();
$parsed = $profile->cv_parsed_data;
$sections = $parsed['sections'] ?? [];

$profilePayload = [
    'headline' => $profile->headline,
    'summary' => $sections['summary'] ?? $profile->summary,
    'city' => $profile->city,
    'country' => $profile->country,
    'desired_position' => $profile->desired_position,
    'work_preference' => $profile->work_preference?->value ?? $profile->work_preference,
    'linkedin_url' => $profile->linkedin_url,
    'github_url' => $profile->github_url,
    'portfolio_url' => $profile->portfolio_url,
    'open_to_work' => $profile->open_to_work,
    'years_of_experience' => $profile->years_of_experience,
];

$rules = (new UpdateProfileRequest)->rules();
$validator = Validator::make($profilePayload, $rules);

echo "Profile payload validation:\n";
if ($validator->fails()) {
    echo json_encode($validator->errors()->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n";
} else {
    echo "OK\n";
}

echo "\nSummary length: ".strlen((string) ($profilePayload['summary'] ?? ''))."\n";
echo "Headline length: ".strlen((string) ($profilePayload['headline'] ?? ''))."\n";

foreach (['linkedin_url', 'github_url', 'portfolio_url'] as $field) {
    $value = $profilePayload[$field] ?? null;
    echo "{$field}: {$value}\n";
    if ($value) {
        echo "  filter_var: ".(filter_var($value, FILTER_VALIDATE_URL) ? 'valid' : 'INVALID')."\n";
    }
}

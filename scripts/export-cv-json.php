<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\CandidateProfile;
use App\Models\User;

$user = User::where('email', 'dev.candidate.20260811000532@fitcareer.test')->first();
$profile = CandidateProfile::where('user_id', $user->id)->first();

file_put_contents(
    __DIR__.'/cv-sample.json',
    json_encode($profile->cv_parsed_data, JSON_UNESCAPED_UNICODE),
);

echo "written\n";

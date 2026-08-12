<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\AiAnalysis;
use App\Models\Application;
use App\Models\CandidateProfile;
use App\Models\Job;

echo 'jobs=' . Job::count() . PHP_EOL;
echo 'published_jobs=' . Job::where('status', 'published')->count() . PHP_EOL;
echo 'applications=' . Application::count() . PHP_EOL;
echo 'candidate_profiles=' . CandidateProfile::count() . PHP_EOL;
echo 'ai_analyses=' . AiAnalysis::count() . PHP_EOL;

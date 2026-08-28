<?php

declare(strict_types=1);

use App\Enums\JobSourceType;
use App\Models\JobSource;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$source = JobSource::query()->updateOrCreate(
    [
        'name' => 'Remotive',
    ],
    [
        'base_url' => 'https://remotive.com/api/remote-jobs',
        'type' => JobSourceType::ApiIntegration,
        'is_active' => true,
        'config' => [
            'provider' => 'remotive',
            'limit' => 10,
            'max_listings' => 50,
            'refresh_interval_minutes' => 360,
            'ingest_policy' => 'remote_open',
        ],
    ],
);

echo 'Job source ready: '.$source->name.' (id='.$source->id.")\n";

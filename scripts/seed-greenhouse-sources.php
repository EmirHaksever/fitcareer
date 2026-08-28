<?php

declare(strict_types=1);

use App\Enums\JobSourceType;
use App\Models\JobSource;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

/**
 * Greenhouse controlled pilot — 2 Turkey boards.
 *
 * Public Job Board API (no authentication):
 * GET https://boards-api.greenhouse.io/v1/boards/{token}/jobs?content=true
 */
$activeBoards = [
    [
        'name' => 'Good Job Games',
        'site_slug' => 'goodjobgames',
        'company_display_name' => 'Good Job Games',
    ],
    [
        'name' => 'Medsien',
        'site_slug' => 'medsien',
        'company_display_name' => 'Medsien',
    ],
    [
        'name' => 'Zynga',
        'site_slug' => 'zyngacareers',
        'company_display_name' => 'Zynga',
        'is_active' => false,
        'ingest_policy' => 'turkey_first',
    ],
    [
        'name' => 'OLIVER Agency',
        'site_slug' => 'oliver',
        'company_display_name' => 'OLIVER Agency',
        'is_active' => false,
        'ingest_policy' => 'turkey_first',
    ],
];

$greenhouseConfigDefaults = [
    'provider' => 'greenhouse',
    'page_size' => 100,
    'max_pages' => 1,
    'max_listings' => 200,
    'refresh_interval_minutes' => 360,
    'max_posting_age_days' => 365,
    'ingest_policy' => 'turkey_first',
];

foreach ($activeBoards as $board) {
    $isActive = $board['is_active'] ?? true;
    unset($board['is_active']);

    $configOverrides = [];
    if (isset($board['ingest_policy'])) {
        $configOverrides['ingest_policy'] = $board['ingest_policy'];
        unset($board['ingest_policy']);
    }

    $source = JobSource::query()->updateOrCreate(
        [
            'name' => $board['name'],
        ],
        [
            'base_url' => 'https://boards-api.greenhouse.io/v1/boards/'.$board['site_slug'].'/jobs',
            'type' => JobSourceType::ApiIntegration,
            'is_active' => $isActive,
            'config' => array_merge($greenhouseConfigDefaults, [
                'site_slug' => $board['site_slug'],
                'company_display_name' => $board['company_display_name'],
            ], $configOverrides),
        ],
    );

    echo 'Job source ready: '.$source->name.' (id='.$source->id.', slug='.$board['site_slug'].")\n";
}

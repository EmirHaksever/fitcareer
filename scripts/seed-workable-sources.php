<?php

declare(strict_types=1);

use App\Enums\JobSourceType;
use App\Models\JobSource;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

/**
 * Workable Phase 1 controlled pilot — 6 Turkey boards.
 *
 * Uses the public widget endpoint (NOT authenticated SPI v3 Developer API):
 * GET https://apply.workable.com/api/v1/widget/accounts/{slug}?details=true
 */
$activeBoards = [
    [
        'name' => 'Wingie Enuygun',
        'site_slug' => 'wingieenuygun',
        'company_display_name' => 'Wingie Enuygun',
    ],
    [
        'name' => 'Vertigo Games',
        'site_slug' => 'vertigogames',
        'company_display_name' => 'Vertigo Games',
    ],
    [
        'name' => 'Sanction Scanner',
        'site_slug' => 'sanction-scanner',
        'company_display_name' => 'Sanction Scanner',
    ],
    [
        'name' => 'Lucida AI',
        'site_slug' => 'lucida-ai',
        'company_display_name' => 'Lucida AI',
    ],
    [
        'name' => 'NewMind AI',
        'site_slug' => 'newmindai',
        'company_display_name' => 'NewMind AI',
    ],
    [
        'name' => 'VavaCars',
        'site_slug' => 'vavacars',
        'company_display_name' => 'VavaCars',
    ],
    [
        'name' => 'FERASET',
        'site_slug' => 'feraset',
        'company_display_name' => 'FERASET',
    ],
];

$workableConfigDefaults = [
    'provider' => 'workable',
    'page_size' => 100,
    'max_pages' => 1,
    'max_listings' => 200,
    'refresh_interval_minutes' => 360,
    'max_posting_age_days' => 365,
    'ingest_policy' => 'turkey_first',
];

foreach ($activeBoards as $board) {
    $source = JobSource::query()->updateOrCreate(
        [
            'name' => $board['name'],
        ],
        [
            'base_url' => 'https://apply.workable.com/api/v1/widget/accounts/'.$board['site_slug'],
            'type' => JobSourceType::ApiIntegration,
            'is_active' => true,
            'config' => array_merge($workableConfigDefaults, [
                'site_slug' => $board['site_slug'],
                'company_display_name' => $board['company_display_name'],
            ]),
        ],
    );

    echo 'Job source ready: '.$source->name.' (id='.$source->id.', slug='.$board['site_slug'].")\n";
}

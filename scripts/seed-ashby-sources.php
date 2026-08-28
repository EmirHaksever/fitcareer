<?php

declare(strict_types=1);

use App\Enums\JobSourceType;
use App\Models\JobSource;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

/**
 * Ashby Phase 1 controlled pilot — 5 Turkey boards.
 *
 * Public Job Posting API (no authentication):
 * GET https://api.ashbyhq.com/posting-api/job-board/{slug}
 */
$activeBoards = [
    [
        'name' => 'Codeway',
        'site_slug' => 'codeway',
        'company_display_name' => 'Codeway',
    ],
    [
        'name' => 'Bigger Games',
        'site_slug' => 'biggergames',
        'company_display_name' => 'Bigger Games',
    ],
    [
        'name' => 'Agave Games',
        'site_slug' => 'agavegames',
        'company_display_name' => 'Agave Games',
    ],
    [
        'name' => 'Bold Games',
        'site_slug' => 'boldgames',
        'company_display_name' => 'Bold Games',
    ],
    [
        'name' => 'DoktorTakvimi',
        'site_slug' => 'doktortakvimi',
        'company_display_name' => 'DoktorTakvimi',
    ],
];

$ashbyConfigDefaults = [
    'provider' => 'ashby',
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
            'base_url' => 'https://api.ashbyhq.com/posting-api/job-board/'.$board['site_slug'],
            'type' => JobSourceType::ApiIntegration,
            'is_active' => true,
            'config' => array_merge($ashbyConfigDefaults, [
                'site_slug' => $board['site_slug'],
                'company_display_name' => $board['company_display_name'],
            ]),
        ],
    );

    echo 'Job source ready: '.$source->name.' (id='.$source->id.', slug='.$board['site_slug'].")\n";
}

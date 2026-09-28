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
    // Phase 2 (2026-09-28): Turkey boards found via jobs.workable.com location search,
    // each verified against the widget API (name match + at least one Turkey posting).
    [
        'name' => 'Abakus Center',
        'site_slug' => 'abakus-center',
        'company_display_name' => 'Abakus Center',
    ],
    [
        'name' => 'Ace Games',
        'site_slug' => 'ace-games',
        'company_display_name' => 'Ace Games',
    ],
    [
        'name' => 'ALUMIL',
        'site_slug' => 'alumil',
        'company_display_name' => 'ALUMIL',
    ],
    [
        'name' => 'Azeus Convene',
        'site_slug' => 'azeus-convene',
        'company_display_name' => 'Azeus Convene',
    ],
    [
        'name' => 'Best Service Team',
        'site_slug' => 'bestserviceteam',
        'company_display_name' => 'Best Service Team',
    ],
    [
        'name' => 'Bnberry',
        'site_slug' => 'bnberry',
        'company_display_name' => 'Bnberry',
    ],
    [
        'name' => 'Cross Border Talents',
        'site_slug' => 'crossbordertalents',
        'company_display_name' => 'Cross Border Talents',
    ],
    [
        'name' => 'CXG',
        'site_slug' => 'cxg',
        'company_display_name' => 'CXG',
    ],
    [
        'name' => 'Digital Zone',
        'site_slug' => 'digital-zone',
        'company_display_name' => 'Digital Zone',
    ],
    [
        'name' => 'Exely',
        'site_slug' => 'exely',
        'company_display_name' => 'Exely',
    ],
    [
        'name' => 'Gramian Consulting Group',
        'site_slug' => 'gramian',
        'company_display_name' => 'Gramian Consulting Group',
    ],
    [
        'name' => 'Guess Europe Sagl',
        'site_slug' => 'guess-europe-sagl',
        'company_display_name' => 'Guess Europe Sagl',
    ],
    [
        'name' => 'Hex Trust',
        'site_slug' => 'hextrust',
        'company_display_name' => 'Hex Trust',
    ],
    [
        'name' => 'Hiroba Games',
        'site_slug' => 'hiroba-games',
        'company_display_name' => 'Hiroba Games',
    ],
    [
        'name' => 'Huda Beauty',
        'site_slug' => 'hudabeauty',
        'company_display_name' => 'Huda Beauty',
    ],
    [
        'name' => 'Humanz',
        'site_slug' => 'humanz',
        'company_display_name' => 'Humanz',
    ],
    [
        'name' => 'Hyperlab',
        'site_slug' => 'hyperlab',
        'company_display_name' => 'Hyperlab',
    ],
    [
        'name' => 'Intertek',
        'site_slug' => 'intertek',
        'company_display_name' => 'Intertek',
    ],
    [
        'name' => 'Laba Group',
        'site_slug' => 'laba',
        'company_display_name' => 'Laba Group',
    ],
    [
        'name' => 'Loom Games',
        'site_slug' => 'loomgames',
        'company_display_name' => 'Loom Games',
    ],
    [
        'name' => 'Lucidya',
        'site_slug' => 'lucidya',
        'company_display_name' => 'Lucidya',
    ],
    [
        'name' => 'Nacre Capital',
        'site_slug' => 'nacrecapital',
        'company_display_name' => 'Nacre Capital',
    ],
    [
        'name' => 'Oredata Yazılım Anonim Şirketi',
        'site_slug' => 'oredata',
        'company_display_name' => 'Oredata Yazılım Anonim Şirketi',
    ],
    [
        'name' => 'PEOPLECERT',
        'site_slug' => 'peoplecert',
        'company_display_name' => 'PEOPLECERT',
    ],
    [
        'name' => 'Pulse Games',
        'site_slug' => 'pulsegames',
        'company_display_name' => 'Pulse Games',
    ],
    [
        'name' => 'Rapsodo',
        'site_slug' => 'rapsodo',
        'company_display_name' => 'Rapsodo',
    ],
    [
        'name' => 'RateHawk',
        'site_slug' => 'ratehawk',
        'company_display_name' => 'RateHawk',
    ],
    [
        'name' => 'Remofirst',
        'site_slug' => 'remofirst',
        'company_display_name' => 'Remofirst',
    ],
    [
        'name' => 'SIHAMCO',
        'site_slug' => 'sihamco',
        'company_display_name' => 'SIHAMCO',
    ],
    [
        'name' => 'Symphony Solutions',
        'site_slug' => 'symphony-solutions',
        'company_display_name' => 'Symphony Solutions',
    ],
    [
        'name' => 'Teltonika',
        'site_slug' => 'teltonika',
        'company_display_name' => 'Teltonika',
    ],
    [
        'name' => 'UserWise Services',
        'site_slug' => 'userwise-services',
        'company_display_name' => 'UserWise Services',
    ],
    [
        'name' => 'Vinmar International',
        'site_slug' => 'vinmar-international',
        'company_display_name' => 'Vinmar International',
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

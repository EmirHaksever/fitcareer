<?php

declare(strict_types=1);

use App\Enums\JobSourceType;
use App\Models\JobSource;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

/**
 * Recruitee Phase E.3 — 6 core Turkey employers (Phase E.2 verified slugs).
 *
 * Public Careers Site API (no authentication):
 * GET https://{slug}.recruitee.com/api/offers
 *
 * Deferred (not seeded — quality verification pending):
 * - TechBiz Global (techbizglobal) — staffing, global pollution risk
 * - Kodland (kodland) — remote EdTech roles
 */
$activeBoards = [
    [
        'name' => 'Mikro Yazılım',
        'site_slug' => 'mikroyazilim',
        'company_display_name' => 'Mikro Yazılım',
    ],
    [
        'name' => 'Zirve Yazılım',
        'site_slug' => 'zirvebilgiteknolojilerisanayiticaretanonimsirketi',
        'company_display_name' => 'Zirve Yazılım',
    ],
    [
        'name' => 'Trio Mobil',
        'site_slug' => 'triomobil',
        'company_display_name' => 'Trio Mobil',
    ],
    [
        'name' => 'Krila Consultancy',
        'site_slug' => 'krila',
        'company_display_name' => 'Krila Consultancy',
    ],
    [
        'name' => 'Nucs AI',
        'site_slug' => 'nucsai',
        'company_display_name' => 'Nucs AI',
    ],
    [
        'name' => 'Paraşüt',
        'site_slug' => 'parasut',
        'company_display_name' => 'Paraşüt',
    ],
];

$recruiteeConfigDefaults = [
    'provider' => 'recruitee',
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
            'base_url' => 'https://'.$board['site_slug'].'.recruitee.com/api/offers',
            'type' => JobSourceType::ApiIntegration,
            'is_active' => true,
            'config' => array_merge($recruiteeConfigDefaults, [
                'site_slug' => $board['site_slug'],
                'company_display_name' => $board['company_display_name'],
            ]),
        ],
    );

    echo 'Job source ready: '.$source->name.' (id='.$source->id.', slug='.$board['site_slug'].")\n";
}

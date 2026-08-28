<?php

declare(strict_types=1);

use App\Enums\JobSourceType;
use App\Models\JobSource;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

/**
 * Production seed: Phase 2 active boards (5 total).
 *
 * Additional boards discovered in Phase 2 are documented below.
 * Run this script again after adding a board to $activeBoards.
 */
$activeBoards = [
    [
        'name' => 'Commencis',
        'site_slug' => 'commencis',
        'company_display_name' => 'Commencis',
    ],
    [
        'name' => 'Midas',
        'site_slug' => 'getmidas',
        'company_display_name' => 'Midas',
    ],
    [
        'name' => 'Insider One',
        'site_slug' => 'insiderone',
        'company_display_name' => 'Insider One',
        'ingest_policy' => 'global',
    ],
    [
        'name' => 'Trendyol',
        'site_slug' => 'trendyol',
        'company_display_name' => 'Trendyol',
    ],
    [
        'name' => 'Dream Games',
        'site_slug' => 'dreamgames',
        'company_display_name' => 'Dream Games',
    ],
    [
        'name' => 'iyzico',
        'site_slug' => 'iyzico',
        'company_display_name' => 'iyzico',
    ],
    [
        'name' => 'Grand Games',
        'site_slug' => 'grand',
        'company_display_name' => 'Grand Games',
    ],
    [
        'name' => 'Ajax Systems',
        'site_slug' => 'ajax',
        'company_display_name' => 'Ajax Systems',
    ],
];

/**
 * Phase 2 discovery — monitor / later (not seeded):
 *
 * Category A (API): binance — global board, only 1 TR/IST job out of 263
 *
 * Category B (Istanbul jobs, valid API) — Phase A seeded:
 * - iyzico (slug: iyzico) — 11 jobs, 11 Istanbul, 7 fresh
 * - Grand Games (slug: grand) — 10 jobs, 10 Istanbul, 5 fresh
 *
 * Phase C quick win (seeded 2026-08-13):
 * - Ajax Systems (slug: ajax) — ~211 jobs board, ~8 Istanbul; ingest_policy=turkey_first
 *
 * Category B — monitor / later (not seeded):
 * Category C / inactive:
 * - codeway — HTTP 200 but 0 open postings (board empty)
 * - papara — HTTP 404 (board no longer on Lever public API)
 * - peakgames — 20 jobs but 0 Istanbul, 17 stale, 3 fresh
 * - useinsider — HTTP 404 (legacy slug retired)
 * - grandgames, ajaxsystems, midas, firefly — wrong slugs (404)
 */
$leverConfigDefaults = [
    'provider' => 'lever',
    'region' => 'global',
    'page_size' => 100,
    'max_pages' => 5,
    'max_listings' => 200,
    'refresh_interval_minutes' => 360,
    'max_posting_age_days' => 365,
    'ingest_policy' => 'turkey_first',
];

foreach ($activeBoards as $board) {
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
            'base_url' => 'https://api.lever.co/v0/postings/'.$board['site_slug'],
            'type' => JobSourceType::ApiIntegration,
            'is_active' => true,
            'config' => array_merge($leverConfigDefaults, [
                'site_slug' => $board['site_slug'],
                'company_display_name' => $board['company_display_name'],
            ], $configOverrides),
        ],
    );

    echo 'Job source ready: '.$source->name.' (id='.$source->id.', slug='.$board['site_slug'].")\n";
}

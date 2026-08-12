<?php

declare(strict_types=1);

use App\Enums\JobSourceType;
use App\Models\JobSource;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$source = JobSource::query()->updateOrCreate(
    [
        'name' => 'Kariyer.net',
    ],
    [
        'base_url' => 'https://www.kariyer.net/is-ilanlari/yazilim',
        'type' => JobSourceType::Scraper,
        'is_active' => true,
        'config' => [
            'provider' => 'kariyer-net',
            'listing_url' => 'https://www.kariyer.net/is-ilanlari/yazilim',
            'limit' => 10,
            'max_listings' => 50,
            'max_pages' => 5,
            'page_size' => 25,
            'refresh_interval_minutes' => 360,
        ],
    ],
);

echo 'Job source ready: '.$source->name.' (id='.$source->id.")\n";

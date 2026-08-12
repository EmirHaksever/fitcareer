<?php

declare(strict_types=1);

return [
    'timeout' => (int) env('SCRAPER_HTTP_TIMEOUT', 30),

    'connect_timeout' => (int) env('SCRAPER_HTTP_CONNECT_TIMEOUT', 10),

    'user_agent' => env('SCRAPER_USER_AGENT', 'FitCareer/1.0 (job-ingestion; contact@fitcareer.local)'),

    'accept_language' => env('SCRAPER_ACCEPT_LANGUAGE', 'tr-TR,tr;q=0.9,en-US;q=0.8,en;q=0.7'),

    'default_fetch_limit' => (int) env('SCRAPER_DEFAULT_FETCH_LIMIT', 10),

    'default_page_size' => (int) env('SCRAPER_DEFAULT_PAGE_SIZE', 25),

    'max_pages' => (int) env('SCRAPER_MAX_PAGES', 5),

    'max_listings' => (int) env('SCRAPER_MAX_LISTINGS', 50),

    'queue' => env('SCRAPER_QUEUE', 'default'),

    'stale_after_hours' => (int) env('SCRAPER_STALE_AFTER_HOURS', 48),

    'expire_after_hours' => (int) env('SCRAPER_EXPIRE_AFTER_HOURS', 168),

    'default_refresh_interval_minutes' => (int) env('SCRAPER_DEFAULT_REFRESH_INTERVAL_MINUTES', 360),
];

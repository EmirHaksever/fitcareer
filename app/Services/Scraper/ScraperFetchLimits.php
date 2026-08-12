<?php

declare(strict_types=1);

namespace App\Services\Scraper;

final class ScraperFetchLimits
{
    public function __construct(
        public readonly int $maxListings,
        public readonly int $maxPages,
        public readonly int $pageSize,
    ) {}

    /**
     * Legacy test/dev path: honors source `limit` key capped at 10.
     *
     * @param  array<string, mixed>  $config
     */
    public static function legacy(array $config): self
    {
        $limit = max(1, min(10, (int) ($config['limit'] ?? config('scraper.default_fetch_limit'))));

        return new self(
            maxListings: $limit,
            maxPages: 1,
            pageSize: $limit,
        );
    }

    /**
     * Production import path: pagination-aware limits without the hard 10 cap.
     *
     * @param  array<string, mixed>  $config
     */
    public static function production(array $config): self
    {
        return new self(
            maxListings: max(1, (int) ($config['max_listings'] ?? config('scraper.max_listings'))),
            maxPages: max(1, (int) ($config['max_pages'] ?? config('scraper.max_pages'))),
            pageSize: max(1, (int) ($config['page_size'] ?? config('scraper.default_page_size'))),
        );
    }
}

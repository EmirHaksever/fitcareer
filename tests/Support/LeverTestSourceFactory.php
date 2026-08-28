<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Enums\JobSourceType;
use App\Models\JobSource;

final class LeverTestSourceFactory
{
    /**
     * @param  array<string, mixed>  $configOverrides
     */
    public static function create(string $name, string $siteSlug, array $configOverrides = []): JobSource
    {
        return JobSource::query()->create([
            'name' => $name,
            'base_url' => 'https://api.lever.co/v0/postings/'.$siteSlug,
            'type' => JobSourceType::ApiIntegration,
            'is_active' => true,
            'config' => array_merge([
                'provider' => 'lever',
                'site_slug' => $siteSlug,
                'company_display_name' => $name,
                'region' => 'global',
                'page_size' => 2,
                'max_pages' => 5,
                'max_listings' => 10,
                'refresh_interval_minutes' => 360,
                'max_posting_age_days' => 365,
            ], $configOverrides),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function loadFixture(string $filename): array
    {
        $path = base_path('tests/Fixtures/Scraper/lever/'.$filename);
        $payload = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($payload)) {
            throw new \RuntimeException('Fixture is not an array: '.$filename);
        }

        return $payload;
    }
}

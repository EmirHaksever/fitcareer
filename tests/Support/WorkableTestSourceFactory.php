<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Enums\JobSourceType;
use App\Models\JobSource;

final class WorkableTestSourceFactory
{
    /**
     * @param  array<string, mixed>  $configOverrides
     */
    public static function create(string $name, string $siteSlug, array $configOverrides = []): JobSource
    {
        return JobSource::query()->create([
            'name' => $name,
            'base_url' => 'https://apply.workable.com/api/v1/widget/accounts/'.$siteSlug,
            'type' => JobSourceType::ApiIntegration,
            'is_active' => true,
            'config' => array_merge([
                'provider' => 'workable',
                'site_slug' => $siteSlug,
                'company_display_name' => $name,
                'page_size' => 100,
                'max_pages' => 1,
                'max_listings' => 200,
                'refresh_interval_minutes' => 360,
                'max_posting_age_days' => 365,
            ], $configOverrides),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function loadFixture(string $filename): array
    {
        $path = base_path('tests/Fixtures/Scraper/workable/'.$filename);
        $payload = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($payload)) {
            throw new \RuntimeException('Fixture is not an array: '.$filename);
        }

        return $payload;
    }
}

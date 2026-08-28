<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Enums\JobSourceType;
use App\Models\JobSource;

final class RecruiteeTestSourceFactory
{
    /**
     * @param  array<string, mixed>  $configOverrides
     */
    public static function create(string $name, string $siteSlug, array $configOverrides = []): JobSource
    {
        return JobSource::query()->create([
            'name' => $name,
            'base_url' => 'https://'.$siteSlug.'.recruitee.com/api/offers',
            'type' => JobSourceType::ApiIntegration,
            'is_active' => true,
            'config' => array_merge([
                'provider' => 'recruitee',
                'site_slug' => $siteSlug,
                'company_display_name' => $name,
                'page_size' => 100,
                'max_pages' => 1,
                'max_listings' => 200,
                'refresh_interval_minutes' => 360,
                'max_posting_age_days' => 365,
                'ingest_policy' => 'turkey_first',
            ], $configOverrides),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function loadFixture(string $filename): array
    {
        $path = base_path('tests/Fixtures/Scraper/recruitee/'.$filename);
        $payload = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($payload)) {
            throw new \RuntimeException('Fixture is not an array: '.$filename);
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public static function turkeyListing(string $id = '2706848', string $title = 'Senior Financial Analyst (FP&A)'): array
    {
        return [
            'id' => (int) $id,
            'slug' => 'senior-financial-analyst-fpa',
            'title' => $title,
            'description' => '<p>Build financial models for Mikro Yazılım.</p>',
            'country' => 'Türkiye',
            'country_code' => 'TR',
            'city' => 'İstanbul',
            'location' => 'İstanbul, İstanbul, Türkiye',
            'locations' => [
                [
                    'city' => 'İstanbul',
                    'country' => 'Türkiye',
                    'country_code' => 'TR',
                ],
            ],
            'remote' => false,
            'hybrid' => true,
            'on_site' => false,
            'employment_type_code' => 'fulltime_permanent',
            'category_code' => 'finance',
            'published_at' => '2026-08-11 10:00:00 UTC',
            'created_at' => '2026-08-10 09:00:00 UTC',
            'updated_at' => '2026-08-12 11:00:00 UTC',
            'careers_url' => 'https://mikroyazilim.recruitee.com/o/senior-financial-analyst-fpa',
            'careers_apply_url' => 'https://mikroyazilim.recruitee.com/o/senior-financial-analyst-fpa/c/new',
            'company_name' => 'Mikro Yazılım',
            'status' => 'published',
        ];
    }
}

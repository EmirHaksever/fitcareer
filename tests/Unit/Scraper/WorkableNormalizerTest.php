<?php

declare(strict_types=1);

namespace Tests\Unit\Scraper;

use App\Enums\EmploymentType;
use App\Enums\WorkType;
use App\Exceptions\ScraperFetchException;
use App\Services\Scraper\JobNormalizerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\WorkableTestSourceFactory;
use Tests\TestCase;

class WorkableNormalizerTest extends TestCase
{
    use RefreshDatabase;

    private JobNormalizerService $normalizer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->normalizer = new JobNormalizerService;
    }

    public function test_normalize_maps_workable_fields_to_canonical_job_attributes(): void
    {
        $source = WorkableTestSourceFactory::create('Wingie Enuygun', 'wingieenuygun');
        $payload = WorkableTestSourceFactory::loadFixture('wingie-single.json');
        $rawListing = $payload['jobs'][0];
        $rawListing['_workable_account_name'] = $payload['name'];

        $attributes = $this->normalizer->normalize($source, $rawListing);

        $this->assertSame('A8A326BDEF', $attributes['external_id']);
        $this->assertSame('Campaign Management Specialist', $attributes['title']);
        $this->assertSame('Build campaigns for global travel products.', $attributes['description']);
        $this->assertSame('https://apply.workable.com/j/A8A326BDEF', $attributes['external_url']);
        $this->assertSame('Istanbul', $attributes['city']);
        $this->assertSame('Türkiye', $attributes['country']);
        $this->assertSame(EmploymentType::FullTime, $attributes['employment_type']);
        $this->assertSame(WorkType::Onsite, $attributes['work_type']);
        $this->assertSame('Business Development', $attributes['category']);
        $this->assertSame('Wingie Enuygun', $attributes['source_company_name']);
        $this->assertInstanceOf(Carbon::class, $attributes['published_at']);
        $this->assertSame('2026-07-31', $attributes['published_at']->toDateString());
        $this->assertNotEmpty($attributes['content_hash']);
    }

    public function test_normalize_strips_html_from_description(): void
    {
        $source = WorkableTestSourceFactory::create('Wingie Enuygun', 'wingieenuygun');
        $rawListing = WorkableTestSourceFactory::loadFixture('wingie-single.json')['jobs'][0];
        $rawListing['description'] = '<p>HTML <strong>description</strong></p>';

        $attributes = $this->normalizer->normalize($source, $rawListing);

        $this->assertSame('HTML description', $attributes['description']);
    }

    public function test_normalize_uses_locations_when_city_and_country_missing(): void
    {
        $source = WorkableTestSourceFactory::create('Wingie Enuygun', 'wingieenuygun');
        $rawListing = WorkableTestSourceFactory::loadFixture('wingie-single.json')['jobs'][0];
        unset($rawListing['city'], $rawListing['country']);

        $attributes = $this->normalizer->normalize($source, $rawListing);

        $this->assertSame('Istanbul', $attributes['city']);
        $this->assertSame('Türkiye', $attributes['country']);
    }

    public function test_normalize_maps_telecommuting_true_to_remote_work_type(): void
    {
        $source = WorkableTestSourceFactory::create('Wingie Enuygun', 'wingieenuygun');
        $rawListing = WorkableTestSourceFactory::loadFixture('wingie-single.json')['jobs'][0];
        $rawListing['telecommuting'] = true;

        $attributes = $this->normalizer->normalize($source, $rawListing);

        $this->assertSame(WorkType::Remote, $attributes['work_type']);
    }

    public function test_normalize_uses_company_display_name_over_account_name(): void
    {
        $source = WorkableTestSourceFactory::create('Wingie Enuygun', 'wingieenuygun', [
            'company_display_name' => 'WINGIE ENUYGUN',
        ]);
        $rawListing = WorkableTestSourceFactory::loadFixture('wingie-single.json')['jobs'][0];
        $rawListing['_workable_account_name'] = 'Wingie Enuygun Group';

        $attributes = $this->normalizer->normalize($source, $rawListing);

        $this->assertSame('WINGIE ENUYGUN', $attributes['source_company_name']);
    }

    public function test_normalize_falls_back_to_source_name_when_company_display_name_missing(): void
    {
        $source = WorkableTestSourceFactory::create('Wingie Enuygun', 'wingieenuygun');
        $config = $source->config;
        unset($config['company_display_name']);
        $source->config = $config;
        $source->save();

        $rawListing = WorkableTestSourceFactory::loadFixture('wingie-single.json')['jobs'][0];
        unset($rawListing['_workable_account_name']);

        $attributes = $this->normalizer->normalize($source->fresh(), $rawListing);

        $this->assertSame('Wingie Enuygun', $attributes['source_company_name']);
    }

    public function test_normalize_rejects_stale_posting_when_max_posting_age_days_configured(): void
    {
        $source = WorkableTestSourceFactory::create('Stale Board', 'stale-board', [
            'max_posting_age_days' => 365,
        ]);
        $rawListing = WorkableTestSourceFactory::loadFixture('stale-posting.json')['jobs'][0];

        $this->expectException(ScraperFetchException::class);
        $this->expectExceptionMessage('max_posting_age_days');

        $this->normalizer->normalize($source, $rawListing);
    }

    public function test_normalize_throws_when_shortcode_or_title_missing(): void
    {
        $source = WorkableTestSourceFactory::create('Wingie Enuygun', 'wingieenuygun');

        $this->expectException(ScraperFetchException::class);
        $this->expectExceptionMessage('missing shortcode or title');

        $this->normalizer->normalize($source, ['title' => 'No shortcode']);
    }
}

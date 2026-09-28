<?php

declare(strict_types=1);

namespace Tests\Unit\Scraper;

use App\Enums\EmploymentType;
use App\Enums\WorkType;
use App\Exceptions\ScraperFetchException;
use App\Services\Scraper\JobNormalizerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\GreenhouseTestSourceFactory;
use Tests\TestCase;

class GreenhouseNormalizerTest extends TestCase
{
    use RefreshDatabase;

    private JobNormalizerService $normalizer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->normalizer = new JobNormalizerService;
    }

    public function test_normalize_maps_greenhouse_fields_to_canonical_job_attributes(): void
    {
        $source = GreenhouseTestSourceFactory::create('Good Job Games', 'goodjobgames');
        $rawListing = GreenhouseTestSourceFactory::loadFixture('goodjobgames-single.json')['jobs'][0];

        $attributes = $this->normalizer->normalize($source, $rawListing);

        $this->assertSame('4968083003', $attributes['external_id']);
        $this->assertSame('2D Animator, Studio', $attributes['title']);
        $this->assertSame('Crafting effects and 2D animations for UI elements.', $attributes['description']);
        $this->assertSame(
            'https://job-boards.greenhouse.io/goodjobgames/jobs/4968083003',
            $attributes['external_url'],
        );
        $this->assertSame('Istanbul', $attributes['city']);
        $this->assertSame('Türkiye', $attributes['country']);
        $this->assertSame(EmploymentType::FullTime, $attributes['employment_type']);
        $this->assertSame(WorkType::Onsite, $attributes['work_type']);
        $this->assertSame('Art', $attributes['category']);
        $this->assertSame('Good Job Games', $attributes['source_company_name']);
        $this->assertInstanceOf(Carbon::class, $attributes['published_at']);
        $this->assertSame('2026-04-30', $attributes['published_at']->toDateString());
        $this->assertNotEmpty($attributes['content_hash']);
    }

    public function test_normalize_strips_entity_encoded_html_from_content(): void
    {
        $source = GreenhouseTestSourceFactory::create('Good Job Games', 'goodjobgames');
        $rawListing = GreenhouseTestSourceFactory::loadFixture('goodjobgames-single.json')['jobs'][0];
        $rawListing['content'] = '&lt;p&gt;HTML &lt;strong&gt;description&lt;/strong&gt;&lt;/p&gt;';

        $attributes = $this->normalizer->normalize($source, $rawListing);

        $this->assertSame('HTML description', $attributes['description']);
    }

    public function test_normalize_falls_back_to_updated_at_when_first_published_missing(): void
    {
        $source = GreenhouseTestSourceFactory::create('Good Job Games', 'goodjobgames');
        $rawListing = GreenhouseTestSourceFactory::loadFixture('goodjobgames-single.json')['jobs'][0];
        unset($rawListing['first_published']);
        $rawListing['updated_at'] = '2026-05-15T10:00:00-04:00';

        $attributes = $this->normalizer->normalize($source, $rawListing);

        $this->assertSame('2026-05-15', $attributes['published_at']->toDateString());
    }

    public function test_normalize_uses_location_name_when_offices_missing(): void
    {
        $source = GreenhouseTestSourceFactory::create('Medsien', 'medsien');
        $rawListing = GreenhouseTestSourceFactory::loadFixture('goodjobgames-single.json')['jobs'][0];
        unset($rawListing['offices']);
        $rawListing['location'] = ['name' => 'Istanbul, Türkiye'];

        $attributes = $this->normalizer->normalize($source, $rawListing);

        $this->assertSame('Istanbul', $attributes['city']);
        $this->assertSame('Türkiye', $attributes['country']);
    }

    public function test_normalize_uses_company_display_name(): void
    {
        $source = GreenhouseTestSourceFactory::create('Good Job Games', 'goodjobgames', [
            'company_display_name' => 'GJG',
        ]);
        $rawListing = GreenhouseTestSourceFactory::loadFixture('goodjobgames-single.json')['jobs'][0];

        $attributes = $this->normalizer->normalize($source, $rawListing);

        $this->assertSame('GJG', $attributes['source_company_name']);
    }

    public function test_normalize_classifies_turkey_location_via_location_classification_service(): void
    {
        $source = GreenhouseTestSourceFactory::create('Good Job Games', 'goodjobgames');
        $rawListing = GreenhouseTestSourceFactory::loadFixture('goodjobgames-single.json')['jobs'][0];
        unset($rawListing['offices']);
        $rawListing['location'] = ['name' => 'Istanbul'];

        $attributes = $this->normalizer->normalize($source, $rawListing);

        $this->assertSame('Istanbul', $attributes['city']);
        $this->assertSame('Türkiye', $attributes['country']);
    }

    public function test_normalize_maps_greenhouse_provider_updated_at(): void
    {
        $source = GreenhouseTestSourceFactory::create('Good Job Games', 'goodjobgames');
        $rawListing = GreenhouseTestSourceFactory::loadFixture('goodjobgames-single.json')['jobs'][0];

        $attributes = $this->normalizer->normalize($source, $rawListing);

        $this->assertInstanceOf(Carbon::class, $attributes['provider_updated_at']);
        $this->assertSame('2026-06-11', $attributes['provider_updated_at']->toDateString());
    }

    public function test_normalize_decodes_nbsp_in_greenhouse_content(): void
    {
        $source = GreenhouseTestSourceFactory::create('Good Job Games', 'goodjobgames');
        $rawListing = GreenhouseTestSourceFactory::loadFixture('goodjobgames-single.json')['jobs'][0];
        $rawListing['content'] = '&lt;p&gt;Hello&nbsp;world&lt;/p&gt;';

        $attributes = $this->normalizer->normalize($source, $rawListing);

        $this->assertSame('Hello world', $attributes['description']);
    }

    public function test_normalize_rejects_stale_posting_when_max_posting_age_days_configured(): void
    {
        $source = GreenhouseTestSourceFactory::create('Stale Board', 'stale-board', [
            'max_posting_age_days' => 365,
        ]);
        $rawListing = GreenhouseTestSourceFactory::loadFixture('stale-posting.json')['jobs'][0];

        $this->expectException(ScraperFetchException::class);
        $this->expectExceptionMessage('max_posting_age_days');

        $this->normalizer->normalize($source, $rawListing);
    }

    public function test_normalize_throws_when_id_or_title_missing(): void
    {
        $source = GreenhouseTestSourceFactory::create('Good Job Games', 'goodjobgames');

        $this->expectException(ScraperFetchException::class);
        $this->expectExceptionMessage('missing id or title');

        $this->normalizer->normalize($source, ['title' => 'No id']);
    }
}

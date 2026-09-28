<?php

declare(strict_types=1);

namespace Tests\Unit\Scraper;

use App\Enums\EmploymentType;
use App\Enums\WorkType;
use App\Exceptions\ScraperFetchException;
use App\Services\Scraper\JobNormalizerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\LeverTestSourceFactory;
use Tests\TestCase;

class LeverNormalizerTest extends TestCase
{
    use RefreshDatabase;

    private JobNormalizerService $normalizer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->normalizer = new JobNormalizerService;
    }

    public function test_normalize_maps_lever_fields_to_canonical_job_attributes(): void
    {
        $source = LeverTestSourceFactory::create('Commencis', 'commencis');
        $rawListing = LeverTestSourceFactory::loadFixture('commencis-single.json')[0];

        $attributes = $this->normalizer->normalize($source, $rawListing);

        $this->assertSame('7440425c-1adf-40da-b230-281fe4a3caaf', $attributes['external_id']);
        $this->assertSame('Senior AI Software Engineer', $attributes['title']);
        $this->assertSame('Build AI-powered products for global clients.', $attributes['description']);
        $this->assertSame('https://jobs.lever.co/commencis/7440425c-1adf-40da-b230-281fe4a3caaf', $attributes['external_url']);
        $this->assertSame('Istanbul', $attributes['city']);
        $this->assertSame('Türkiye', $attributes['country']);
        $this->assertSame(EmploymentType::FullTime, $attributes['employment_type']);
        $this->assertSame(WorkType::Onsite, $attributes['work_type']);
        $this->assertSame('Engineering', $attributes['category']);
        $this->assertSame('Commencis', $attributes['source_company_name']);
        $this->assertInstanceOf(Carbon::class, $attributes['published_at']);
        $this->assertSame(
            Carbon::createFromTimestampMs(1783322836118)->toIso8601String(),
            $attributes['published_at']->toIso8601String(),
        );
        $this->assertNotEmpty($attributes['content_hash']);
    }

    public function test_normalize_uses_html_description_when_plain_text_missing(): void
    {
        $source = LeverTestSourceFactory::create('Commencis', 'commencis');
        $rawListing = LeverTestSourceFactory::loadFixture('commencis-single.json')[0];
        unset($rawListing['descriptionPlain']);
        $rawListing['description'] = '<p>HTML <strong>description</strong></p>';

        $attributes = $this->normalizer->normalize($source, $rawListing);

        $this->assertSame('HTML description', $attributes['description']);
    }

    public function test_normalize_rejects_stale_posting_when_max_posting_age_days_configured(): void
    {
        $source = LeverTestSourceFactory::create('Commencis', 'commencis', [
            'max_posting_age_days' => 365,
        ]);
        $rawListing = LeverTestSourceFactory::loadFixture('stale-posting.json')[0];

        $this->expectException(ScraperFetchException::class);
        $this->expectExceptionMessage('max_posting_age_days');

        $this->normalizer->normalize($source, $rawListing);
    }

    public function test_normalize_allows_stale_posting_when_max_posting_age_days_not_configured(): void
    {
        $source = LeverTestSourceFactory::create('Commencis', 'commencis');
        $config = $source->config;
        unset($config['max_posting_age_days']);
        $source->config = $config;
        $source->save();

        $rawListing = LeverTestSourceFactory::loadFixture('stale-posting.json')[0];

        $attributes = $this->normalizer->normalize($source->fresh(), $rawListing);

        $this->assertSame('stale-job-001', $attributes['external_id']);
    }

    public function test_normalize_corrects_lever_istanbul_country_swap(): void
    {
        $source = LeverTestSourceFactory::create('Insider', 'insider');
        $rawListing = LeverTestSourceFactory::loadFixture('commencis-single.json')[0];
        $rawListing['categories']['location'] = 'Istanbul';
        $rawListing['categories']['allLocations'] = ['Istanbul'];

        $attributes = $this->normalizer->normalize($source, $rawListing);

        $this->assertSame('Istanbul', $attributes['city']);
        $this->assertSame('Türkiye', $attributes['country']);
    }

    public function test_normalize_throws_when_external_id_or_title_missing(): void
    {
        $source = LeverTestSourceFactory::create('Commencis', 'commencis');

        $this->expectException(ScraperFetchException::class);
        $this->expectExceptionMessage('missing id or text');

        $this->normalizer->normalize($source, ['text' => 'No id']);
    }
}

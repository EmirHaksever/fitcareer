<?php

declare(strict_types=1);

namespace Tests\Unit\Scraper;

use App\Enums\EmploymentType;
use App\Enums\WorkType;
use App\Exceptions\ScraperFetchException;
use App\Services\Scraper\JobNormalizerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\AshbyTestSourceFactory;
use Tests\TestCase;

class AshbyNormalizerTest extends TestCase
{
    use RefreshDatabase;

    private JobNormalizerService $normalizer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->normalizer = new JobNormalizerService;
    }

    public function test_normalize_maps_ashby_fields_to_canonical_job_attributes(): void
    {
        $source = AshbyTestSourceFactory::create('Codeway', 'codeway');
        $rawListing = AshbyTestSourceFactory::loadFixture('codeway-single.json')['jobs'][0];

        $attributes = $this->normalizer->normalize($source, $rawListing);

        $this->assertSame('3f5c2489-d889-4783-b846-70f04d274094', $attributes['external_id']);
        $this->assertSame('Growth Manager, Learna', $attributes['title']);
        $this->assertSame('Build growth campaigns for Learna.', $attributes['description']);
        $this->assertSame(
            'https://jobs.ashbyhq.com/codeway/3f5c2489-d889-4783-b846-70f04d274094',
            $attributes['external_url'],
        );
        $this->assertSame('Istanbul', $attributes['city']);
        $this->assertSame('Türkiye', $attributes['country']);
        $this->assertSame(EmploymentType::FullTime, $attributes['employment_type']);
        $this->assertSame(WorkType::Hybrid, $attributes['work_type']);
        $this->assertSame('Growth', $attributes['category']);
        $this->assertSame('Codeway', $attributes['source_company_name']);
        $this->assertInstanceOf(Carbon::class, $attributes['published_at']);
        $this->assertSame('2026-07-06', $attributes['published_at']->toDateString());
        $this->assertNotEmpty($attributes['content_hash']);
    }

    public function test_normalize_strips_html_when_plain_description_missing(): void
    {
        $source = AshbyTestSourceFactory::create('Codeway', 'codeway');
        $rawListing = AshbyTestSourceFactory::loadFixture('codeway-single.json')['jobs'][0];
        unset($rawListing['descriptionPlain']);
        $rawListing['descriptionHtml'] = '<p>HTML <strong>description</strong></p>';

        $attributes = $this->normalizer->normalize($source, $rawListing);

        $this->assertSame('HTML description', $attributes['description']);
    }

    public function test_normalize_maps_remote_workplace_type(): void
    {
        $source = AshbyTestSourceFactory::create('Codeway', 'codeway');
        $rawListing = AshbyTestSourceFactory::loadFixture('codeway-single.json')['jobs'][0];
        $rawListing['workplaceType'] = 'Remote';
        $rawListing['isRemote'] = true;

        $attributes = $this->normalizer->normalize($source, $rawListing);

        $this->assertSame(WorkType::Remote, $attributes['work_type']);
    }

    public function test_normalize_uses_company_display_name(): void
    {
        $source = AshbyTestSourceFactory::create('Codeway', 'codeway', [
            'company_display_name' => 'CODEWAY HQ',
        ]);
        $rawListing = AshbyTestSourceFactory::loadFixture('codeway-single.json')['jobs'][0];

        $attributes = $this->normalizer->normalize($source, $rawListing);

        $this->assertSame('CODEWAY HQ', $attributes['source_company_name']);
    }

    public function test_normalize_classifies_turkey_location_via_location_classification_service(): void
    {
        $source = AshbyTestSourceFactory::create('Codeway', 'codeway');
        $rawListing = AshbyTestSourceFactory::loadFixture('codeway-single.json')['jobs'][0];
        unset($rawListing['address']);

        $attributes = $this->normalizer->normalize($source, $rawListing);

        $this->assertSame('Istanbul', $attributes['city']);
        $this->assertSame('Türkiye', $attributes['country']);
    }

    public function test_normalize_rejects_stale_posting_when_max_posting_age_days_configured(): void
    {
        $source = AshbyTestSourceFactory::create('Stale Board', 'stale-board', [
            'max_posting_age_days' => 365,
        ]);
        $rawListing = AshbyTestSourceFactory::loadFixture('stale-posting.json')['jobs'][0];

        $this->expectException(ScraperFetchException::class);
        $this->expectExceptionMessage('max_posting_age_days');

        $this->normalizer->normalize($source, $rawListing);
    }

    public function test_normalize_throws_when_id_or_title_missing(): void
    {
        $source = AshbyTestSourceFactory::create('Codeway', 'codeway');

        $this->expectException(ScraperFetchException::class);
        $this->expectExceptionMessage('missing id or title');

        $this->normalizer->normalize($source, ['title' => 'No id']);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Scraper;

use App\Enums\EmploymentType;
use App\Enums\ExperienceLevel;
use App\Enums\JobSourceType;
use App\Enums\WorkType;
use App\Models\JobSource;
use App\Services\Scraper\JobNormalizerService;
use App\Services\Scraper\KariyerNetHtmlParser;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class KariyerNetNormalizerTest extends TestCase
{
    #[Test]
    public function it_maps_kariyer_net_detail_fields(): void
    {
        $html = file_get_contents(base_path('tests/Fixtures/Scraper/kariyer-net-detail-4515714.html'));
        $url = 'https://www.kariyer.net/is-ilani/acme-yazilim-gelistirici-4515714';

        $raw = app(KariyerNetHtmlParser::class)->parseDetailPage($html, $url);

        $this->assertSame('4515714', $raw['external_id']);
        $this->assertSame($url, $raw['external_url']);
        $this->assertSame('Yazılım Uzmanı', $raw['title']);
        $this->assertSame('Acme Yazılım A.Ş.', $raw['company']);
        $this->assertSame('İstanbul(Avr.) (Sarıyer)', $raw['location']);
        $this->assertSame('Tam Zamanlı', $raw['employment_type_raw']);
        $this->assertSame('En az 8 yıl tecrübeli', $raw['experience_level_raw']);
        $this->assertSame('Uzaktan / Remote', $raw['work_model_raw']);
        $this->assertSame('29.07.2026', $raw['published_date_raw']);

        $source = JobSource::factory()->create([
            'type' => JobSourceType::Scraper,
            'config' => ['provider' => 'kariyer-net'],
        ]);

        $normalized = app(JobNormalizerService::class)->normalize($source, $raw);

        $this->assertSame('4515714', $normalized['external_id']);
        $this->assertSame('Yazılım Uzmanı', $normalized['title']);
        $this->assertSame('Acme Yazılım A.Ş.', $normalized['source_company_name']);
        $this->assertStringContainsString('Flutter', $normalized['description']);
        $this->assertSame('İstanbul(Avr.)', $normalized['city']);
        $this->assertSame('Türkiye', $normalized['country']);
        $this->assertSame(EmploymentType::FullTime, $normalized['employment_type']);
        $this->assertSame(WorkType::Remote, $normalized['work_type']);
        $this->assertSame(ExperienceLevel::Senior, $normalized['experience_level']);
        $this->assertNotNull($normalized['published_at']);
        $this->assertNotEmpty($normalized['content_hash']);
    }

    #[Test]
    public function it_extracts_external_id_from_url(): void
    {
        $parser = app(KariyerNetHtmlParser::class);

        $this->assertSame(
            '4477112',
            $parser->extractExternalIdFromUrl('https://www.kariyer.net/is-ilani/fonet-lider-yazilim-gelistirme-uzmani-full-stack-4477112'),
        );
    }

    #[Test]
    public function it_extracts_listing_urls_and_skips_disallowed_paths(): void
    {
        $html = file_get_contents(base_path('tests/Fixtures/Scraper/kariyer-net-listing.html'));

        $urls = app(KariyerNetHtmlParser::class)->extractListingUrls($html);

        $this->assertCount(2, $urls);
        $this->assertContains('https://www.kariyer.net/is-ilani/acme-yazilim-gelistirici-4515714', $urls);
        $this->assertContains('https://www.kariyer.net/is-ilani/fonet-lider-yazilim-gelistirme-uzmani-full-stack-4477112', $urls);
    }
}

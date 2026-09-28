<?php

declare(strict_types=1);

namespace Tests\Unit\Scraper;

use App\Enums\JobOrigin;
use App\Enums\JobSourceType;
use App\Enums\JobStatus;
use App\Exceptions\ScraperFetchException;
use App\Models\Job;
use App\Models\JobSource;
use App\Services\Scraper\JobIngestionService;
use App\Services\Scraper\KariyerNetHtmlParser;
use App\Services\Scraper\ScraperClientService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class KariyerNetIngestionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_parses_listing_and_detail_pages_from_fixtures(): void
    {
        $listingHtml = file_get_contents(base_path('tests/Fixtures/Scraper/kariyer-net-listing.html'));
        $detailHtmlOne = file_get_contents(base_path('tests/Fixtures/Scraper/kariyer-net-detail-4515714.html'));
        $detailHtmlTwo = file_get_contents(base_path('tests/Fixtures/Scraper/kariyer-net-detail-4477112.html'));

        Http::fake([
            'www.kariyer.net/is-ilanlari/yazilim' => Http::response($listingHtml, 200),
            'www.kariyer.net/is-ilani/acme-yazilim-gelistirici-4515714' => Http::response($detailHtmlOne, 200),
            'www.kariyer.net/is-ilani/fonet-lider-yazilim-gelistirme-uzmani-full-stack-4477112' => Http::response($detailHtmlTwo, 200),
        ]);

        $source = JobSource::factory()->create([
            'name' => 'Kariyer.net Test',
            'base_url' => 'https://www.kariyer.net/is-ilanlari/yazilim',
            'type' => JobSourceType::Scraper,
            'config' => [
                'provider' => 'kariyer-net',
                'listing_url' => 'https://www.kariyer.net/is-ilanlari/yazilim',
                'limit' => 10,
            ],
        ]);

        $listings = app(ScraperClientService::class)->fetchListings($source);

        $this->assertCount(2, $listings);
        $this->assertSame('4515714', $listings[0]['external_id']);
        $this->assertSame('4477112', $listings[1]['external_id']);

        $first = app(JobIngestionService::class)->ingest($source, $listings[0]);
        $this->assertTrue($first['created']);
        $this->assertSame('Yazılım Uzmanı', $first['job']->title);
        $this->assertSame(JobOrigin::Scraped, $first['job']->source);
        $this->assertSame(JobStatus::Published, $first['job']->status);
        $this->assertSame('4515714', $first['job']->external_id);
        $this->assertSame('Acme Yazılım A.Ş.', $first['job']->source_company_name);

        $secondIngest = app(JobIngestionService::class)->ingest($source, $listings[0]);
        $this->assertFalse($secondIngest['created']);
        $this->assertSame($first['job']->id, $secondIngest['job']->id);
        $this->assertSame(1, Job::query()->where('job_source_id', $source->id)->where('external_id', '4515714')->count());

        $second = app(JobIngestionService::class)->ingest($source, $listings[1]);
        $this->assertTrue($second['created']);
        $this->assertSame(2, Job::query()->where('job_source_id', $source->id)->count());
    }

    #[Test]
    public function parser_rejects_disallowed_urls(): void
    {
        $parser = app(KariyerNetHtmlParser::class);

        $this->expectException(ScraperFetchException::class);

        $parser->assertAllowedListingUrl('https://www.kariyer.net/filtre/yazilim');
    }
}

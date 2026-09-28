<?php

declare(strict_types=1);

namespace Tests\Unit\Scraper;

use App\Exceptions\ScraperFetchException;
use App\Services\Scraper\KariyerNetHtmlParser;
use App\Services\Scraper\ScraperClientService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\LeverTestSourceFactory;
use Tests\TestCase;

class LeverClientTest extends TestCase
{
    use RefreshDatabase;

    private ScraperClientService $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = new ScraperClientService(new KariyerNetHtmlParser);
    }

    public function test_fetch_listings_for_import_returns_lever_postings_on_success(): void
    {
        $source = LeverTestSourceFactory::create('Commencis', 'commencis', [
            'page_size' => 100,
            'max_pages' => 1,
            'max_listings' => 10,
        ]);

        $fixture = LeverTestSourceFactory::loadFixture('commencis-single.json');

        Http::fake([
            'https://api.lever.co/v0/postings/commencis*' => Http::response($fixture, 200),
        ]);

        $listings = $this->client->fetchListingsForImport($source);

        $this->assertCount(1, $listings);
        $this->assertSame('7440425c-1adf-40da-b230-281fe4a3caaf', $listings[0]['id']);
    }

    public function test_fetch_listings_for_import_falls_back_to_eu_endpoint_on_global_404(): void
    {
        $source = LeverTestSourceFactory::create('Commencis', 'commencis', [
            'page_size' => 100,
            'max_pages' => 1,
            'max_listings' => 10,
        ]);

        $fixture = LeverTestSourceFactory::loadFixture('commencis-single.json');

        Http::fake([
            'https://api.lever.co/v0/postings/commencis*' => Http::response([], 404),
            'https://api.eu.lever.co/v0/postings/commencis*' => Http::response($fixture, 200),
        ]);

        $listings = $this->client->fetchListingsForImport($source);

        $this->assertCount(1, $listings);
    }

    public function test_fetch_listings_for_import_throws_when_global_and_eu_return_404(): void
    {
        $source = LeverTestSourceFactory::create('Commencis', 'missing-board');

        Http::fake([
            'https://api.lever.co/v0/postings/missing-board*' => Http::response([], 404),
            'https://api.eu.lever.co/v0/postings/missing-board*' => Http::response([], 404),
        ]);

        $this->expectException(ScraperFetchException::class);
        $this->expectExceptionMessage('HTTP 404');

        $this->client->fetchListingsForImport($source);
    }

    public function test_fetch_listings_for_import_paginates_until_short_page(): void
    {
        $source = LeverTestSourceFactory::create('Midas', 'getmidas', [
            'page_size' => 2,
            'max_pages' => 5,
            'max_listings' => 10,
        ]);

        Http::fake([
            'https://api.lever.co/v0/postings/getmidas*' => function ($request) {
                $query = [];
                parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
                $skip = (int) ($query['skip'] ?? 0);

                if ($skip === 0) {
                    return Http::response(LeverTestSourceFactory::loadFixture('midas-page1.json'), 200);
                }

                return Http::response(LeverTestSourceFactory::loadFixture('midas-page2.json'), 200);
            },
        ]);

        $listings = $this->client->fetchListingsForImport($source);

        $this->assertCount(3, $listings);
        $this->assertSame('page1-job-001', $listings[0]['id']);
        $this->assertSame('page2-job-001', $listings[2]['id']);
    }

    public function test_fetch_listings_for_import_fails_on_empty_board(): void
    {
        $source = LeverTestSourceFactory::create('Commencis', 'commencis', [
            'page_size' => 100,
            'max_pages' => 1,
            'max_listings' => 10,
        ]);

        Http::fake([
            'https://api.lever.co/v0/postings/commencis*' => Http::response([], 200),
        ]);

        $this->expectException(ScraperFetchException::class);
        $this->expectExceptionMessage('no postings');

        $this->client->fetchListingsForImport($source);
    }

    public function test_fetch_listings_for_import_fails_on_malformed_response(): void
    {
        $source = LeverTestSourceFactory::create('Commencis', 'commencis', [
            'page_size' => 100,
            'max_pages' => 1,
            'max_listings' => 10,
        ]);

        Http::fake([
            'https://api.lever.co/v0/postings/commencis*' => Http::response(['error' => 'not an array'], 200),
        ]);

        $this->expectException(ScraperFetchException::class);
        $this->expectExceptionMessage('not a JSON array');

        $this->client->fetchListingsForImport($source);
    }
}

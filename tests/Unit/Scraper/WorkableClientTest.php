<?php

declare(strict_types=1);

namespace Tests\Unit\Scraper;

use App\Exceptions\ScraperFetchException;
use App\Services\Scraper\KariyerNetHtmlParser;
use App\Services\Scraper\ScraperClientService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\WorkableTestSourceFactory;
use Tests\TestCase;

class WorkableClientTest extends TestCase
{
    use RefreshDatabase;

    private ScraperClientService $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = new ScraperClientService(new KariyerNetHtmlParser);
    }

    public function test_fetch_listings_for_import_returns_workable_jobs_on_success(): void
    {
        $source = WorkableTestSourceFactory::create('Wingie Enuygun', 'wingieenuygun');

        Http::fake([
            'https://apply.workable.com/api/v1/widget/accounts/wingieenuygun*' => Http::response(
                WorkableTestSourceFactory::loadFixture('wingie-single.json'),
                200,
            ),
        ]);

        $listings = $this->client->fetchListingsForImport($source);

        $this->assertCount(1, $listings);
        $this->assertSame('A8A326BDEF', $listings[0]['shortcode']);
        $this->assertSame('Wingie Enuygun Group', $listings[0]['_workable_account_name']);
    }

    public function test_fetch_listings_for_import_throws_on_404(): void
    {
        $source = WorkableTestSourceFactory::create('Missing Board', 'missing-board');

        Http::fake([
            'https://apply.workable.com/api/v1/widget/accounts/missing-board*' => Http::response([], 404),
        ]);

        $this->expectException(ScraperFetchException::class);
        $this->expectExceptionMessage('HTTP 404');

        $this->client->fetchListingsForImport($source);
    }

    public function test_fetch_listings_for_import_fails_on_malformed_json(): void
    {
        $source = WorkableTestSourceFactory::create('Wingie Enuygun', 'wingieenuygun');

        Http::fake([
            'https://apply.workable.com/api/v1/widget/accounts/wingieenuygun*' => Http::response('not-json', 200, [
                'Content-Type' => 'text/plain',
            ]),
        ]);

        $this->expectException(ScraperFetchException::class);
        $this->expectExceptionMessage('missing jobs array');

        $this->client->fetchListingsForImport($source);
    }

    public function test_fetch_listings_for_import_fails_when_jobs_array_missing(): void
    {
        $source = WorkableTestSourceFactory::create('Malformed Board', 'malformed-board');

        Http::fake([
            'https://apply.workable.com/api/v1/widget/accounts/malformed-board*' => Http::response(
                WorkableTestSourceFactory::loadFixture('missing-jobs.json'),
                200,
            ),
        ]);

        $this->expectException(ScraperFetchException::class);
        $this->expectExceptionMessage('missing jobs array');

        $this->client->fetchListingsForImport($source);
    }

    public function test_fetch_listings_for_import_fails_on_empty_board(): void
    {
        $source = WorkableTestSourceFactory::create('Empty Board', 'empty-board');

        Http::fake([
            'https://apply.workable.com/api/v1/widget/accounts/empty-board*' => Http::response(
                WorkableTestSourceFactory::loadFixture('empty-board.json'),
                200,
            ),
        ]);

        $this->expectException(ScraperFetchException::class);
        $this->expectExceptionMessage('no postings');

        $this->client->fetchListingsForImport($source);
    }

    public function test_fetch_listings_for_import_deduplicates_duplicate_shortcodes(): void
    {
        $source = WorkableTestSourceFactory::create('Wingie Enuygun', 'wingieenuygun');

        Http::fake([
            'https://apply.workable.com/api/v1/widget/accounts/wingieenuygun*' => Http::response(
                WorkableTestSourceFactory::loadFixture('duplicate-shortcode.json'),
                200,
            ),
        ]);

        $listings = $this->client->fetchListingsForImport($source);

        $this->assertCount(1, $listings);
        $this->assertSame('1FF5A9AA2B', $listings[0]['shortcode']);
        $this->assertSame('Duplicate Role Updated', $listings[0]['title']);
    }
}

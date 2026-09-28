<?php

declare(strict_types=1);

namespace Tests\Unit\Scraper;

use App\Exceptions\ScraperFetchException;
use App\Services\Scraper\KariyerNetHtmlParser;
use App\Services\Scraper\ScraperClientService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\AshbyTestSourceFactory;
use Tests\TestCase;

class AshbyClientTest extends TestCase
{
    use RefreshDatabase;

    private ScraperClientService $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = new ScraperClientService(new KariyerNetHtmlParser);
    }

    public function test_fetch_listings_for_import_returns_ashby_jobs_on_success(): void
    {
        $source = AshbyTestSourceFactory::create('Codeway', 'codeway');

        Http::fake([
            'https://api.ashbyhq.com/posting-api/job-board/codeway*' => Http::response(
                AshbyTestSourceFactory::loadFixture('codeway-single.json'),
                200,
            ),
        ]);

        $listings = $this->client->fetchListingsForImport($source);

        $this->assertCount(1, $listings);
        $this->assertSame('3f5c2489-d889-4783-b846-70f04d274094', $listings[0]['id']);
        $this->assertSame('Growth Manager, Learna', $listings[0]['title']);
    }

    public function test_fetch_listings_for_import_throws_on_404(): void
    {
        $source = AshbyTestSourceFactory::create('Missing Board', 'missing-board');

        Http::fake([
            'https://api.ashbyhq.com/posting-api/job-board/missing-board*' => Http::response([], 404),
        ]);

        $this->expectException(ScraperFetchException::class);
        $this->expectExceptionMessage('HTTP 404');

        $this->client->fetchListingsForImport($source);
    }

    public function test_fetch_listings_for_import_fails_on_malformed_json(): void
    {
        $source = AshbyTestSourceFactory::create('Codeway', 'codeway');

        Http::fake([
            'https://api.ashbyhq.com/posting-api/job-board/codeway*' => Http::response('not-json', 200, [
                'Content-Type' => 'text/plain',
            ]),
        ]);

        $this->expectException(ScraperFetchException::class);
        $this->expectExceptionMessage('missing jobs array');

        $this->client->fetchListingsForImport($source);
    }

    public function test_fetch_listings_for_import_fails_when_jobs_array_missing(): void
    {
        $source = AshbyTestSourceFactory::create('Malformed Board', 'malformed-board');

        Http::fake([
            'https://api.ashbyhq.com/posting-api/job-board/malformed-board*' => Http::response(
                AshbyTestSourceFactory::loadFixture('missing-jobs.json'),
                200,
            ),
        ]);

        $this->expectException(ScraperFetchException::class);
        $this->expectExceptionMessage('missing jobs array');

        $this->client->fetchListingsForImport($source);
    }

    public function test_fetch_listings_for_import_fails_on_empty_board(): void
    {
        $source = AshbyTestSourceFactory::create('Empty Board', 'empty-board');

        Http::fake([
            'https://api.ashbyhq.com/posting-api/job-board/empty-board*' => Http::response(
                AshbyTestSourceFactory::loadFixture('empty-board.json'),
                200,
            ),
        ]);

        $this->expectException(ScraperFetchException::class);
        $this->expectExceptionMessage('no postings');

        $this->client->fetchListingsForImport($source);
    }

    public function test_fetch_listings_for_import_deduplicates_duplicate_ids(): void
    {
        $source = AshbyTestSourceFactory::create('Codeway', 'codeway');

        Http::fake([
            'https://api.ashbyhq.com/posting-api/job-board/codeway*' => Http::response(
                AshbyTestSourceFactory::loadFixture('duplicate-id.json'),
                200,
            ),
        ]);

        $listings = $this->client->fetchListingsForImport($source);

        $this->assertCount(1, $listings);
        $this->assertSame('aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee', $listings[0]['id']);
        $this->assertSame('Duplicate Role Updated', $listings[0]['title']);
    }
}

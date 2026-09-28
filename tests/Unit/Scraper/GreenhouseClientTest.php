<?php

declare(strict_types=1);

namespace Tests\Unit\Scraper;

use App\Exceptions\ScraperFetchException;
use App\Services\Scraper\KariyerNetHtmlParser;
use App\Services\Scraper\ScraperClientService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\GreenhouseTestSourceFactory;
use Tests\TestCase;

class GreenhouseClientTest extends TestCase
{
    use RefreshDatabase;

    private ScraperClientService $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = new ScraperClientService(new KariyerNetHtmlParser);
    }

    public function test_fetch_listings_for_import_returns_greenhouse_jobs_on_success(): void
    {
        $source = GreenhouseTestSourceFactory::create('Good Job Games', 'goodjobgames');

        Http::fake([
            'https://boards-api.greenhouse.io/v1/boards/goodjobgames/jobs*' => Http::response(
                GreenhouseTestSourceFactory::loadFixture('goodjobgames-single.json'),
                200,
            ),
        ]);

        $listings = $this->client->fetchListingsForImport($source);

        $this->assertCount(1, $listings);
        $this->assertSame(4968083003, $listings[0]['id']);
        $this->assertSame('2D Animator, Studio', $listings[0]['title']);
    }

    public function test_fetch_listings_for_import_throws_on_404(): void
    {
        $source = GreenhouseTestSourceFactory::create('Missing Board', 'missing-board');

        Http::fake([
            'https://boards-api.greenhouse.io/v1/boards/missing-board/jobs*' => Http::response([], 404),
        ]);

        $this->expectException(ScraperFetchException::class);
        $this->expectExceptionMessage('HTTP 404');

        $this->client->fetchListingsForImport($source);
    }

    public function test_fetch_listings_for_import_fails_on_malformed_json(): void
    {
        $source = GreenhouseTestSourceFactory::create('Good Job Games', 'goodjobgames');

        Http::fake([
            'https://boards-api.greenhouse.io/v1/boards/goodjobgames/jobs*' => Http::response('not-json', 200, [
                'Content-Type' => 'text/plain',
            ]),
        ]);

        $this->expectException(ScraperFetchException::class);
        $this->expectExceptionMessage('missing jobs array');

        $this->client->fetchListingsForImport($source);
    }

    public function test_fetch_listings_for_import_fails_when_jobs_array_missing(): void
    {
        $source = GreenhouseTestSourceFactory::create('Malformed Board', 'malformed-board');

        Http::fake([
            'https://boards-api.greenhouse.io/v1/boards/malformed-board/jobs*' => Http::response(
                GreenhouseTestSourceFactory::loadFixture('missing-jobs.json'),
                200,
            ),
        ]);

        $this->expectException(ScraperFetchException::class);
        $this->expectExceptionMessage('missing jobs array');

        $this->client->fetchListingsForImport($source);
    }

    public function test_fetch_listings_for_import_fails_on_empty_board(): void
    {
        $source = GreenhouseTestSourceFactory::create('Empty Board', 'empty-board');

        Http::fake([
            'https://boards-api.greenhouse.io/v1/boards/empty-board/jobs*' => Http::response(
                GreenhouseTestSourceFactory::loadFixture('empty-board.json'),
                200,
            ),
        ]);

        $this->expectException(ScraperFetchException::class);
        $this->expectExceptionMessage('no postings');

        $this->client->fetchListingsForImport($source);
    }

    public function test_fetch_listings_for_import_deduplicates_duplicate_ids(): void
    {
        $source = GreenhouseTestSourceFactory::create('Good Job Games', 'goodjobgames');

        Http::fake([
            'https://boards-api.greenhouse.io/v1/boards/goodjobgames/jobs*' => Http::response(
                GreenhouseTestSourceFactory::loadFixture('duplicate-id.json'),
                200,
            ),
        ]);

        $listings = $this->client->fetchListingsForImport($source);

        $this->assertCount(1, $listings);
        $this->assertSame(1111111111, $listings[0]['id']);
        $this->assertSame('Duplicate Role Updated', $listings[0]['title']);
    }
}

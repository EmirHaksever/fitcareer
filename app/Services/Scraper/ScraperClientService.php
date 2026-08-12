<?php

declare(strict_types=1);

namespace App\Services\Scraper;

use App\Enums\JobSourceType;
use App\Exceptions\ScraperFetchException;
use App\Models\JobSource;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ScraperClientService
{
    public function __construct(
        private readonly KariyerNetHtmlParser $kariyerNetHtmlParser,
    ) {}

    /**
     * Legacy fetch path used by jobs:test-ingestion (limit capped at 10).
     *
     * @return list<array<string, mixed>>
     */
    public function fetchListings(JobSource $source): array
    {
        return $this->fetchListingsWithLimits($source, ScraperFetchLimits::legacy($source->config ?? []));
    }

    /**
     * Production import path with pagination and configurable limits.
     *
     * @return list<array<string, mixed>>
     */
    public function fetchListingsForImport(JobSource $source): array
    {
        return $this->fetchListingsWithLimits($source, ScraperFetchLimits::production($source->config ?? []));
    }

    /**
     * @return array<string, mixed>
     */
    public function fetchListing(JobSource $source, string $externalId): array
    {
        foreach ($this->fetchListings($source) as $listing) {
            if ((string) ($listing['id'] ?? $listing['external_id'] ?? '') === $externalId) {
                return $listing;
            }
        }

        throw ScraperFetchException::invalidPayload('Listing '.$externalId.' not found in current fetch batch.');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchListingsWithLimits(JobSource $source, ScraperFetchLimits $limits): array
    {
        return match ($source->type) {
            JobSourceType::ApiIntegration => $this->fetchFromApiIntegration($source, $limits),
            JobSourceType::Scraper => $this->fetchFromScraper($source, $limits),
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchFromScraper(JobSource $source, ScraperFetchLimits $limits): array
    {
        $provider = (string) ($source->config['provider'] ?? '');

        return match ($provider) {
            'kariyer-net' => $this->fetchKariyerNetListings($source, $limits),
            default => throw ScraperFetchException::unsupportedProvider($provider !== '' ? $provider : '(empty)'),
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchFromApiIntegration(JobSource $source, ScraperFetchLimits $limits): array
    {
        $provider = (string) ($source->config['provider'] ?? '');

        return match ($provider) {
            'remotive' => $this->fetchRemotiveListings($source, $limits),
            default => throw ScraperFetchException::unsupportedProvider($provider !== '' ? $provider : '(empty)'),
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchKariyerNetListings(JobSource $source, ScraperFetchLimits $limits): array
    {
        $listingUrl = (string) ($source->config['listing_url'] ?? $source->base_url ?? '');

        if (trim($listingUrl) === '') {
            throw ScraperFetchException::missingConfiguration('listing_url is required for Kariyer.net source.');
        }

        $this->kariyerNetHtmlParser->assertAllowedListingUrl($listingUrl);

        $detailUrls = [];
        $seenUrls = [];

        for ($page = 1; $page <= $limits->maxPages; $page++) {
            if (count($detailUrls) >= $limits->maxListings) {
                break;
            }

            $pageUrl = $this->buildKariyerListingUrl($listingUrl, $page);
            $listingHtml = $this->fetchHtml($pageUrl, $source->id, 'Kariyer.net listing page '.$page);
            $pageUrls = $this->kariyerNetHtmlParser->extractListingUrls($listingHtml);

            if ($pageUrls === []) {
                break;
            }

            $newUrls = [];

            foreach ($pageUrls as $pageUrlItem) {
                if (! isset($seenUrls[$pageUrlItem])) {
                    $seenUrls[$pageUrlItem] = true;
                    $newUrls[] = $pageUrlItem;
                }
            }

            if ($newUrls === []) {
                break;
            }

            $detailUrls = array_merge($detailUrls, $newUrls);

            if (count($pageUrls) < $limits->pageSize) {
                break;
            }
        }

        $detailUrls = array_slice($detailUrls, 0, $limits->maxListings);

        if ($detailUrls === []) {
            throw ScraperFetchException::invalidPayload('Kariyer.net listing page returned no job URLs.');
        }

        $listings = [];
        $detailFailures = 0;

        foreach ($detailUrls as $detailUrl) {
            try {
                $detailHtml = $this->fetchHtml($detailUrl, $source->id, 'Kariyer.net detail page');
                $listings[] = $this->kariyerNetHtmlParser->parseDetailPage($detailHtml, $detailUrl);
            } catch (ScraperFetchException $exception) {
                $detailFailures++;

                Log::warning('Kariyer.net detail fetch skipped.', [
                    'source_id' => $source->id,
                    'url' => $detailUrl,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        if ($listings === []) {
            throw ScraperFetchException::invalidPayload('Kariyer.net detail pages returned no parseable listings.');
        }

        Log::info('Kariyer.net listings fetched.', [
            'source_id' => $source->id,
            'listing_url' => $listingUrl,
            'pages_scanned' => min($limits->maxPages, max(1, (int) ceil(count($detailUrls) / max(1, $limits->pageSize)))),
            'fetched' => count($listings),
            'detail_failures' => $detailFailures,
        ]);

        return $listings;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchRemotiveListings(JobSource $source, ScraperFetchLimits $limits): array
    {
        $endpoint = $source->base_url;

        if ($endpoint === null || trim($endpoint) === '') {
            throw ScraperFetchException::missingConfiguration('base_url is required for Remotive source.');
        }

        $limit = $limits->maxListings;
        $startedAt = microtime(true);

        try {
            $response = Http::timeout((int) config('scraper.timeout'))
                ->connectTimeout((int) config('scraper.connect_timeout'))
                ->withHeaders([
                    'User-Agent' => (string) config('scraper.user_agent'),
                    'Accept' => 'application/json',
                ])
                ->get($endpoint, [
                    'limit' => $limit,
                ]);
        } catch (ConnectionException $exception) {
            Log::warning('Remotive fetch transport failure.', [
                'source_id' => $source->id,
                'message' => $exception->getMessage(),
            ]);

            throw ScraperFetchException::httpFailure(0, $endpoint);
        }

        $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);

        if ($response->failed()) {
            Log::warning('Remotive fetch failed.', [
                'source_id' => $source->id,
                'status' => $response->status(),
                'latency_ms' => $latencyMs,
            ]);

            throw ScraperFetchException::httpFailure($response->status(), $endpoint);
        }

        $payload = $response->json();

        if (! is_array($payload) || ! isset($payload['jobs']) || ! is_array($payload['jobs'])) {
            throw ScraperFetchException::invalidPayload('Remotive response missing jobs array.');
        }

        $listings = array_values(array_filter(
            $payload['jobs'],
            static fn (mixed $item): bool => is_array($item) && isset($item['id'], $item['title'], $item['url']),
        ));

        Log::info('Remotive listings fetched.', [
            'source_id' => $source->id,
            'requested_limit' => $limit,
            'fetched' => count($listings),
            'latency_ms' => $latencyMs,
        ]);

        return array_slice($listings, 0, $limit);
    }

    private function buildKariyerListingUrl(string $baseUrl, int $page): string
    {
        if ($page <= 1) {
            return $baseUrl;
        }

        $separator = str_contains($baseUrl, '?') ? '&' : '?';

        return $baseUrl.$separator.'cp='.$page;
    }

    private function fetchHtml(string $url, int $sourceId, string $context): string
    {
        $startedAt = microtime(true);

        try {
            $response = Http::timeout((int) config('scraper.timeout'))
                ->connectTimeout((int) config('scraper.connect_timeout'))
                ->withHeaders([
                    'User-Agent' => (string) config('scraper.user_agent'),
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.8',
                    'Accept-Language' => (string) config('scraper.accept_language'),
                ])
                ->get($url);
        } catch (ConnectionException $exception) {
            Log::warning('Scraper HTML fetch transport failure.', [
                'source_id' => $sourceId,
                'context' => $context,
                'url' => $url,
                'message' => $exception->getMessage(),
            ]);

            throw ScraperFetchException::httpFailure(0, $url);
        }

        $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);

        if ($response->failed()) {
            Log::warning('Scraper HTML fetch failed.', [
                'source_id' => $sourceId,
                'context' => $context,
                'url' => $url,
                'status' => $response->status(),
                'latency_ms' => $latencyMs,
            ]);

            throw ScraperFetchException::httpFailure($response->status(), $url);
        }

        return (string) $response->body();
    }
}

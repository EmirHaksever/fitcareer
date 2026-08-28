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
            if ((string) ($listing['id'] ?? $listing['external_id'] ?? $listing['shortcode'] ?? '') === $externalId) {
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
            'lever' => $this->fetchLeverListings($source, $limits),
            'workable' => $this->fetchWorkableListings($source, $limits),
            'ashby' => $this->fetchAshbyListings($source, $limits),
            'greenhouse' => $this->fetchGreenhouseListings($source, $limits),
            'recruitee' => $this->fetchRecruiteeListings($source, $limits),
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

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchLeverListings(JobSource $source, ScraperFetchLimits $limits): array
    {
        $siteSlug = trim((string) ($source->config['site_slug'] ?? ''));

        if ($siteSlug === '') {
            throw ScraperFetchException::missingConfiguration('site_slug is required for Lever source.');
        }

        $configuredRegion = strtolower(trim((string) ($source->config['region'] ?? 'global')));
        $globalBase = 'https://api.lever.co/v0/postings/'.$siteSlug;
        $euBase = 'https://api.eu.lever.co/v0/postings/'.$siteSlug;

        $allListings = [];
        $resolvedBaseUrl = null;

        for ($page = 0; $page < $limits->maxPages; $page++) {
            if (count($allListings) >= $limits->maxListings) {
                break;
            }

            $skip = $page * $limits->pageSize;
            $limit = min($limits->pageSize, $limits->maxListings - count($allListings));

            if ($page === 0) {
                $pageResult = $this->fetchLeverFirstPage($source, $globalBase, $euBase, $configuredRegion, $skip, $limit);
                $resolvedBaseUrl = $pageResult['base_url'];
                $pageListings = $pageResult['listings'];
            } else {
                $pageListings = $this->fetchLeverPage($source, (string) $resolvedBaseUrl, $skip, $limit);
            }

            if ($pageListings === []) {
                break;
            }

            $allListings = array_merge($allListings, $pageListings);

            if (count($pageListings) < $limit) {
                break;
            }
        }

        if ($allListings === []) {
            throw ScraperFetchException::invalidPayload('Lever board returned no postings.');
        }

        Log::info('Lever listings fetched.', [
            'source_id' => $source->id,
            'site_slug' => $siteSlug,
            'base_url' => $resolvedBaseUrl,
            'fetched' => count($allListings),
        ]);

        return array_slice($allListings, 0, $limits->maxListings);
    }

    /**
     * @return array{base_url: string, listings: list<array<string, mixed>>}
     */
    private function fetchLeverFirstPage(
        JobSource $source,
        string $globalBase,
        string $euBase,
        string $configuredRegion,
        int $skip,
        int $limit,
    ): array {
        if ($configuredRegion === 'eu') {
            return [
                'base_url' => $euBase,
                'listings' => $this->fetchLeverPage($source, $euBase, $skip, $limit),
            ];
        }

        try {
            return [
                'base_url' => $globalBase,
                'listings' => $this->fetchLeverPage($source, $globalBase, $skip, $limit),
            ];
        } catch (ScraperFetchException $exception) {
            if (! str_contains($exception->getMessage(), 'HTTP 404')) {
                throw $exception;
            }
        }

        return [
            'base_url' => $euBase,
            'listings' => $this->fetchLeverPage($source, $euBase, $skip, $limit),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchLeverPage(JobSource $source, string $baseUrl, int $skip, int $limit): array
    {
        $startedAt = microtime(true);

        try {
            $response = Http::timeout((int) config('scraper.timeout'))
                ->connectTimeout((int) config('scraper.connect_timeout'))
                ->withHeaders([
                    'User-Agent' => (string) config('scraper.user_agent'),
                    'Accept' => 'application/json',
                ])
                ->get($baseUrl, [
                    'mode' => 'json',
                    'skip' => $skip,
                    'limit' => $limit,
                ]);
        } catch (ConnectionException $exception) {
            Log::warning('Lever fetch transport failure.', [
                'source_id' => $source->id,
                'message' => $exception->getMessage(),
            ]);

            throw ScraperFetchException::httpFailure(0, $baseUrl);
        }

        $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);

        if ($response->failed()) {
            Log::warning('Lever fetch failed.', [
                'source_id' => $source->id,
                'status' => $response->status(),
                'latency_ms' => $latencyMs,
            ]);

            throw ScraperFetchException::httpFailure($response->status(), $baseUrl);
        }

        $payload = $response->json();

        if (! is_array($payload) || ! array_is_list($payload)) {
            throw ScraperFetchException::invalidPayload('Lever response is not a JSON array.');
        }

        return array_values(array_filter(
            $payload,
            static fn (mixed $item): bool => is_array($item) && isset($item['id'], $item['text']),
        ));
    }

    /**
     * Workable integration uses the public widget endpoint, not the authenticated SPI v3 Developer API.
     *
     * @see https://workable.readme.io/ SPI v3 requires a bearer token and is not used here.
     *
     * @return list<array<string, mixed>>
     */
    private function fetchWorkableListings(JobSource $source, ScraperFetchLimits $limits): array
    {
        $siteSlug = trim((string) ($source->config['site_slug'] ?? ''));

        if ($siteSlug === '') {
            throw ScraperFetchException::missingConfiguration('site_slug is required for Workable source.');
        }

        $endpoint = 'https://apply.workable.com/api/v1/widget/accounts/'.$siteSlug;
        $startedAt = microtime(true);

        try {
            $response = Http::timeout((int) config('scraper.timeout'))
                ->connectTimeout((int) config('scraper.connect_timeout'))
                ->withHeaders([
                    'User-Agent' => (string) config('scraper.user_agent'),
                    'Accept' => 'application/json',
                ])
                ->get($endpoint, [
                    'details' => 'true',
                ]);
        } catch (ConnectionException $exception) {
            Log::warning('Workable fetch transport failure.', [
                'source_id' => $source->id,
                'message' => $exception->getMessage(),
            ]);

            throw ScraperFetchException::httpFailure(0, $endpoint);
        }

        $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);

        if ($response->failed()) {
            Log::warning('Workable fetch failed.', [
                'source_id' => $source->id,
                'status' => $response->status(),
                'latency_ms' => $latencyMs,
            ]);

            throw ScraperFetchException::httpFailure($response->status(), $endpoint);
        }

        $payload = $response->json();

        if (! is_array($payload) || ! isset($payload['jobs']) || ! is_array($payload['jobs'])) {
            throw ScraperFetchException::invalidPayload('Workable response missing jobs array.');
        }

        $accountName = is_string($payload['name'] ?? null) ? trim($payload['name']) : '';

        $listingsByShortcode = [];

        foreach ($payload['jobs'] as $job) {
            if (! is_array($job) || ! isset($job['shortcode'], $job['title'])) {
                continue;
            }

            $listingsByShortcode[(string) $job['shortcode']] = [
                ...$job,
                '_workable_account_name' => $accountName !== '' ? $accountName : null,
            ];
        }

        $listings = array_values($listingsByShortcode);

        if ($listings === []) {
            throw ScraperFetchException::invalidPayload('Workable board returned no postings.');
        }

        Log::info('Workable listings fetched.', [
            'source_id' => $source->id,
            'site_slug' => $siteSlug,
            'fetched' => count($listings),
            'latency_ms' => $latencyMs,
        ]);

        return array_slice($listings, 0, $limits->maxListings);
    }

    /**
     * Ashby public Job Posting API — no authentication required.
     *
     * @see https://developers.ashbyhq.com/docs/public-job-posting-api
     *
     * @return list<array<string, mixed>>
     */
    private function fetchAshbyListings(JobSource $source, ScraperFetchLimits $limits): array
    {
        $siteSlug = trim((string) ($source->config['site_slug'] ?? ''));

        if ($siteSlug === '') {
            throw ScraperFetchException::missingConfiguration('site_slug is required for Ashby source.');
        }

        $endpoint = 'https://api.ashbyhq.com/posting-api/job-board/'.$siteSlug;
        $startedAt = microtime(true);

        try {
            $response = Http::timeout((int) config('scraper.timeout'))
                ->connectTimeout((int) config('scraper.connect_timeout'))
                ->withHeaders([
                    'User-Agent' => (string) config('scraper.user_agent'),
                    'Accept' => 'application/json',
                ])
                ->get($endpoint);
        } catch (ConnectionException $exception) {
            Log::warning('Ashby fetch transport failure.', [
                'source_id' => $source->id,
                'message' => $exception->getMessage(),
            ]);

            throw ScraperFetchException::httpFailure(0, $endpoint);
        }

        $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);

        if ($response->failed()) {
            Log::warning('Ashby fetch failed.', [
                'source_id' => $source->id,
                'status' => $response->status(),
                'latency_ms' => $latencyMs,
            ]);

            throw ScraperFetchException::httpFailure($response->status(), $endpoint);
        }

        $payload = $response->json();

        if (! is_array($payload) || ! isset($payload['jobs']) || ! is_array($payload['jobs'])) {
            throw ScraperFetchException::invalidPayload('Ashby response missing jobs array.');
        }

        $listingsById = [];

        foreach ($payload['jobs'] as $job) {
            if (! is_array($job) || ! isset($job['id'], $job['title'])) {
                continue;
            }

            if (($job['isListed'] ?? true) === false) {
                continue;
            }

            $listingsById[(string) $job['id']] = $job;
        }

        $listings = array_values($listingsById);

        if ($listings === []) {
            throw ScraperFetchException::invalidPayload('Ashby board returned no postings.');
        }

        Log::info('Ashby listings fetched.', [
            'source_id' => $source->id,
            'site_slug' => $siteSlug,
            'fetched' => count($listings),
            'latency_ms' => $latencyMs,
        ]);

        return array_slice($listings, 0, $limits->maxListings);
    }

    /**
     * Greenhouse public Job Board API — no authentication required.
     *
     * @see https://developers.greenhouse.io/job-board.html
     *
     * @return list<array<string, mixed>>
     */
    private function fetchGreenhouseListings(JobSource $source, ScraperFetchLimits $limits): array
    {
        $siteSlug = trim((string) ($source->config['site_slug'] ?? ''));

        if ($siteSlug === '') {
            throw ScraperFetchException::missingConfiguration('site_slug is required for Greenhouse source.');
        }

        $endpoint = 'https://boards-api.greenhouse.io/v1/boards/'.$siteSlug.'/jobs';
        $startedAt = microtime(true);

        try {
            $response = Http::timeout((int) config('scraper.timeout'))
                ->connectTimeout((int) config('scraper.connect_timeout'))
                ->withHeaders([
                    'User-Agent' => (string) config('scraper.user_agent'),
                    'Accept' => 'application/json',
                ])
                ->get($endpoint, [
                    'content' => 'true',
                ]);
        } catch (ConnectionException $exception) {
            Log::warning('Greenhouse fetch transport failure.', [
                'source_id' => $source->id,
                'message' => $exception->getMessage(),
            ]);

            throw ScraperFetchException::httpFailure(0, $endpoint);
        }

        $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);

        if ($response->failed()) {
            Log::warning('Greenhouse fetch failed.', [
                'source_id' => $source->id,
                'status' => $response->status(),
                'latency_ms' => $latencyMs,
            ]);

            throw ScraperFetchException::httpFailure($response->status(), $endpoint);
        }

        $payload = $response->json();

        if (! is_array($payload) || ! isset($payload['jobs']) || ! is_array($payload['jobs'])) {
            throw ScraperFetchException::invalidPayload('Greenhouse response missing jobs array.');
        }

        $listingsById = [];

        foreach ($payload['jobs'] as $job) {
            if (! is_array($job) || ! isset($job['id'], $job['title'])) {
                continue;
            }

            $listingsById[(string) $job['id']] = $job;
        }

        $listings = array_values($listingsById);

        if ($listings === []) {
            throw ScraperFetchException::invalidPayload('Greenhouse board returned no postings.');
        }

        Log::info('Greenhouse listings fetched.', [
            'source_id' => $source->id,
            'site_slug' => $siteSlug,
            'fetched' => count($listings),
            'latency_ms' => $latencyMs,
        ]);

        return array_slice($listings, 0, $limits->maxListings);
    }

    /**
     * Recruitee Careers Site API — no authentication required.
     *
     * @see https://docs.recruitee.com/reference/intro-to-careers-site-api
     *
     * @return list<array<string, mixed>>
     */
    private function fetchRecruiteeListings(JobSource $source, ScraperFetchLimits $limits): array
    {
        $siteSlug = trim((string) ($source->config['site_slug'] ?? ''));

        if ($siteSlug === '') {
            throw ScraperFetchException::missingConfiguration('site_slug is required for Recruitee source.');
        }

        $endpoint = 'https://'.$siteSlug.'.recruitee.com/api/offers';
        $startedAt = microtime(true);

        try {
            $response = Http::timeout((int) config('scraper.timeout'))
                ->connectTimeout((int) config('scraper.connect_timeout'))
                ->withHeaders([
                    'User-Agent' => (string) config('scraper.user_agent'),
                    'Accept' => 'application/json',
                ])
                ->get($endpoint);
        } catch (ConnectionException $exception) {
            Log::warning('Recruitee fetch transport failure.', [
                'source_id' => $source->id,
                'message' => $exception->getMessage(),
            ]);

            throw ScraperFetchException::httpFailure(0, $endpoint);
        }

        $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);

        if ($response->failed()) {
            Log::warning('Recruitee fetch failed.', [
                'source_id' => $source->id,
                'status' => $response->status(),
                'latency_ms' => $latencyMs,
            ]);

            throw ScraperFetchException::httpFailure($response->status(), $endpoint);
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw ScraperFetchException::invalidPayload('Recruitee response is not valid JSON.');
        }

        $offers = $payload['offers'] ?? $payload;

        if (! is_array($offers)) {
            throw ScraperFetchException::invalidPayload('Recruitee response missing offers array.');
        }

        $listingsById = [];

        foreach ($offers as $offer) {
            if (! is_array($offer) || ! isset($offer['id'], $offer['title'])) {
                continue;
            }

            $status = strtolower(trim((string) ($offer['status'] ?? 'published')));

            if ($status !== '' && $status !== 'published') {
                continue;
            }

            $listingsById[(string) $offer['id']] = $offer;
        }

        $listings = array_values($listingsById);

        if ($listings === []) {
            throw ScraperFetchException::invalidPayload('Recruitee board returned no postings.');
        }

        Log::info('Recruitee listings fetched.', [
            'source_id' => $source->id,
            'site_slug' => $siteSlug,
            'fetched' => count($listings),
            'latency_ms' => $latencyMs,
        ]);

        return array_slice($listings, 0, $limits->maxListings);
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

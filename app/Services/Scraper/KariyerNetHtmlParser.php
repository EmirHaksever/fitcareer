<?php

declare(strict_types=1);

namespace App\Services\Scraper;

use App\Exceptions\ScraperFetchException;

class KariyerNetHtmlParser
{
    private const DISALLOWED_PATH_FRAGMENTS = [
        '/filtre/',
        '/aday/giris',
        '/profil',
    ];

    /**
     * @return list<string>
     */
    public function extractListingUrls(string $html): array
    {
        preg_match_all('#https?://www\.kariyer\.net/is-ilani/[a-z0-9\-]+-\d+#i', $html, $absolute);
        $links = array_values(array_unique($absolute[0] ?? []));

        if ($links === []) {
            preg_match_all('#href="(/is-ilani/[^"]+)"#i', $html, $relative);

            foreach (array_unique($relative[1] ?? []) as $path) {
                if (preg_match('#/is-ilani/.+-\d+#', $path)) {
                    $links[] = 'https://www.kariyer.net'.$path;
                }
            }
        }

        return array_values(array_filter(
            array_unique($links),
            fn (string $url): bool => $this->isAllowedPublicUrl($url),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function parseDetailPage(string $html, string $url): array
    {
        if (! $this->isAllowedPublicUrl($url)) {
            throw ScraperFetchException::invalidPayload('Disallowed Kariyer.net URL: '.$url);
        }

        $externalId = $this->extractExternalIdFromUrl($url);

        if ($externalId === null) {
            throw ScraperFetchException::invalidPayload('Kariyer.net detail URL missing external id suffix.');
        }

        $title = $this->cleanText($this->firstMatch($html, [
            '#data-test="job-title"[^>]*>\s*([^<]+)#s',
        ]));

        if ($title === null || $title === '') {
            throw ScraperFetchException::invalidPayload('Kariyer.net detail page missing title.');
        }

        $descriptionHtml = $this->firstMatch($html, [
            '#data-test="qualifications-and-job-description"[^>]*>(.*?)</div>#is',
        ]);

        $description = $this->cleanText($descriptionHtml);

        if ($description === null || $description === '') {
            throw ScraperFetchException::invalidPayload('Kariyer.net detail page missing description.');
        }

        return [
            'external_id' => $externalId,
            'external_url' => $url,
            'title' => $title,
            'company' => $this->cleanText($this->firstMatch($html, [
                '#data-test="company-name"[^>]*>\s*([^<]+)#s',
            ])),
            'location' => $this->cleanText($this->firstMatch($html, [
                '#data-test="company-location"[^>]*>\s*([^<]+)#s',
            ])),
            'employment_type_raw' => $this->firstMatch($html, ['#workType="([^"]+)"#']),
            'experience_level_raw' => $this->firstMatch($html, ['#experienceLevel="([^"]+)"#']),
            'work_model_raw' => $this->cleanText($this->firstMatch($html, [
                '#data-test="job-feature-item"[^>]*>\s*([^<]+)#s',
            ])),
            'published_date_raw' => $this->firstMatch($html, [
                '#lastPublishDate="([^"]+)"#',
                '#lastPublishDate\s*:\s*([0-9\.]+)#',
            ]),
            'closing_date_raw' => $this->firstMatch($html, [
                '#closingDate="([^"]+)"#',
                '#closingDate\s*:\s*([0-9\.]+)#',
            ]),
            'description' => $description,
        ];
    }

    public function assertAllowedListingUrl(string $url): void
    {
        if (! $this->isAllowedPublicUrl($url)) {
            throw ScraperFetchException::invalidPayload('Disallowed Kariyer.net listing URL: '.$url);
        }

        if (! str_contains($url, '/is-ilanlari')) {
            throw ScraperFetchException::invalidPayload('Kariyer.net listing URL must target /is-ilanlari.');
        }
    }

    public function isAllowedPublicUrl(string $url): bool
    {
        foreach (self::DISALLOWED_PATH_FRAGMENTS as $fragment) {
            if (str_contains($url, $fragment)) {
                return false;
            }
        }

        return str_contains($url, 'kariyer.net');
    }

    public function extractExternalIdFromUrl(string $url): ?string
    {
        if (preg_match('#/is-ilani/[^/]+-(\d+)#', $url, $match)) {
            return $match[1];
        }

        return null;
    }

    /**
     * @param  list<string>  $patterns
     */
    private function firstMatch(string $html, array $patterns): ?string
    {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $match)) {
                return trim($match[1]);
            }
        }

        return null;
    }

    private function cleanText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = html_entity_decode(strip_tags($value));
        $normalized = trim(preg_replace('/\s+/u', ' ', $value) ?? '');

        return $normalized === '' ? null : $normalized;
    }
}

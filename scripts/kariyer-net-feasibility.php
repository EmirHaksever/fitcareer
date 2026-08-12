<?php

declare(strict_types=1);

/**
 * One-off feasibility probe for Kariyer.net public pages.
 * Not wired to production ingestion.
 */

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Http;

function fetchPage(string $url): array
{
    $response = Http::withHeaders([
        'User-Agent' => 'FitCareer-Feasibility-Test/1.0 (+https://fitcareer.local)',
        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'Accept-Language' => 'tr-TR,tr;q=0.9,en-US;q=0.8,en;q=0.7',
    ])->timeout(25)->get($url);

    $body = (string) $response->body();

    return [
        'url' => $url,
        'status' => $response->status(),
        'effective_url' => (string) ($response->effectiveUri() ?? $url),
        'content_type' => $response->header('Content-Type'),
        'body' => $body,
        'body_len' => strlen($body),
    ];
}

function findJobLinks(string $html): array
{
    preg_match_all('#https?://www\.kariyer\.net/is-ilani/[a-z0-9\-]+-\d+#i', $html, $absolute);
    $links = array_values(array_unique($absolute[0] ?? []));

    if ($links !== []) {
        return $links;
    }

    preg_match_all('#href="(/is-ilani/[^"]+)"#i', $html, $relative);

    foreach (array_unique($relative[1] ?? []) as $path) {
        if (preg_match('#/is-ilani/.+-\d+#', $path)) {
            $links[] = 'https://www.kariyer.net'.$path;
        }
    }

    return array_values(array_unique($links));
}

function extractJsonLd(string $html): array
{
    preg_match_all('#<script[^>]*type="application/ld\+json"[^>]*>(.*?)</script>#is', $html, $matches);
    $blocks = [];

    foreach ($matches[1] ?? [] as $json) {
        $decoded = json_decode(html_entity_decode(trim($json)), true);
        if (is_array($decoded)) {
            $blocks[] = $decoded;
        }
    }

    return $blocks;
}

function firstMatch(string $html, array $patterns): ?string
{
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $html, $match)) {
            return trim($match[1]);
        }
    }

    return null;
}

function cleanText(?string $value): ?string
{
    if ($value === null) {
        return null;
    }

    $value = html_entity_decode(strip_tags($value));

    return trim(preg_replace('/\s+/u', ' ', $value) ?? '') ?: null;
}

function parseDetail(string $html, string $url): array
{
    $jsonLd = extractJsonLd($html);
    $jobPosting = null;

    foreach ($jsonLd as $block) {
        if (($block['@type'] ?? null) === 'JobPosting') {
            $jobPosting = $block;
            break;
        }
    }

    $externalId = null;
    if (preg_match('#/is-ilani/[^/]+-(\d+)#', $url, $match)) {
        $externalId = $match[1];
    }

    $descriptionHtml = firstMatch($html, ['#data-test="qualifications-and-job-description"[^>]*>(.*?)</div>#is']);

    return [
        'url' => $url,
        'title' => cleanText(firstMatch($html, ['#data-test="job-title"[^>]*>\s*([^<]+)#s'])),
        'company' => cleanText(firstMatch($html, ['#data-test="company-name"[^>]*>\s*([^<]+)#s'])),
        'location' => cleanText(firstMatch($html, ['#data-test="company-location"[^>]*>\s*([^<]+)#s'])),
        'employment_type' => firstMatch($html, ['#workType="([^"]+)"#']),
        'experience_level' => firstMatch($html, ['#experienceLevel="([^"]+)"#']),
        'work_model' => cleanText(firstMatch($html, ['#data-test="job-feature-item"[^>]*>\s*([^<]+)#s'])),
        'published_date' => firstMatch($html, ['#lastPublishDate="([^"]+)"#', '#lastPublishDate\s*:\s*([0-9\.]+)#']),
        'closing_date' => firstMatch($html, ['#closingDate="([^"]+)"#', '#closingDate\s*:\s*([0-9\.]+)#']),
        'description_preview' => $descriptionHtml ? mb_substr(cleanText($descriptionHtml) ?? '', 0, 180) : null,
        'description_len' => strlen(cleanText($descriptionHtml) ?? ''),
        'external_id_from_url' => $externalId,
        'external_url' => $url,
        'json_ld_job_posting' => $jobPosting,
        'json_ld_count' => count($jsonLd),
        'has_ssr' => str_contains($html, 'data-n-head-ssr'),
        'has_nuxt' => str_contains($html, '__NUXT__'),
        'blocking' => [
            'cloudflare' => (bool) preg_match('/cf-browser-verification|cf-challenge|just a moment/i', $html),
            'captcha_page' => (bool) preg_match('/<title[^>]*>.*?(captcha|robot|doğrulama).*?<\/title>/is', $html),
            'login_wall' => (bool) preg_match('/Bu ilanı görüntülemek için giriş|login required|oturum aç/i', $html),
        ],
        'body_len' => strlen($html),
    ];
}

$report = [
    'robots' => null,
    'listings' => [],
    'detail' => null,
];

$robots = Http::timeout(15)->get('https://www.kariyer.net/robots.txt');
$report['robots'] = [
    'status' => $robots->status(),
    'body' => (string) $robots->body(),
    'disallows_is_ilanlari' => str_contains((string) $robots->body(), 'Disallow: /is-ilanlari'),
    'disallows_is_ilani' => str_contains((string) $robots->body(), 'Disallow: /is-ilani'),
];

$listingUrls = [
    'https://www.kariyer.net/is-ilanlari?kw=flutter+developer',
    'https://www.kariyer.net/is-ilanlari/istanbul',
    'https://www.kariyer.net/is-ilanlari/yazilim',
];

$detailUrl = null;
$secondaryDetailUrl = 'https://www.kariyer.net/is-ilani/fonet-bilgi-teknolojileri-a-s-lider-yazilim-gelistirme-uzmani-full-stack-4477112';

foreach ($listingUrls as $listingUrl) {
    $page = fetchPage($listingUrl);
    $links = findJobLinks($page['body']);

    $report['listings'][] = [
        'url' => $listingUrl,
        'status' => $page['status'],
        'effective_url' => $page['effective_url'],
        'body_len' => $page['body_len'],
        'job_links_found' => count($links),
        'sample_links' => array_slice($links, 0, 5),
        'has_nuxt' => str_contains($page['body'], '__NUXT__'),
        'has_ssr' => str_contains($page['body'], 'data-n-head-ssr'),
        'json_ld_count' => count(extractJsonLd($page['body'])),
    ];

    if ($detailUrl === null && $links !== []) {
        $detailUrl = $links[0];
    }
}

if ($detailUrl === null) {
    echo json_encode(['error' => 'No job detail URL discovered from listing pages'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit(1);
}

$detailPage = fetchPage($detailUrl);
$parsed = parseDetail($detailPage['body'], $detailUrl);
$parsed['status'] = $detailPage['status'];
$parsed['effective_url'] = $detailPage['effective_url'];
$report['detail'] = $parsed;

$secondaryPage = fetchPage($secondaryDetailUrl);
$secondaryParsed = parseDetail($secondaryPage['body'], $secondaryDetailUrl);
$secondaryParsed['status'] = $secondaryPage['status'];
$secondaryParsed['effective_url'] = $secondaryPage['effective_url'];
$report['detail_secondary'] = $secondaryParsed;

echo json_encode($report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

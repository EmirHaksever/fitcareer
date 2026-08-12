<?php

declare(strict_types=1);

/**
 * Kariyer.net access diagnostic — no bypass attempts.
 * Collects HTTP evidence for KARIYER_NET_ACCESS_REPORT.md
 */

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Http;

const LISTING_URL = 'https://www.kariyer.net/is-ilanlari/yazilim';
const DETAIL_URL = 'https://www.kariyer.net/is-ilani/fonet-lider-yazilim-gelistirme-uzmani-full-stack-4477112';

/**
 * @return array<string, mixed>
 */
function probe(string $label, string $url, array $headers): array
{
    $startedAt = microtime(true);

    try {
        $response = Http::withHeaders($headers)
            ->withOptions(['allow_redirects' => true])
            ->timeout(30)
            ->connectTimeout(10)
            ->get($url);
    } catch (Throwable $exception) {
        return [
            'label' => $label,
            'url' => $url,
            'error' => $exception->getMessage(),
            'latency_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ];
    }

    $body = (string) $response->body();
    $headerBag = $response->headers();

    $interestingHeaders = [];
    foreach ([
        'server', 'content-type', 'content-length', 'retry-after', 'location',
        'cf-ray', 'cf-cache-status', 'x-cache', 'x-amz-cf-id', 'via',
        'set-cookie', 'x-akamai-transformed', 'x-frame-options',
        'strict-transport-security', 'x-request-id', 'date',
    ] as $name) {
        if (isset($headerBag[$name])) {
            $interestingHeaders[$name] = is_array($headerBag[$name])
                ? implode('; ', $headerBag[$name])
                : $headerBag[$name];
        }
    }

    $title = null;
    if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $body, $match)) {
        $title = trim(html_entity_decode(strip_tags($match[1])));
    }

    $bodySnippet = mb_substr(trim(preg_replace('/\s+/u', ' ', strip_tags($body)) ?? ''), 0, 500);

    return [
        'label' => $label,
        'url' => $url,
        'status' => $response->status(),
        'effective_url' => (string) ($response->effectiveUri() ?? $url),
        'redirected' => (string) ($response->effectiveUri() ?? $url) !== $url,
        'latency_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        'headers' => $interestingHeaders,
        'body_len' => strlen($body),
        'title' => $title,
        'body_snippet' => $bodySnippet,
        'signals' => [
            'cloudflare' => (bool) preg_match('/cf-ray|cloudflare|cf-challenge|just a moment/i', $body.$json = json_encode($interestingHeaders)),
            'akamai' => (bool) preg_match('/akamai|x-akamai/i', $body.$json),
            'captcha_page' => (bool) preg_match('/captcha|robot|doğrulama|access denied|forbidden/i', ($title ?? '').$bodySnippet),
            'login_wall' => (bool) preg_match('/giriş|login|oturum aç/i', $bodySnippet),
            'waf_block' => (bool) preg_match('/access denied|forbidden|blocked|unauthorized/i', ($title ?? '').$bodySnippet),
            'has_nuxt' => str_contains($body, '__NUXT__'),
            'has_job_links' => (bool) preg_match('#/is-ilani/[a-z0-9\-]+-\d+#i', $body),
        ],
    ];
}

$scraperUa = (string) config('scraper.user_agent');
$scraperLang = (string) config('scraper.accept_language');

$profiles = [
    'scraper_default' => [
        'User-Agent' => $scraperUa,
        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'Accept-Language' => $scraperLang,
    ],
    'browser_chrome' => [
        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
        'Accept-Language' => 'tr-TR,tr;q=0.9,en-US;q=0.8,en;q=0.7',
        'Accept-Encoding' => 'gzip, deflate, br',
    ],
    'curl_default' => [
        'User-Agent' => 'curl/8.0',
        'Accept' => '*/*',
    ],
    'minimal' => [
        'Accept' => 'text/html',
    ],
    'json_accept' => [
        'User-Agent' => $scraperUa,
        'Accept' => 'application/json',
        'Accept-Language' => $scraperLang,
    ],
];

$results = [
    'generated_at' => now()->toIso8601String(),
    'environment' => [
        'php' => PHP_VERSION,
        'os' => PHP_OS_FAMILY,
        'scraper_user_agent' => $scraperUa,
        'scraper_accept_language' => $scraperLang,
    ],
    'robots_txt' => null,
    'listing_probes' => [],
    'detail_probes' => [],
];

$robots = Http::timeout(15)->get('https://www.kariyer.net/robots.txt');
$robotsBody = (string) $robots->body();
$results['robots_txt'] = [
    'status' => $robots->status(),
    'disallows_listing' => str_contains($robotsBody, 'Disallow: /is-ilanlari'),
    'disallows_detail' => str_contains($robotsBody, 'Disallow: /is-ilani'),
    'snippet' => mb_substr($robotsBody, 0, 800),
];

foreach ($profiles as $name => $headers) {
    $results['listing_probes'][] = probe("listing:$name", LISTING_URL, $headers);
    $results['detail_probes'][] = probe("detail:$name", DETAIL_URL, $headers);
}

$outPath = base_path('KARIYER_NET_ACCESS_REPORT.json');
file_put_contents($outPath, json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

echo "Diagnostic written to {$outPath}\n\n";

foreach ($results['listing_probes'] as $probe) {
    echo sprintf(
        "[listing] %-20s status=%s len=%s cf=%s jobs=%s title=%s\n",
        $probe['label'] ?? '?',
        $probe['status'] ?? 'ERR',
        $probe['body_len'] ?? 0,
        ($probe['signals']['cloudflare'] ?? false) ? 'yes' : 'no',
        ($probe['signals']['has_job_links'] ?? false) ? 'yes' : 'no',
        mb_substr($probe['title'] ?? '', 0, 60),
    );
}

foreach ($results['detail_probes'] as $probe) {
    echo sprintf(
        "[detail ] %-20s status=%s len=%s cf=%s title=%s\n",
        $probe['label'] ?? '?',
        $probe['status'] ?? 'ERR',
        $probe['body_len'] ?? 0,
        ($probe['signals']['cloudflare'] ?? false) ? 'yes' : 'no',
        mb_substr($probe['title'] ?? '', 0, 60),
    );
}

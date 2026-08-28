<?php

declare(strict_types=1);

/**
 * Phase C READ-ONLY career source discovery probe.
 * - No DB writes, no imports, no source mutations
 * - Rate-conscious (250ms between probes)
 */

require __DIR__.'/ats-coverage-discovery-helpers.php';

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

const PHASE_C_PROBE_DELAY_US = 250_000;
const PHASE_C_USER_AGENT = 'FitCareer-PhaseC-Discovery/1.0 (+read-only-audit)';

/** @return list<array<string,mixed>> */
function phaseCCompanyCatalog(): array
{
    return [
        // --- Already seeded (baseline / overlap check) ---
        ['name' => 'Commencis', 'category' => 'software', 'website' => 'https://www.commencis.com', 'career_url' => 'https://jobs.lever.co/commencis', 'candidates' => ['lever:commencis'], 'seeded' => true],
        ['name' => 'Midas', 'category' => 'fintech', 'website' => 'https://www.getmidas.com', 'career_url' => 'https://jobs.lever.co/getmidas', 'candidates' => ['lever:getmidas'], 'seeded' => true],
        ['name' => 'Insider One', 'category' => 'saas', 'website' => 'https://useinsider.com', 'career_url' => 'https://jobs.lever.co/insiderone', 'candidates' => ['lever:insiderone'], 'seeded' => true],
        ['name' => 'Trendyol', 'category' => 'ecommerce', 'website' => 'https://www.trendyol.com', 'career_url' => 'https://jobs.lever.co/trendyol', 'candidates' => ['lever:trendyol'], 'seeded' => true],
        ['name' => 'Dream Games', 'category' => 'gaming', 'website' => 'https://www.dreamgames.com', 'career_url' => 'https://jobs.lever.co/dreamgames', 'candidates' => ['lever:dreamgames'], 'seeded' => true],
        ['name' => 'iyzico', 'category' => 'fintech', 'website' => 'https://www.iyzico.com', 'career_url' => 'https://jobs.lever.co/iyzico', 'candidates' => ['lever:iyzico'], 'seeded' => true],
        ['name' => 'Grand Games', 'category' => 'gaming', 'website' => 'https://grand.games', 'career_url' => 'https://jobs.lever.co/grand', 'candidates' => ['lever:grand'], 'seeded' => true],
        ['name' => 'Wingie Enuygun', 'category' => 'travel', 'website' => 'https://www.wingie.com', 'career_url' => 'https://apply.workable.com/wingieenuygun', 'candidates' => ['workable:wingieenuygun'], 'seeded' => true],
        ['name' => 'Vertigo Games', 'category' => 'gaming', 'website' => 'https://vertigo.games', 'career_url' => 'https://apply.workable.com/vertigogames', 'candidates' => ['workable:vertigogames'], 'seeded' => true],
        ['name' => 'Codeway', 'category' => 'gaming', 'website' => 'https://codeway.co', 'career_url' => 'https://jobs.ashbyhq.com/codeway', 'candidates' => ['ashby:codeway'], 'seeded' => true],
        ['name' => 'Good Job Games', 'category' => 'gaming', 'website' => 'https://goodjobgames.com', 'career_url' => 'https://job-boards.greenhouse.io/goodjobgames', 'candidates' => ['greenhouse:goodjobgames'], 'seeded' => true],

        // --- Fintech ---
        ['name' => 'Papara', 'category' => 'fintech', 'website' => 'https://www.papara.com', 'career_url' => 'https://www.papara.com/career', 'candidates' => ['lever:papara', 'greenhouse:papara', 'workable:papara', 'ashby:papara', 'smartrecruiters:Papara', 'smartrecruiters:papara']],
        ['name' => 'Param', 'category' => 'fintech', 'website' => 'https://param.com.tr', 'career_url' => 'https://param.com.tr/tr/kariyer', 'candidates' => ['lever:param', 'workable:param', 'smartrecruiters:Param']],
        ['name' => 'Craftgate', 'category' => 'fintech', 'website' => 'https://craftgate.io', 'career_url' => 'https://craftgate.io/careers', 'candidates' => ['lever:craftgate', 'greenhouse:craftgate', 'workable:craftgate', 'ashby:craftgate']],
        ['name' => 'PayTR', 'category' => 'fintech', 'website' => 'https://www.paytr.com', 'career_url' => 'https://www.paytr.com/kariyer', 'candidates' => ['lever:paytr', 'workable:paytr']],
        ['name' => 'Figopara', 'category' => 'fintech', 'website' => 'https://figopara.com', 'career_url' => 'https://figopara.com/careers', 'candidates' => ['lever:figopara', 'workable:figopara', 'smartrecruiters:Figopara']],
        ['name' => 'Ozan', 'category' => 'fintech', 'website' => 'https://ozan.com', 'career_url' => 'https://ozan.com/careers', 'candidates' => ['lever:ozan', 'greenhouse:ozan']],
        ['name' => 'Paraşüt', 'category' => 'saas', 'website' => 'https://parasut.com', 'career_url' => 'https://parasut.com/kariyer', 'candidates' => ['lever:parasut', 'workable:parasut', 'teamtailor:parasut']],
        ['name' => 'Moka', 'category' => 'fintech', 'website' => 'https://mokaunited.com', 'career_url' => 'https://mokaunited.com/careers', 'candidates' => ['lever:moka', 'workable:moka']],

        // --- E-commerce / marketplace ---
        ['name' => 'Hepsiburada', 'category' => 'ecommerce', 'website' => 'https://www.hepsiburada.com', 'career_url' => 'https://www.hepsiburada.com/kariyer', 'candidates' => ['lever:hepsiburada', 'greenhouse:hepsiburada', 'workable:hepsiburada', 'smartrecruiters:Hepsiburada']],
        ['name' => 'Getir', 'category' => 'ecommerce', 'website' => 'https://getir.com', 'career_url' => 'https://getir.com/careers', 'candidates' => ['lever:getir', 'greenhouse:getir', 'workable:getir', 'ashby:getir', 'smartrecruiters:Getir']],
        ['name' => 'n11', 'category' => 'ecommerce', 'website' => 'https://www.n11.com', 'career_url' => 'https://www.n11.com/kariyer', 'candidates' => ['greenhouse:n11', 'lever:n11', 'workable:n11']],
        ['name' => 'Çiçeksepeti', 'category' => 'ecommerce', 'website' => 'https://www.ciceksepeti.com', 'career_url' => 'https://kariyer.ciceksepeti.com', 'candidates' => ['lever:ciceksepeti', 'greenhouse:ciceksepeti', 'workable:ciceksepeti', 'smartrecruiters:Ciceksepeti']],
        ['name' => 'Boyner', 'category' => 'ecommerce', 'website' => 'https://www.boyner.com.tr', 'career_url' => 'https://kariyer.boyner.com.tr', 'candidates' => ['lever:boyner', 'workable:boyner', 'smartrecruiters:Boyner']],
        ['name' => 'Yemeksepeti', 'category' => 'ecommerce', 'website' => 'https://www.yemeksepeti.com', 'career_url' => 'https://careers.deliveryhero.com', 'candidates' => ['lever:yemeksepeti', 'greenhouse:deliveryhero', 'smartrecruiters:DeliveryHero', 'smartrecruiters:Yemeksepeti']],

        // --- Gaming ---
        ['name' => 'Peak Games', 'category' => 'gaming', 'website' => 'https://www.peak.com', 'career_url' => 'https://www.peak.com/careers', 'candidates' => ['lever:peakgames', 'greenhouse:peakgames', 'ashby:peakgames', 'workable:peakgames']],
        ['name' => 'Rollic', 'category' => 'gaming', 'website' => 'https://rollicgames.com', 'career_url' => 'https://rollicgames.com/careers', 'candidates' => ['lever:rollic', 'greenhouse:rollic', 'workable:rollic']],
        ['name' => 'MagicLab', 'category' => 'gaming', 'website' => 'https://magiclab.com.tr', 'career_url' => 'https://magiclab.com.tr/careers', 'candidates' => ['lever:magiclab', 'workable:magiclab']],
        ['name' => 'Potatee', 'category' => 'gaming', 'website' => 'https://potatee.com', 'career_url' => 'https://potatee.com/careers', 'candidates' => ['lever:potatee', 'workable:potatee']],
        ['name' => 'Invictus Games', 'category' => 'gaming', 'website' => 'https://invictus.com', 'career_url' => 'https://invictus.com/careers', 'candidates' => ['lever:invictus', 'lever:invictusgames']],

        // --- SaaS / software ---
        ['name' => 'Logo Yazılım', 'category' => 'saas', 'website' => 'https://www.logo.com.tr', 'career_url' => 'https://www.logo.com.tr/logo-kariyer', 'candidates' => ['lever:logo', 'greenhouse:logo', 'workable:logoyazilim', 'ashby:logo']],
        ['name' => 'Teknasyon', 'category' => 'software', 'website' => 'https://teknasyon.com', 'career_url' => 'https://teknasyon.com/career', 'candidates' => ['lever:teknasyon', 'workable:teknasyon', 'greenhouse:teknasyon']],
        ['name' => 'Jotform', 'category' => 'saas', 'website' => 'https://www.jotform.com', 'career_url' => 'https://www.jotform.com/jobs', 'candidates' => ['lever:jotform', 'greenhouse:jotform', 'workable:jotform']],
        ['name' => 'Kolay İK', 'category' => 'saas', 'website' => 'https://www.kolayik.com', 'career_url' => 'https://www.kolayik.com/kariyer', 'candidates' => ['lever:kolayik', 'workable:kolayik', 'teamtailor:kolayik']],
        ['name' => 'Sovos (Foriba)', 'category' => 'saas', 'website' => 'https://sovos.com', 'career_url' => 'https://sovos.com/careers', 'candidates' => ['lever:sovos', 'greenhouse:sovos', 'smartrecruiters:Sovos']],
        ['name' => 'Protel', 'category' => 'saas', 'website' => 'https://www.protel.com.tr', 'career_url' => 'https://www.protel.com.tr/kariyer', 'candidates' => ['lever:protel', 'workable:protel']],
        ['name' => 'Logo İşbaşı', 'category' => 'saas', 'website' => 'https://isbasi.com', 'career_url' => 'https://www.logo.com.tr/logo-kariyer', 'candidates' => ['lever:isbasi']],

        // --- Telecom / enterprise IT ---
        ['name' => 'Turkcell', 'category' => 'telecom', 'website' => 'https://www.turkcell.com.tr', 'career_url' => 'https://kariyer.turkcell.com.tr', 'candidates' => ['greenhouse:turkcell', 'lever:turkcell', 'smartrecruiters:Turkcell']],
        ['name' => 'Vodafone Turkey', 'category' => 'telecom', 'website' => 'https://www.vodafone.com.tr', 'career_url' => 'https://careers.vodafone.com.tr', 'candidates' => ['greenhouse:vodafone', 'smartrecruiters:Vodafone', 'smartrecruiters:VodafoneTurkey']],
        ['name' => 'Netaş', 'category' => 'enterprise', 'website' => 'https://www.netas.com.tr', 'career_url' => 'https://www.netas.com.tr/kariyer', 'candidates' => ['lever:netas', 'workable:netas', 'smartrecruiters:Netas']],
        ['name' => 'Softtech', 'category' => 'enterprise', 'website' => 'https://www.softtech.com.tr', 'career_url' => 'https://kariyer.softtech.com.tr', 'candidates' => ['lever:softtech', 'workable:softtech']],
        ['name' => 'Intertech', 'category' => 'banking_tech', 'website' => 'https://www.intertech.com.tr', 'career_url' => 'https://kariyer.intertech.com.tr', 'candidates' => ['lever:intertech', 'workable:intertech']],
        ['name' => 'TAV Technologies', 'category' => 'enterprise', 'website' => 'https://www.tavtechnologies.aero', 'career_url' => 'https://www.tavtechnologies.aero/careers', 'candidates' => ['lever:tavtechnologies', 'workable:tavtechnologies']],

        // --- Startups / scaleups ---
        ['name' => 'BiTaksi', 'category' => 'mobility', 'website' => 'https://bitaksi.com', 'career_url' => 'https://bitaksi.com/careers', 'candidates' => ['lever:bitaksi', 'workable:bitaksi', 'greenhouse:bitaksi']],
        ['name' => 'Scotty', 'category' => 'mobility', 'website' => 'https://scotty.ai', 'career_url' => 'https://scotty.ai/careers', 'candidates' => ['lever:scotty', 'workable:scotty']],
        ['name' => 'Volt Lines', 'category' => 'mobility', 'website' => 'https://voltlines.com', 'career_url' => 'https://apply.workable.com/voltlines', 'candidates' => ['workable:voltlines']],
        ['name' => 'Kolay Gelsin', 'category' => 'logistics', 'website' => 'https://kolaygelsin.com', 'career_url' => 'https://kolaygelsin.com/kariyer', 'candidates' => ['lever:kolaygelsin', 'workable:kolaygelsin']],
        ['name' => 'Nomagic', 'category' => 'ai', 'website' => 'https://nomagic.ai', 'career_url' => 'https://nomagic.ai/careers', 'candidates' => ['lever:nomagic', 'greenhouse:nomagic', 'ashby:nomagic']],
        ['name' => 'Lucid Dreams (AI)', 'category' => 'ai', 'website' => 'https://luciddreams.ai', 'career_url' => 'https://apply.workable.com/lucida-ai', 'candidates' => ['workable:lucida-ai'], 'seeded' => true],

        // --- International with TR hiring ---
        ['name' => 'Ajax Systems', 'category' => 'intl_tr', 'website' => 'https://ajax.systems', 'career_url' => 'https://jobs.lever.co/ajax', 'candidates' => ['lever:ajax']],
        ['name' => 'Binance', 'category' => 'intl_tr', 'website' => 'https://www.binance.com', 'career_url' => 'https://jobs.lever.co/binance', 'candidates' => ['lever:binance']],
        ['name' => 'RateHawk', 'category' => 'intl_tr', 'website' => 'https://ratehawk.com', 'career_url' => 'https://apply.workable.com/ratehawk', 'candidates' => ['workable:ratehawk']],
        ['name' => 'Teltonika', 'category' => 'intl_tr', 'website' => 'https://teltonika.io', 'career_url' => 'https://apply.workable.com/teltonika', 'candidates' => ['workable:teltonika']],
        ['name' => 'Intellect', 'category' => 'intl_tr', 'website' => 'https://intellect.co', 'career_url' => 'https://apply.workable.com/intellecthq', 'candidates' => ['workable:intellecthq']],
        ['name' => 'Revolut', 'category' => 'intl_tr', 'website' => 'https://www.revolut.com', 'career_url' => 'https://www.revolut.com/careers', 'candidates' => ['lever:revolut', 'greenhouse:revolut', 'smartrecruiters:Revolut']],
        ['name' => 'Amazon Turkey', 'category' => 'intl_tr', 'website' => 'https://www.amazon.com.tr', 'career_url' => 'https://www.amazon.jobs/en/locations/istanbul-turkey', 'candidates' => ['amazon_jobs:istanbul']],

        // --- Custom / bank career portals ---
        ['name' => 'Garanti BBVA Technology', 'category' => 'banking_tech', 'website' => 'https://www.garantibbvatechnology.com.tr', 'career_url' => 'https://kariyer.garantibbvatechnology.com.tr', 'candidates' => ['custom:garanti-tech']],
        ['name' => 'Akbank Tech', 'category' => 'banking_tech', 'website' => 'https://akbank.com', 'career_url' => 'https://akbank.com/kariyer', 'candidates' => ['custom:akbank']],
        ['name' => 'Arçelik', 'category' => 'enterprise', 'website' => 'https://www.arcelik.com.tr', 'career_url' => 'https://kariyer.arcelik.com.tr', 'candidates' => ['custom:arcelik', 'smartrecruiters:Arcelik']],
    ];
}

function phaseCHttpGet(string $url, int $timeout = 30): array
{
    $startedAt = microtime(true);

    try {
        $response = Illuminate\Support\Facades\Http::timeout($timeout)
            ->connectTimeout(12)
            ->withHeaders([
                'Accept' => 'text/html,application/json,*/*',
                'User-Agent' => PHASE_C_USER_AGENT,
            ])
            ->get($url);
    } catch (Throwable $exception) {
        return [
            'url' => $url,
            'http_status' => null,
            'latency_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'error' => $exception->getMessage(),
            'body' => null,
            'headers' => [],
        ];
    }

    return [
        'url' => $url,
        'http_status' => $response->status(),
        'latency_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        'error' => null,
        'body' => (string) $response->body(),
        'headers' => [
            'content-type' => $response->header('Content-Type'),
        ],
    ];
}

/** @return array<string,mixed> */
function probeLeverBoard(string $slug): array
{
    $url = 'https://api.lever.co/v0/postings/'.$slug.'?mode=json&limit=200';
    $resp = httpGetJson($url);

    if (($resp['http_status'] ?? 0) !== 200 || ! is_array($resp['json'])) {
        return [
            'provider' => 'lever',
            'slug' => $slug,
            'accessible' => false,
            'http_status' => $resp['http_status'],
            'error' => $resp['error'] ?? 'invalid_json',
            'total_jobs' => 0,
            'turkey_jobs' => 0,
            'istanbul_jobs' => 0,
            'fresh_jobs' => 0,
        ];
    }

    $jobs = $resp['json'];
    $total = count($jobs);
    $turkey = 0;
    $istanbul = 0;
    $fresh = 0;
    $timestamps = [];

    foreach ($jobs as $job) {
        if (! is_array($job)) {
            continue;
        }
        $categories = is_array($job['categories'] ?? null) ? $job['categories'] : [];
        $locations = [];
        if (is_string($categories['location'] ?? null)) {
            $locations[] = $categories['location'];
        }
        foreach ($categories['allLocations'] ?? [] as $loc) {
            if (is_string($loc)) {
                $locations[] = $loc;
            }
        }
        $blob = locationBlobFromParts($locations);
        $class = classifyLocation($blob);
        if (in_array($class, ['istanbul', 'turkey', 'remote_turkey'], true)) {
            $turkey++;
            if ($class === 'istanbul') {
                $istanbul++;
            }
        }
        $ts = isset($job['createdAt']) ? (int) floor(((int) $job['createdAt']) / 1000) : null;
        $timestamps[] = $ts;
    }

    $freshness = freshnessStats($timestamps);

    return [
        'provider' => 'lever',
        'slug' => $slug,
        'accessible' => true,
        'http_status' => 200,
        'endpoint' => $url,
        'total_jobs' => $total,
        'turkey_jobs' => $turkey,
        'istanbul_jobs' => $istanbul,
        'fresh_jobs' => $freshness['fresh'],
        'stale_jobs' => $freshness['stale'],
        'newest' => $freshness['newest'],
    ];
}

/** @return array<string,mixed> */
function probeGreenhouseBoard(string $token): array
{
    $url = 'https://boards-api.greenhouse.io/v1/boards/'.$token.'/jobs?content=true';
    $resp = httpGetJson($url);

    if (($resp['http_status'] ?? 0) !== 200 || ! is_array($resp['json'])) {
        return [
            'provider' => 'greenhouse',
            'slug' => $token,
            'accessible' => false,
            'http_status' => $resp['http_status'],
            'error' => $resp['error'] ?? 'invalid_json',
            'total_jobs' => 0,
            'turkey_jobs' => 0,
            'istanbul_jobs' => 0,
            'fresh_jobs' => 0,
        ];
    }

    $jobs = is_array($resp['json']['jobs'] ?? null) ? $resp['json']['jobs'] : [];
    $total = count($jobs);
    $turkey = 0;
    $istanbul = 0;
    $timestamps = [];

    foreach ($jobs as $job) {
        if (! is_array($job)) {
            continue;
        }
        $parts = [];
        if (is_array($job['location'] ?? null) && is_string($job['location']['name'] ?? null)) {
            $parts[] = $job['location']['name'];
        }
        foreach ($job['offices'] ?? [] as $office) {
            if (is_array($office) && is_string($office['location'] ?? null)) {
                $parts[] = $office['location'];
            }
        }
        $blob = locationBlobFromParts($parts);
        $class = classifyLocation($blob);
        if (in_array($class, ['istanbul', 'turkey', 'remote_turkey'], true)) {
            $turkey++;
            if ($class === 'istanbul') {
                $istanbul++;
            }
        }
        $ts = parseTimestamp($job['first_published'] ?? $job['updated_at'] ?? null);
        $timestamps[] = $ts;
    }

    $freshness = freshnessStats($timestamps);

    return [
        'provider' => 'greenhouse',
        'slug' => $token,
        'accessible' => true,
        'http_status' => 200,
        'endpoint' => $url,
        'total_jobs' => $total,
        'turkey_jobs' => $turkey,
        'istanbul_jobs' => $istanbul,
        'fresh_jobs' => $freshness['fresh'],
        'stale_jobs' => $freshness['stale'],
        'newest' => $freshness['newest'],
    ];
}

/** @return array<string,mixed> */
function probeWorkableBoard(string $slug): array
{
    $url = 'https://apply.workable.com/api/v1/widget/accounts/'.$slug.'?details=true';
    $resp = httpGetJson($url);

    if (($resp['http_status'] ?? 0) !== 200 || ! is_array($resp['json'])) {
        return [
            'provider' => 'workable',
            'slug' => $slug,
            'accessible' => false,
            'http_status' => $resp['http_status'],
            'error' => $resp['error'] ?? 'invalid_json',
            'total_jobs' => 0,
            'turkey_jobs' => 0,
            'istanbul_jobs' => 0,
            'fresh_jobs' => 0,
        ];
    }

    $jobs = is_array($resp['json']['jobs'] ?? null) ? $resp['json']['jobs'] : [];
    $total = count($jobs);
    $turkey = 0;
    $istanbul = 0;
    $timestamps = [];

    foreach ($jobs as $job) {
        if (! is_array($job)) {
            continue;
        }
        $parts = [];
        if (is_string($job['location']['country'] ?? null)) {
            $parts[] = $job['location']['country'];
        }
        if (is_string($job['location']['city'] ?? null)) {
            $parts[] = $job['location']['city'];
        }
        if (is_string($job['location']['region'] ?? null)) {
            $parts[] = $job['location']['region'];
        }
        if (is_string($job['country'] ?? null)) {
            $parts[] = $job['country'];
        }
        if (is_string($job['city'] ?? null)) {
            $parts[] = $job['city'];
        }
        $blob = locationBlobFromParts($parts);
        $class = classifyLocation($blob);
        if (in_array($class, ['istanbul', 'turkey', 'remote_turkey'], true)) {
            $turkey++;
            if ($class === 'istanbul') {
                $istanbul++;
            }
        }
        $ts = parseTimestamp($job['published_at'] ?? $job['created_at'] ?? null);
        $timestamps[] = $ts;
    }

    $freshness = freshnessStats($timestamps);

    return [
        'provider' => 'workable',
        'slug' => $slug,
        'accessible' => true,
        'http_status' => 200,
        'endpoint' => $url,
        'total_jobs' => $total,
        'turkey_jobs' => $turkey,
        'istanbul_jobs' => $istanbul,
        'fresh_jobs' => $freshness['fresh'],
        'stale_jobs' => $freshness['stale'],
        'newest' => $freshness['newest'],
    ];
}

/** @return array<string,mixed> */
function probeAshbyBoard(string $slug): array
{
    $url = 'https://api.ashbyhq.com/posting-api/job-board/'.$slug;
    $resp = httpGetJson($url);

    if (($resp['http_status'] ?? 0) !== 200 || ! is_array($resp['json'])) {
        return [
            'provider' => 'ashby',
            'slug' => $slug,
            'accessible' => false,
            'http_status' => $resp['http_status'],
            'error' => $resp['error'] ?? 'invalid_json',
            'total_jobs' => 0,
            'turkey_jobs' => 0,
            'istanbul_jobs' => 0,
            'fresh_jobs' => 0,
        ];
    }

    $jobs = is_array($resp['json']['jobs'] ?? null) ? $resp['json']['jobs'] : [];
    $total = count($jobs);
    $turkey = 0;
    $istanbul = 0;

    foreach ($jobs as $job) {
        if (! is_array($job)) {
            continue;
        }
        $parts = [];
        if (is_string($job['location'] ?? null)) {
            $parts[] = $job['location'];
        }
        if (is_string($job['locationName'] ?? null)) {
            $parts[] = $job['locationName'];
        }
        $blob = locationBlobFromParts($parts);
        $class = classifyLocation($blob);
        if (in_array($class, ['istanbul', 'turkey', 'remote_turkey'], true)) {
            $turkey++;
            if ($class === 'istanbul') {
                $istanbul++;
            }
        }
    }

    return [
        'provider' => 'ashby',
        'slug' => $slug,
        'accessible' => true,
        'http_status' => 200,
        'endpoint' => $url,
        'total_jobs' => $total,
        'turkey_jobs' => $turkey,
        'istanbul_jobs' => $istanbul,
        'fresh_jobs' => null,
        'stale_jobs' => null,
    ];
}

/** @return array<string,mixed> */
function probeSmartRecruiters(string $companyId): array
{
    $url = 'https://api.smartrecruiters.com/v1/companies/'.$companyId.'/postings?limit=100';
    $resp = httpGetJson($url);

    if (($resp['http_status'] ?? 0) !== 200 || ! is_array($resp['json'])) {
        return [
            'provider' => 'smartrecruiters',
            'slug' => $companyId,
            'accessible' => false,
            'http_status' => $resp['http_status'],
            'error' => $resp['error'] ?? 'invalid_json',
            'total_jobs' => 0,
            'turkey_jobs' => 0,
            'istanbul_jobs' => 0,
        ];
    }

    $jobs = is_array($resp['json']['content'] ?? null) ? $resp['json']['content'] : [];
    $total = (int) ($resp['json']['totalFound'] ?? count($jobs));
    $turkey = 0;
    $istanbul = 0;

    foreach ($jobs as $job) {
        if (! is_array($job)) {
            continue;
        }
        $loc = is_array($job['location'] ?? null) ? $job['location'] : [];
        $parts = array_filter([
            is_string($loc['city'] ?? null) ? $loc['city'] : null,
            is_string($loc['country'] ?? null) ? $loc['country'] : null,
            is_string($loc['region'] ?? null) ? $loc['region'] : null,
        ]);
        $blob = locationBlobFromParts(array_values($parts));
        $class = classifyLocation($blob);
        if (in_array($class, ['istanbul', 'turkey', 'remote_turkey'], true)) {
            $turkey++;
            if ($class === 'istanbul') {
                $istanbul++;
            }
        }
    }

    return [
        'provider' => 'smartrecruiters',
        'slug' => $companyId,
        'accessible' => true,
        'http_status' => 200,
        'endpoint' => $url,
        'total_jobs' => $total,
        'turkey_jobs' => $turkey,
        'istanbul_jobs' => $istanbul,
    ];
}

/** @return array<string,mixed> */
function probeTeamtailor(string $slug): array
{
    $url = 'https://'.$slug.'.teamtailor.com/jobs.json';
    $resp = httpGetJson($url);

    if (($resp['http_status'] ?? 0) !== 200 || ! is_array($resp['json'])) {
        return [
            'provider' => 'teamtailor',
            'slug' => $slug,
            'accessible' => false,
            'http_status' => $resp['http_status'],
            'error' => $resp['error'] ?? 'invalid_json',
            'total_jobs' => 0,
            'turkey_jobs' => 0,
            'istanbul_jobs' => 0,
        ];
    }

    $jobs = is_array($resp['json']) ? $resp['json'] : [];
    $total = count($jobs);
    $turkey = 0;
    $istanbul = 0;

    foreach ($jobs as $job) {
        if (! is_array($job)) {
            continue;
        }
        $parts = [];
        if (is_string($job['location'] ?? null)) {
            $parts[] = $job['location'];
        }
        if (is_string($job['locations'] ?? null)) {
            $parts[] = $job['locations'];
        }
        $blob = locationBlobFromParts($parts);
        $class = classifyLocation($blob);
        if (in_array($class, ['istanbul', 'turkey', 'remote_turkey'], true)) {
            $turkey++;
            if ($class === 'istanbul') {
                $istanbul++;
            }
        }
    }

    return [
        'provider' => 'teamtailor',
        'slug' => $slug,
        'accessible' => true,
        'http_status' => 200,
        'endpoint' => $url,
        'total_jobs' => $total,
        'turkey_jobs' => $turkey,
        'istanbul_jobs' => $istanbul,
    ];
}

/** @return array<string,mixed> */
function analyzeCareerPage(string $url): array
{
    $resp = phaseCHttpGet($url);

    if (($resp['http_status'] ?? 0) !== 200 || ! is_string($resp['body'])) {
        return [
            'career_url' => $url,
            'http_status' => $resp['http_status'],
            'accessible' => false,
            'error' => $resp['error'],
            'detected_platform' => 'unknown',
            'json_ld_jobposting_count' => 0,
            'ats_fingerprints' => [],
            'cloudflare_or_bot_block' => false,
        ];
    }

    $body = $resp['body'];
    $lower = mb_strtolower($body);
    $fingerprints = [];

    foreach ([
        'greenhouse' => ['boards.greenhouse.io', 'job-boards.greenhouse.io', 'boards-api.greenhouse.io'],
        'lever' => ['jobs.lever.co', 'api.lever.co'],
        'workable' => ['apply.workable.com', 'workable.com/api'],
        'ashby' => ['jobs.ashbyhq.com', 'ashbyhq.com/posting-api'],
        'smartrecruiters' => ['smartrecruiters.com', 'api.smartrecruiters.com'],
        'teamtailor' => ['teamtailor.com'],
        'personio' => ['jobs.personio.de', 'personio.de'],
        'linkedin' => ['linkedin.com/jobs', 'linkedin.com/company'],
        'kariyer.net' => ['kariyer.net'],
    ] as $platform => $needles) {
        foreach ($needles as $needle) {
            if (str_contains($lower, $needle)) {
                $fingerprints[] = $platform;
                break;
            }
        }
    }

    $jsonLdCount = preg_match_all('/"@type"\s*:\s*"JobPosting"/i', $body) ?: 0;
    $blocked = str_contains($lower, 'cf-browser-verification')
        || str_contains($lower, 'attention required')
        || str_contains($lower, 'captcha')
        || ($resp['http_status'] === 403);

    return [
        'career_url' => $url,
        'http_status' => $resp['http_status'],
        'accessible' => ! $blocked,
        'detected_platform' => $fingerprints[0] ?? 'custom',
        'ats_fingerprints' => array_values(array_unique($fingerprints)),
        'json_ld_jobposting_count' => $jsonLdCount,
        'cloudflare_or_bot_block' => $blocked,
        'body_bytes' => strlen($body),
    ];
}

/** @return array<string,mixed> */
function probeCandidate(string $candidate): array
{
    [$provider, $slug] = explode(':', $candidate, 2);

    return match ($provider) {
        'lever' => probeLeverBoard($slug),
        'greenhouse' => probeGreenhouseBoard($slug),
        'workable' => probeWorkableBoard($slug),
        'ashby' => probeAshbyBoard($slug),
        'smartrecruiters' => probeSmartRecruiters($slug),
        'teamtailor' => probeTeamtailor($slug),
        default => [
            'provider' => $provider,
            'slug' => $slug,
            'accessible' => false,
            'error' => 'not_probed',
            'total_jobs' => 0,
            'turkey_jobs' => 0,
            'istanbul_jobs' => 0,
        ],
    };
}

function confidenceFromProbe(array $best, bool $seeded): string
{
    if ($seeded) {
        return 'HIGH';
    }
    if (! ($best['accessible'] ?? false)) {
        return 'LOW';
    }
    $tr = (int) ($best['turkey_jobs'] ?? 0);
    $fresh = (int) ($best['fresh_jobs'] ?? $tr);
    $total = (int) ($best['total_jobs'] ?? 0);
    $trPct = $total > 0 ? ($tr / $total) : 0;

    if ($tr >= 3 && ($fresh >= 3 || $best['fresh_jobs'] === null) && ($trPct >= 0.5 || $tr >= 5)) {
        return 'HIGH';
    }
    if ($tr >= 1 && $fresh >= 1) {
        return 'MEDIUM';
    }

    return 'LOW';
}

function actionFromProbe(array $best, bool $seeded, array $careerPage): string
{
    if ($seeded) {
        return 'EXISTING';
    }
    if (! ($best['accessible'] ?? false)) {
        if (($careerPage['json_ld_jobposting_count'] ?? 0) > 0) {
            return 'NEW_ADAPTER_CANDIDATE';
        }
        if (($careerPage['cloudflare_or_bot_block'] ?? false)) {
            return 'MANUAL_REVIEW';
        }

        return 'DISCOVER_MORE';
    }

    $tr = (int) ($best['turkey_jobs'] ?? 0);
    $total = (int) ($best['total_jobs'] ?? 0);
    $trPct = $total > 0 ? ($tr / $total) : 0;
    $existingProviders = ['lever', 'greenhouse', 'workable', 'ashby'];

    if ($tr >= 3 && ($trPct >= 0.15 || $tr >= 5) && in_array($best['provider'] ?? '', $existingProviders, true)) {
        if ($trPct >= 0.7 || $tr >= 5) {
            return 'SEED_NOW';
        }

        return 'EXISTING_PROVIDER_SUPPORT';
    }

    if ($tr >= 1 && in_array($best['provider'] ?? '', $existingProviders, true) && $trPct < 0.15) {
        return 'REJECT';
    }

    if (($best['provider'] ?? '') === 'smartrecruiters' && $tr >= 3) {
        return 'NEW_ADAPTER_CANDIDATE';
    }

    if (($careerPage['json_ld_jobposting_count'] ?? 0) >= 3) {
        return 'NEW_ADAPTER_CANDIDATE';
    }

    if ($tr === 0 && $total > 0) {
        return 'REJECT';
    }

    return 'DISCOVER_MORE';
}

function sourceValueScore(array $company): int
{
    $best = $company['best_probe'] ?? [];
    $tr = (int) ($best['turkey_jobs'] ?? 0);
    $ist = (int) ($best['istanbul_jobs'] ?? 0);
    $fresh = (int) ($best['fresh_jobs'] ?? 0);
    $total = max(1, (int) ($best['total_jobs'] ?? 0));
    $trPct = $tr / $total;
    $existing = in_array($best['provider'] ?? '', ['lever', 'greenhouse', 'workable', 'ashby'], true) ? 15 : 0;
    $pollution = (int) round((1 - $trPct) * 20);

    return (int) round(
        ($tr * 4)
        + ($ist * 2)
        + ($fresh * 2)
        + ($existing)
        + (($best['accessible'] ?? false) ? 10 : 0)
        - $pollution
        - (($company['career_page']['cloudflare_or_bot_block'] ?? false) ? 15 : 0)
    );
}

function platformGroup(array $company): string
{
    $action = $company['recommended_action'] ?? '';
    $best = $company['best_probe'] ?? [];

    if ($action === 'EXISTING') {
        return 'A';
    }
    if ($action === 'SEED_NOW' || $action === 'EXISTING_PROVIDER_SUPPORT') {
        return in_array($best['provider'] ?? '', ['lever', 'greenhouse', 'workable', 'ashby'], true) ? 'A' : 'B';
    }
    if ($action === 'NEW_ADAPTER_CANDIDATE') {
        return 'C';
    }
    if (($company['career_page']['ats_fingerprints'][0] ?? '') === 'linkedin') {
        return 'E';
    }
    if ($action === 'REJECT' || $action === 'MANUAL_REVIEW') {
        return 'F';
    }

    return 'D';
}

// --- Main execution ---
$startedAt = microtime(true);
$companies = phaseCCompanyCatalog();
$results = [];

foreach ($companies as $entry) {
    $probes = [];
    foreach ($entry['candidates'] as $candidate) {
        if (str_starts_with($candidate, 'custom:') || str_starts_with($candidate, 'amazon_jobs:')) {
            continue;
        }
        $probes[] = probeCandidate($candidate);
        usleep(PHASE_C_PROBE_DELAY_US);
    }

    usleep(PHASE_C_PROBE_DELAY_US);
    $careerPage = analyzeCareerPage($entry['career_url']);

    $accessibleProbes = array_values(array_filter($probes, static fn (array $p): bool => ($p['accessible'] ?? false) && (($p['total_jobs'] ?? 0) > 0)));
    usort($accessibleProbes, static function (array $a, array $b): int {
        return ((int) ($b['turkey_jobs'] ?? 0)) <=> ((int) ($a['turkey_jobs'] ?? 0));
    });
    $best = $accessibleProbes[0] ?? ($probes[0] ?? []);

    $seeded = (bool) ($entry['seeded'] ?? false);
    $confidence = confidenceFromProbe($best, $seeded);
    $action = $seeded ? 'EXISTING' : actionFromProbe($best, false, $careerPage);

    $record = [
        'company_name' => $entry['name'],
        'category' => $entry['category'],
        'website' => $entry['website'],
        'career_page_url' => $entry['career_url'],
        'active_in_turkey' => true,
        'has_active_openings' => ($best['total_jobs'] ?? 0) > 0 || ($careerPage['json_ld_jobposting_count'] ?? 0) > 0,
        'approx_active_jobs' => (int) ($best['total_jobs'] ?? 0),
        'turkey_jobs' => (int) ($best['turkey_jobs'] ?? 0),
        'istanbul_jobs' => (int) ($best['istanbul_jobs'] ?? 0),
        'career_platform' => ($best['provider'] ?? null) ?: ($careerPage['detected_platform'] ?? 'unknown'),
        'existing_provider_supported' => in_array($best['provider'] ?? '', ['lever', 'greenhouse', 'workable', 'ashby'], true),
        'new_provider_required' => ! in_array($best['provider'] ?? '', ['lever', 'greenhouse', 'workable', 'ashby', 'unknown', 'custom'], true)
            && ($best['accessible'] ?? false),
        'career_page_analysis' => $careerPage,
        'all_probes' => $probes,
        'best_probe' => $best ?: null,
        'sustainability' => ($best['accessible'] ?? false) ? 'high_api' : (($careerPage['json_ld_jobposting_count'] ?? 0) > 0 ? 'medium_structured' : 'low_or_blocked'),
        'global_pollution_risk' => ($best['total_jobs'] ?? 0) > 0
            ? round(100 - (100 * ((int) ($best['turkey_jobs'] ?? 0)) / max(1, (int) $best['total_jobs'])), 1)
            : null,
        'confidence' => $confidence,
        'recommended_action' => $action,
        'platform_group' => null,
        'source_value_score' => 0,
        'already_seeded' => $seeded,
    ];
    $record['platform_group'] = platformGroup($record);
    $record['source_value_score'] = sourceValueScore($record);
    $results[] = $record;

    usleep(PHASE_C_PROBE_DELAY_US);
}

usort($results, static fn (array $a, array $b): int => ($b['source_value_score'] ?? 0) <=> ($a['source_value_score'] ?? 0));

$summary = [
    'companies_researched' => count($results),
    'active_career_sources' => count(array_filter($results, static fn (array $r): bool => $r['has_active_openings'])),
    'existing_provider_ready' => count(array_filter($results, static fn (array $r): bool => in_array($r['recommended_action'], ['SEED_NOW', 'EXISTING_PROVIDER_SUPPORT', 'EXISTING'], true))),
    'new_generic_provider_needed' => count(array_filter($results, static fn (array $r): bool => $r['recommended_action'] === 'NEW_ADAPTER_CANDIDATE')),
    'custom_adapter_needed' => count(array_filter($results, static fn (array $r): bool => $r['platform_group'] === 'D')),
    'rejected' => count(array_filter($results, static fn (array $r): bool => $r['recommended_action'] === 'REJECT')),
    'seed_now' => count(array_filter($results, static fn (array $r): bool => $r['recommended_action'] === 'SEED_NOW')),
    'platform_groups' => array_count_values(array_column($results, 'platform_group')),
    'turkey_job_impact' => [
        'minimum_new_tr_jobs' => 0,
        'realistic_new_tr_jobs' => 0,
        'maximum_new_tr_jobs' => 0,
    ],
];

$unseeded = array_values(array_filter($results, static fn (array $r): bool => ! $r['already_seeded'] && $r['recommended_action'] !== 'REJECT'));
$trCandidates = array_values(array_filter($unseeded, static fn (array $r): bool => ($r['turkey_jobs'] ?? 0) > 0));
$summary['turkey_job_impact']['minimum_new_tr_jobs'] = count(array_filter($trCandidates, static fn (array $r): bool => $r['confidence'] === 'HIGH' && $r['recommended_action'] === 'SEED_NOW'))
    ? array_sum(array_map(static fn (array $r): int => min(3, (int) $r['turkey_jobs']), array_filter($trCandidates, static fn (array $r): bool => $r['recommended_action'] === 'SEED_NOW')))
    : 0;
$summary['turkey_job_impact']['realistic_new_tr_jobs'] = (int) round(array_sum(array_map(
    static fn (array $r): float => ($r['recommended_action'] === 'SEED_NOW' || $r['recommended_action'] === 'EXISTING_PROVIDER_SUPPORT')
        ? max(0, (int) $r['turkey_jobs'] * 0.7)
        : 0,
    $unseeded
)));
$summary['turkey_job_impact']['maximum_new_tr_jobs'] = (int) array_sum(array_map(
    static fn (array $r): int => in_array($r['recommended_action'], ['SEED_NOW', 'EXISTING_PROVIDER_SUPPORT', 'NEW_ADAPTER_CANDIDATE'], true)
        ? (int) $r['turkey_jobs']
        : 0,
    $unseeded
));

$output = [
    'generated_at' => now()->toIso8601String(),
    'mode' => 'read_only',
    'duration_seconds' => round(microtime(true) - $startedAt, 1),
    'summary' => $summary,
    'linkedin_integration_decision' => 'OFFICIAL_PARTNERSHIP_REQUIRED',
    'recommended_next_task' => 'Verify Papara/Getir/Hepsiburada actual ATS via manual career page inspection; implement SmartRecruiters adapter only if ≥3 TR companies confirmed on SR with ≥10 combined TR jobs',
    'architecture_decision' => 'OPTION_A_PRIMARY',
    'companies' => $results,
    'top_20_pipeline' => array_slice($results, 0, 20),
];

$outPath = base_path('storage/phase-c-discovery-output.json');
file_put_contents($outPath, json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n";
echo "\nWrote: {$outPath}\n";

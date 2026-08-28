<?php

declare(strict_types=1);

/**
 * Phase A.2 — READ-ONLY Turkey/Istanbul supply audit.
 * No DB writes. No imports.
 */

use App\Enums\JobOrigin;
use App\Enums\JobStatus;
use App\Models\Job;
use App\Models\JobSource;
use App\Services\Scraper\DTO\LocationInput;
use App\Services\Scraper\LocationClassificationService;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$classifier = new LocationClassificationService;

$now = now();
$publishedScope = static function ($query) use ($now): void {
    $query->where('status', JobStatus::Published)
        ->where(function ($inner) use ($now): void {
            $inner->whereNull('expires_at')->orWhere('expires_at', '>', $now);
        });
};

function isIstanbul(?string $city, ?string $country): bool
{
    $blob = mb_strtolower(trim(($city ?? '').' '.($country ?? '')));

    return str_contains($blob, 'istanbul') || str_contains($blob, 'i̇stanbul');
}

function isTurkey(?string $city, ?string $country): bool
{
    $blob = mb_strtolower(trim(($city ?? '').' '.($country ?? '')));

    return str_contains($blob, 'turkey')
        || str_contains($blob, 'türkiye')
        || str_contains($blob, 'turkiye')
        || isIstanbul($city, $country);
}

function relevanceLabel(int $total, int $turkey, int $istanbul): string
{
    if ($total === 0) {
        return 'EMPTY';
    }
    $pct = ($turkey / $total) * 100;
    if ($pct >= 70) {
        return 'HIGH';
    }
    if ($pct >= 30) {
        return 'MEDIUM';
    }
    if ($turkey >= 3 && $pct >= 10) {
        return 'MEDIUM';
    }

    return 'LOW';
}

function confidenceFromRelevance(string $relevance, int $total, int $turkey, bool $seeded): string
{
    if ($total === 0 || ! $seeded && $turkey === 0) {
        return 'REJECT';
    }
    if ($relevance === 'HIGH' && $turkey >= 3) {
        return 'HIGH_CONFIDENCE';
    }
    if ($relevance === 'MEDIUM' && $turkey >= 2 && ($turkey / max(1, $total)) >= 0.15) {
        return 'MEDIUM_CONFIDENCE';
    }
    if ($turkey >= 1 && ($turkey / max(1, $total)) < 0.15) {
        return 'REJECT';
    }

    return 'MEDIUM_CONFIDENCE';
}

// --- Part 1: existing sources from DB ---
$seededSlugs = [];
$existingSources = [];

foreach (JobSource::query()->orderBy('id')->get() as $source) {
    $slug = $source->config['site_slug'] ?? null;
    if ($slug !== null) {
        $seededSlugs[$slug] = true;
    }

    $jobs = Job::query()
        ->where('job_source_id', $source->id)
        ->where($publishedScope)
        ->get();

    $total = $jobs->count();
    $istanbul = 0;
    $turkey = 0;
    $turkeyRelevantSearch = 0;
    $other = 0;

    foreach ($jobs as $job) {
        $ist = isIstanbul($job->city, $job->country);
        $tr = isTurkey($job->city, $job->country);
        if ($ist) {
            $istanbul++;
        }
        if ($tr) {
            $turkey++;
        }
        if (! $tr) {
            $other++;
        }

        $classified = $classifier->classify(LocationInput::fromSignals(
            $job->city,
            $job->country,
            $job->work_type,
        ));
        if ($classified->isTurkeyRelevant) {
            $turkeyRelevantSearch++;
        }
    }

    $existingSources[] = [
        'id' => $source->id,
        'name' => $source->name,
        'provider' => $source->config['provider'] ?? 'unknown',
        'slug' => $slug,
        'seeded' => true,
        'total_jobs' => $total,
        'istanbul_jobs' => $istanbul,
        'turkey_jobs' => $turkey,
        'other_country_jobs' => $other,
        'turkey_relevant_search_scope' => $turkeyRelevantSearch,
        'turkey_relevance_pct' => $total > 0 ? round(($turkey / $total) * 100, 1) : 0,
        'relevance' => relevanceLabel($total, $turkey, $istanbul),
        'method' => 'DB published jobs + city/country match; search scope via LocationClassificationService',
    ];
}

// --- Part 2: live probe helpers ---
function probeLever(string $slug): array
{
    $url = 'https://api.lever.co/v0/postings/'.$slug.'?mode=json';
    $body = httpGet($url);
    if ($body === null) {
        return ['http_status' => 0, 'total' => 0, 'turkey' => 0, 'istanbul' => 0, 'error' => 'fetch failed'];
    }
    $data = json_decode($body, true);
    if (! is_array($data)) {
        return ['http_status' => 200, 'total' => 0, 'turkey' => 0, 'istanbul' => 0, 'error' => 'invalid json'];
    }
    $total = count($data);
    $turkey = 0;
    $istanbul = 0;
    foreach ($data as $job) {
        $loc = (string) ($job['categories']['location'] ?? '');
        if (isTurkey(null, $loc) || isTurkey($loc, null)) {
            $turkey++;
        }
        if (isIstanbul($loc, null)) {
            $istanbul++;
        }
    }

    return ['http_status' => 200, 'total' => $total, 'turkey' => $turkey, 'istanbul' => $istanbul, 'error' => null];
}

function probeGreenhouse(string $token): array
{
    $url = 'https://boards-api.greenhouse.io/v1/boards/'.$token.'/jobs?content=true';
    $body = httpGet($url);
    if ($body === null) {
        return ['http_status' => 0, 'total' => 0, 'turkey' => 0, 'istanbul' => 0, 'error' => 'fetch failed'];
    }
    $data = json_decode($body, true);
    if (! is_array($data) || ! isset($data['jobs'])) {
        return ['http_status' => 200, 'total' => 0, 'turkey' => 0, 'istanbul' => 0, 'error' => 'invalid json'];
    }
    $jobs = $data['jobs'];
    $total = count($jobs);
    $turkey = 0;
    $istanbul = 0;
    foreach ($jobs as $job) {
        $loc = (string) ($job['location']['name'] ?? '');
        if (isTurkey(null, $loc) || isTurkey($loc, null)) {
            $turkey++;
        }
        if (isIstanbul($loc, null)) {
            $istanbul++;
        }
    }

    return ['http_status' => 200, 'total' => $total, 'turkey' => $turkey, 'istanbul' => $istanbul, 'error' => null];
}

function probeWorkable(string $slug): array
{
    $url = 'https://apply.workable.com/api/v1/widget/accounts/'.$slug.'?details=true';
    $body = httpGet($url);
    if ($body === null) {
        return ['http_status' => 0, 'total' => 0, 'turkey' => 0, 'istanbul' => 0, 'error' => 'fetch failed'];
    }
    $data = json_decode($body, true);
    if (! is_array($data) || ! isset($data['jobs'])) {
        return ['http_status' => 200, 'total' => 0, 'turkey' => 0, 'istanbul' => 0, 'error' => 'invalid json'];
    }
    $jobs = $data['jobs'];
    $total = count($jobs);
    $turkey = 0;
    $istanbul = 0;
    foreach ($jobs as $job) {
        $city = (string) ($job['location']['city'] ?? '');
        $country = (string) ($job['location']['country'] ?? '');
        if (isTurkey($city, $country)) {
            $turkey++;
        }
        if (isIstanbul($city, $country)) {
            $istanbul++;
        }
    }

    return ['http_status' => 200, 'total' => $total, 'turkey' => $turkey, 'istanbul' => $istanbul, 'error' => null];
}

function probeAshby(string $board): array
{
    $url = 'https://api.ashbyhq.com/posting-api/job-board/'.$board;
    $body = httpGet($url.'?includeCompensation=true');
    if ($body === null) {
        return ['http_status' => 0, 'total' => 0, 'turkey' => 0, 'istanbul' => 0, 'error' => 'fetch failed'];
    }
    $data = json_decode($body, true);
    if (! is_array($data) || ! isset($data['jobs'])) {
        return ['http_status' => 200, 'total' => 0, 'turkey' => 0, 'istanbul' => 0, 'error' => 'invalid json'];
    }
    $jobs = array_values(array_filter($data['jobs'], static fn ($j) => is_array($j) && ($j['isListed'] ?? true)));
    $total = count($jobs);
    $turkey = 0;
    $istanbul = 0;
    foreach ($jobs as $job) {
        $addr = is_array($job['address'] ?? null) ? $job['address'] : [];
        $postal = is_array($addr['postalAddress'] ?? null) ? $addr['postalAddress'] : [];
        $city = (string) ($postal['addressLocality'] ?? ($job['locationName'] ?? ''));
        $country = (string) ($postal['addressCountry'] ?? '');
        $loc = (string) ($job['locationName'] ?? '');
        if (isTurkey($city, $country) || isTurkey($loc, null)) {
            $turkey++;
        }
        if (isIstanbul($city, $country) || isIstanbul($loc, null)) {
            $istanbul++;
        }
    }

    return ['http_status' => 200, 'total' => $total, 'turkey' => $turkey, 'istanbul' => $istanbul, 'error' => null];
}

function httpGet(string $url): ?string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            'User-Agent: FitCareer/1.0 (phase-a2-readonly-audit)',
            'Accept: application/json',
        ],
    ]);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ($body !== false && $status === 200) ? $body : null;
}

/** @var list<array{company:string,provider:string,slug:string,seeded:bool}> */
$candidateBoards = [
    // Unseeded — Lever
    ['company' => 'Ajax Systems', 'provider' => 'lever', 'slug' => 'ajax', 'seeded' => false],
    ['company' => 'Binance', 'provider' => 'lever', 'slug' => 'binance', 'seeded' => false],
    ['company' => 'Peak Games', 'provider' => 'lever', 'slug' => 'peakgames', 'seeded' => false],
    // Unseeded — Greenhouse
    ['company' => 'GitLab', 'provider' => 'greenhouse', 'slug' => 'gitlab', 'seeded' => false],
    ['company' => 'RZR Global', 'provider' => 'greenhouse', 'slug' => 'rzr', 'seeded' => false],
    ['company' => 'Udemy', 'provider' => 'greenhouse', 'slug' => 'udemy', 'seeded' => false],
    ['company' => 'Wargaming', 'provider' => 'greenhouse', 'slug' => 'wargamingen', 'seeded' => false],
    // Unseeded — Workable
    ['company' => 'RateHawk', 'provider' => 'workable', 'slug' => 'ratehawk', 'seeded' => false],
    ['company' => 'Teltonika', 'provider' => 'workable', 'slug' => 'teltonika', 'seeded' => false],
    ['company' => 'D-ploy', 'provider' => 'workable', 'slug' => 'd-ploy', 'seeded' => false],
    ['company' => 'Intellect', 'provider' => 'workable', 'slug' => 'intellecthq', 'seeded' => false],
    ['company' => 'Intuition Machines', 'provider' => 'workable', 'slug' => 'intuition-machines', 'seeded' => false],
    ['company' => 'Volt Lines', 'provider' => 'workable', 'slug' => 'voltlines', 'seeded' => false],
    ['company' => 'Teachers In Turkey', 'provider' => 'workable', 'slug' => 'teachers-in-turkey', 'seeded' => false],
    // Ashby probes (Turkey companies on wrong ATS or unseeded)
    ['company' => 'Getir (Ashby probe)', 'provider' => 'ashby', 'slug' => 'getir', 'seeded' => false],
    ['company' => 'iyzico (Ashby probe)', 'provider' => 'ashby', 'slug' => 'iyzico', 'seeded' => false],
    ['company' => 'Peak Games (Ashby probe)', 'provider' => 'ashby', 'slug' => 'peakgames', 'seeded' => false],
    ['company' => 'Papara (Ashby probe)', 'provider' => 'ashby', 'slug' => 'papara', 'seeded' => false],
    ['company' => 'Hepsiburada (Ashby probe)', 'provider' => 'ashby', 'slug' => 'hepsiburada', 'seeded' => false],
    ['company' => 'Logo Yazılım (Ashby probe)', 'provider' => 'ashby', 'slug' => 'logo', 'seeded' => false],
    // Global boards — pollution risk reference
    ['company' => 'Stripe (Greenhouse)', 'provider' => 'greenhouse', 'slug' => 'stripe', 'seeded' => false],
    ['company' => 'Figma (Greenhouse)', 'provider' => 'greenhouse', 'slug' => 'figma', 'seeded' => false],
];

$candidates = [];
foreach ($candidateBoards as $board) {
    if (isset($seededSlugs[$board['slug']]) && ! $board['seeded']) {
        // slug collision with seeded source — skip duplicate probe label
    }

    $probe = match ($board['provider']) {
        'lever' => probeLever($board['slug']),
        'greenhouse' => probeGreenhouse($board['slug']),
        'workable' => probeWorkable($board['slug']),
        'ashby' => probeAshby($board['slug']),
        default => ['http_status' => 0, 'total' => 0, 'turkey' => 0, 'istanbul' => 0, 'error' => 'unknown'],
    };

    $total = (int) $probe['total'];
    $turkey = (int) $probe['turkey'];
    $istanbul = (int) $probe['istanbul'];
    $rel = relevanceLabel($total, $turkey, $istanbul);

    $candidates[] = [
        'company' => $board['company'],
        'provider' => $board['provider'],
        'slug' => $board['slug'],
        'already_seeded' => isset($seededSlugs[$board['slug']]),
        'http_ok' => $probe['http_status'] === 200 && $probe['error'] === null,
        'total_available' => $total,
        'turkey_jobs' => $turkey,
        'istanbul_jobs' => $istanbul,
        'other_country_jobs' => max(0, $total - $turkey),
        'turkey_relevance_pct' => $total > 0 ? round(($turkey / $total) * 100, 1) : 0,
        'relevance' => $rel,
        'confidence' => confidenceFromRelevance($rel, $total, $turkey, isset($seededSlugs[$board['slug']])),
        'import_ready_no_code' => in_array($board['provider'], ['lever', 'greenhouse', 'workable', 'ashby'], true),
        'method' => 'Live API probe '.date('c'),
        'error' => $probe['error'],
    ];
}

// Aggregate totals
$publishedTotal = Job::query()->where($publishedScope)->count();
$istanbulTotal = Job::query()->where($publishedScope)->where(function ($q): void {
    $q->whereRaw('LOWER(city) LIKE ?', ['%istanbul%']);
})->count();
$turkeyTotal = Job::query()->where($publishedScope)->where(function ($q): void {
    $q->whereRaw('LOWER(country) LIKE ?', ['%turk%']);
})->count();

$globalPollutionSources = array_values(array_filter($existingSources, static fn ($s): bool => $s['total_jobs'] >= 10 && $s['turkey_relevance_pct'] < 20));

$output = [
    'generated_at' => now()->toIso8601String(),
    'audit_type' => 'read_only_phase_a2',
    'inventory_summary' => [
        'published_jobs' => $publishedTotal,
        'active_sources' => JobSource::where('is_active', true)->count(),
        'istanbul_published' => $istanbulTotal,
        'turkey_country_published' => $turkeyTotal,
        'method' => 'DB query on published jobs',
    ],
    'existing_sources' => $existingSources,
    'global_pollution_sources' => $globalPollutionSources,
    'candidate_boards' => $candidates,
    'provider_readiness' => [
        'lever' => ['seed_ready' => true, 'ingest_filter' => 'none at fetch; max_posting_age_days at normalize'],
        'greenhouse' => ['seed_ready' => true, 'ingest_filter' => 'none at fetch; imports entire board'],
        'workable' => ['seed_ready' => true, 'ingest_filter' => 'none at fetch; single widget response'],
        'ashby' => ['seed_ready' => true, 'ingest_filter' => 'none at fetch; all Category-A boards seeded'],
        'remotive' => ['seed_ready' => true, 'ingest_filter' => 'API feed ~17 jobs; low TR ROI'],
        'kariyer-net' => ['seed_ready' => false, 'ingest_filter' => 'HTTP 403 PerimeterX — blocked'],
    ],
    'unsupported_architecture' => [
        'linkedin' => 'No public job API; scraping violates ToS; not in scope',
        'kariyer_net' => 'HTML + PerimeterX; official API/partnership required',
        'company_career_pages' => 'No generic adapter unless ATS detected (Lever/GH/Workable/Ashby)',
    ],
];

echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL;

<?php

declare(strict_types=1);

/**
 * Phase F.1 read-only targeted tech/junior employer discovery.
 * Uses only existing providers. NO DB writes.
 */

require __DIR__.'/ats-coverage-discovery-helpers.php';
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Job;
use App\Models\JobSource;

const F1_DELAY_US = 180_000;
const F1_MAX_LEVER_PAGES = 3;
const F1_LEVER_PAGE_SIZE = 100;

function f1Norm(string $text): string
{
    $text = mb_strtolower($text);
    $text = str_replace(['ı', 'İ', 'ş', 'Ş', 'ğ', 'Ğ', 'ü', 'Ü', 'ö', 'Ö', 'ç', 'Ç', 'i̇'], ['i', 'i', 's', 's', 'g', 'g', 'u', 'u', 'o', 'o', 'c', 'c', 'i'], $text);

    return $text;
}

function f1TitleMatches(string $title, array $patterns): bool
{
    $blob = ' '.f1Norm($title).' ';
    foreach ($patterns as $pattern) {
        if (str_contains($blob, f1Norm($pattern))) {
            return true;
        }
    }

    return false;
}

function f1PersonaCounts(array $titles): array
{
    $junior = [' intern', 'internship', 'stajyer', 'staj', 'junior', 'jr ', 'entry level', 'entry-level', 'yeni mezun', 'trainee', 'graduate', 'new grad'];
    $frontend = ['frontend', 'front-end', 'front end', 'react', 'vue', 'angular', 'javascript developer', 'ui developer'];
    $qa = [' qa', 'quality assurance', 'test engineer', 'sdet', 'test automation', 'software tester', 'quality engineer'];
    $devops = ['devops', 'sre', 'site reliability', 'platform engineer', 'cloud engineer', 'kubernetes', 'aws engineer', 'azure engineer'];

    $counts = ['junior' => 0, 'frontend' => 0, 'qa' => 0, 'devops' => 0];
    foreach ($titles as $title) {
        if (f1TitleMatches($title, $junior)) {
            $counts['junior']++;
        }
        if (f1TitleMatches($title, $frontend)) {
            $counts['frontend']++;
        }
        if (f1TitleMatches($title, $qa)) {
            $counts['qa']++;
        }
        if (f1TitleMatches($title, $devops)) {
            $counts['devops']++;
        }
    }

    return $counts;
}

function f1IsStaffing(string $name): bool
{
    $blob = f1Norm($name);

    return str_contains($blob, 'agency')
        || str_contains($blob, 'staffing')
        || str_contains($blob, 'consultancy')
        || str_contains($blob, 'recruit')
        || str_contains($blob, 'outsourcing');
}

/** @return list<array<string,mixed>> */
function f1ExistingCoverage(): array
{
    $coverage = [];
    foreach (JobSource::query()->get() as $source) {
        $coverage[] = [
            'name' => $source->name,
            'provider' => (string) ($source->config['provider'] ?? ''),
            'site_slug' => (string) ($source->config['site_slug'] ?? ''),
            'is_active' => (bool) $source->is_active,
            'published_jobs' => Job::where('job_source_id', $source->id)->count(),
        ];
    }

    return $coverage;
}

function f1DuplicateStatus(string $company, string $provider, string $slug, array $coverage): string
{
    $normName = preg_replace('/[^a-z0-9]+/', '', f1Norm($company)) ?? '';
    $normSlug = preg_replace('/[^a-z0-9]+/', '', f1Norm($slug)) ?? '';

    foreach ($coverage as $row) {
        $rowProvider = (string) ($row['provider'] ?? '');
        if (in_array($rowProvider, ['remotive', 'kariyer-net'], true)) {
            continue;
        }
        $rowSlug = preg_replace('/[^a-z0-9]+/', '', f1Norm((string) $row['site_slug'])) ?? '';
        $rowName = preg_replace('/[^a-z0-9]+/', '', f1Norm((string) $row['name'])) ?? '';
        if ($normSlug !== '' && $normSlug === $rowSlug) {
            return 'DUPLICATE_SLUG:'.$rowProvider.':'.$row['name'];
        }
        if ($normName !== '' && $normName === $rowName) {
            return 'DUPLICATE_EMPLOYER:'.$rowProvider.':'.$row['name'];
        }
    }

    return 'NET_NEW';
}

function f1LeverJobs(string $slug): array
{
    $bases = [
        "https://api.lever.co/v0/postings/{$slug}",
        "https://api.eu.lever.co/v0/postings/{$slug}",
    ];
    $jobs = [];
    $httpStatus = null;
    $resolved = null;

    for ($page = 0; $page < F1_MAX_LEVER_PAGES; $page++) {
        $skip = $page * F1_LEVER_PAGE_SIZE;
        $pageJobs = null;
        foreach ($bases as $base) {
            if ($page > 0 && $resolved !== null && $base !== $resolved) {
                continue;
            }
            $response = httpGetJson($base, ['mode' => 'json', 'skip' => $skip, 'limit' => F1_LEVER_PAGE_SIZE]);
            $httpStatus = $response['http_status'];
            if ($httpStatus === 200 && is_array($response['json']) && array_is_list($response['json'])) {
                $resolved = $base;
                $pageJobs = $response['json'];
                break;
            }
        }
        if (! is_array($pageJobs)) {
            break;
        }
        $jobs = array_merge($jobs, $pageJobs);
        if (count($pageJobs) < F1_LEVER_PAGE_SIZE) {
            break;
        }
    }

    return ['http_status' => $httpStatus, 'jobs' => $jobs];
}

function f1ExtractTitlesAndBlobs(string $provider, array $jobs): array
{
    $titles = [];
    $trTitles = [];
    $istanbul = 0;
    $turkey = 0;
    $locationQualityHits = 0;

    foreach ($jobs as $job) {
        if (! is_array($job)) {
            continue;
        }
        $title = match ($provider) {
            'lever' => (string) ($job['text'] ?? ''),
            default => (string) ($job['title'] ?? ''),
        };
        $blob = match ($provider) {
            'lever' => locationBlobFromParts([
                $job['categories']['location'] ?? null,
                $job['categories']['allLocations'] ?? null,
                $job['country'] ?? null,
                $job['workplaceType'] ?? null,
            ]),
            'greenhouse' => locationBlobFromParts([
                $job['location']['name'] ?? null,
                $job['offices'] ?? null,
                $job['location'] ?? null,
            ]),
            'workable' => locationBlobFromParts([
                $job['location'] ?? null,
                $job['city'] ?? null,
                $job['country'] ?? null,
                $job['locations'] ?? null,
                $job['workplace'] ?? null,
                $job['telecommuting'] ?? null,
            ]),
            'ashby' => locationBlobFromParts([
                $job['location'] ?? null,
                $job['secondaryLocations'] ?? null,
                $job['isRemote'] ?? null,
                $job['workplaceType'] ?? null,
                $job['address'] ?? null,
            ]),
            'recruitee' => locationBlobFromParts([
                $job['city'] ?? null,
                $job['country'] ?? null,
                $job['country_code'] ?? null,
                $job['location'] ?? null,
                $job['locations'] ?? null,
                $job['remote'] ?? null,
                $job['hybrid'] ?? null,
            ]),
            default => '',
        };

        $titles[] = $title;
        $class = classifyLocation($blob);
        $hasLocation = trim($blob) !== '' && $blob !== 'null' && $blob !== '[]';
        if ($hasLocation) {
            $locationQualityHits++;
        }
        if (in_array($class, ['istanbul', 'turkey', 'remote_turkey'], true)) {
            $turkey++;
            $trTitles[] = $title;
            if ($class === 'istanbul') {
                $istanbul++;
            }
        }
    }

    $total = count($jobs);
    $quality = $total === 0 ? 'LOW' : (($locationQualityHits / $total) >= 0.8 ? 'HIGH' : (($locationQualityHits / $total) >= 0.4 ? 'MEDIUM' : 'LOW'));

    return [
        'titles' => $titles,
        'tr_titles' => $trTitles,
        'turkey' => $turkey,
        'istanbul' => $istanbul,
        'location_quality' => $quality,
    ];
}

function f1ClassifyCandidate(array $row): string
{
    if ($row['duplicate_status'] !== 'NET_NEW') {
        return 'REJECT';
    }
    if (! $row['accessible'] || $row['total_jobs'] === 0) {
        return 'REJECT';
    }
    if ($row['staffing']) {
        return 'REJECT';
    }
    if ($row['location_quality'] === 'LOW') {
        return 'REJECT';
    }

    $total = max(1, $row['total_jobs']);
    $trShare = $row['turkey_jobs'] / $total;
    if ($row['total_jobs'] >= 40 && $trShare < 0.10) {
        return 'REJECT';
    }

    $criticalHits = $row['junior_jobs'] + $row['frontend_jobs'] + $row['qa_jobs'] + $row['devops_jobs'];
    $meetsA = $row['turkey_jobs'] >= 3;
    $meetsB = $criticalHits >= 1 && $row['turkey_jobs'] >= 1;

    if ($meetsA || $meetsB) {
        if ($row['turkey_jobs'] >= 3 && $criticalHits >= 1 && $row['location_quality'] === 'HIGH') {
            return 'SEED_NOW';
        }
        if ($meetsA && $row['location_quality'] === 'HIGH' && $trShare >= 0.25) {
            return 'SEED_NOW';
        }

        return 'MANUAL_REVIEW';
    }

    return 'REJECT';
}

$candidates = [
    // Lever — deferred / unseeded Turkish tech
    ['company' => 'Teknasyon', 'provider' => 'lever', 'slug' => 'teknasyon'],
    ['company' => 'Rollic', 'provider' => 'lever', 'slug' => 'rollic'],
    ['company' => 'MagicLab', 'provider' => 'lever', 'slug' => 'magiclab'],
    ['company' => 'Figopara', 'provider' => 'lever', 'slug' => 'figopara'],
    ['company' => 'Peak Games', 'provider' => 'lever', 'slug' => 'peakgames'],
    ['company' => 'Kolay Gelsin', 'provider' => 'lever', 'slug' => 'kolaygelsin'],
    ['company' => 'Ozan', 'provider' => 'lever', 'slug' => 'ozan'],
    ['company' => 'Invictus Games', 'provider' => 'lever', 'slug' => 'invictusgames'],
    ['company' => 'Sahibinden', 'provider' => 'lever', 'slug' => 'sahibinden'],
    ['company' => 'Yemeksepeti', 'provider' => 'lever', 'slug' => 'yemeksepeti'],
    ['company' => 'Flo', 'provider' => 'lever', 'slug' => 'flo'],
    ['company' => 'Jotform', 'provider' => 'lever', 'slug' => 'jotform'],
    ['company' => 'Ticimax', 'provider' => 'lever', 'slug' => 'ticimax'],
    ['company' => 'OBSS', 'provider' => 'lever', 'slug' => 'obss'],
    ['company' => 'Etiya', 'provider' => 'lever', 'slug' => 'etiya'],
    ['company' => 'Softtech', 'provider' => 'lever', 'slug' => 'softtech'],
    ['company' => 'Intertech', 'provider' => 'lever', 'slug' => 'intertech'],
    ['company' => 'Hangikredi', 'provider' => 'lever', 'slug' => 'hangikredi'],
    ['company' => 'Param', 'provider' => 'lever', 'slug' => 'param'],
    ['company' => 'Craftgate', 'provider' => 'lever', 'slug' => 'craftgate'],
    ['company' => 'Kolay IK', 'provider' => 'lever', 'slug' => 'kolayik'],
    ['company' => 'BiTaksi', 'provider' => 'lever', 'slug' => 'bitaksi'],
    ['company' => 'Macellan', 'provider' => 'lever', 'slug' => 'macellan'],
    ['company' => 'Infina', 'provider' => 'lever', 'slug' => 'infina'],
    ['company' => 'Netas', 'provider' => 'lever', 'slug' => 'netas'],
    ['company' => 'Papara', 'provider' => 'lever', 'slug' => 'papara'],

    // Greenhouse — unseeded, Turkey-relevant history or TR tech
    ['company' => 'RZR Global', 'provider' => 'greenhouse', 'slug' => 'rzr'],
    ['company' => 'Udemy', 'provider' => 'greenhouse', 'slug' => 'udemy'],
    ['company' => 'Delivery Hero', 'provider' => 'greenhouse', 'slug' => 'deliveryhero'],
    ['company' => 'Wise', 'provider' => 'greenhouse', 'slug' => 'wise'],
    ['company' => 'Unity', 'provider' => 'greenhouse', 'slug' => 'unity3d'],
    ['company' => 'Wargaming', 'provider' => 'greenhouse', 'slug' => 'wargamingen'],
    ['company' => 'Boku', 'provider' => 'greenhouse', 'slug' => 'boku'],
    ['company' => 'Peak Games', 'provider' => 'greenhouse', 'slug' => 'peakgames'],
    ['company' => 'Teknasyon', 'provider' => 'greenhouse', 'slug' => 'teknasyon'],
    ['company' => 'Jotform', 'provider' => 'greenhouse', 'slug' => 'jotform'],
    ['company' => 'Sahibinden', 'provider' => 'greenhouse', 'slug' => 'sahibinden'],

    // Workable — unseeded Turkish tech
    ['company' => 'Ticimax', 'provider' => 'workable', 'slug' => 'ticimax'],
    ['company' => 'Jotform', 'provider' => 'workable', 'slug' => 'jotform'],
    ['company' => 'Hangikredi', 'provider' => 'workable', 'slug' => 'hangikredi'],
    ['company' => 'Param', 'provider' => 'workable', 'slug' => 'param'],
    ['company' => 'Kolay IK', 'provider' => 'workable', 'slug' => 'kolayik'],
    ['company' => 'Craftgate', 'provider' => 'workable', 'slug' => 'craftgate'],
    ['company' => 'OBSS', 'provider' => 'workable', 'slug' => 'obss'],
    ['company' => 'Etiya', 'provider' => 'workable', 'slug' => 'etiya'],
    ['company' => 'Softtech', 'provider' => 'workable', 'slug' => 'softtech'],
    ['company' => 'Intertech', 'provider' => 'workable', 'slug' => 'intertech'],
    ['company' => 'Teknasyon', 'provider' => 'workable', 'slug' => 'teknasyon'],
    ['company' => 'Rollic', 'provider' => 'workable', 'slug' => 'rollic'],
    ['company' => 'Infina', 'provider' => 'workable', 'slug' => 'infina'],
    ['company' => 'Macellan', 'provider' => 'workable', 'slug' => 'macellan'],
    ['company' => 'Shopside', 'provider' => 'workable', 'slug' => 'shopside'],
    ['company' => 'Emukellef', 'provider' => 'workable', 'slug' => 'emukellef'],
    ['company' => 'BiTaksi', 'provider' => 'workable', 'slug' => 'bitaksi'],
    ['company' => 'Moka', 'provider' => 'workable', 'slug' => 'mokaunitedpayment'],
    ['company' => 'Flo', 'provider' => 'workable', 'slug' => 'flo'],
    ['company' => 'Sahibinden', 'provider' => 'workable', 'slug' => 'sahibinden'],
    ['company' => 'RateHawk', 'provider' => 'workable', 'slug' => 'ratehawk'],
    ['company' => 'Teltonika', 'provider' => 'workable', 'slug' => 'teltonika'],

    // Ashby — unseeded Turkish / gaming-tech
    ['company' => 'Rollic', 'provider' => 'ashby', 'slug' => 'rollic'],
    ['company' => 'Teknasyon', 'provider' => 'ashby', 'slug' => 'teknasyon'],
    ['company' => 'Peak Games', 'provider' => 'ashby', 'slug' => 'peakgames'],
    ['company' => 'Jotform', 'provider' => 'ashby', 'slug' => 'jotform'],
    ['company' => 'MagicLab', 'provider' => 'ashby', 'slug' => 'magiclab'],
    ['company' => 'SuperPlay', 'provider' => 'ashby', 'slug' => 'superplay'],
    ['company' => 'Moon Active', 'provider' => 'ashby', 'slug' => 'moonactive'],
    ['company' => 'Ticimax', 'provider' => 'ashby', 'slug' => 'ticimax'],
    ['company' => 'Hangikredi', 'provider' => 'ashby', 'slug' => 'hangikredi'],
    ['company' => 'Craftgate', 'provider' => 'ashby', 'slug' => 'craftgate'],

    // Recruitee — deferred + new TR tech slugs
    ['company' => 'Kodland', 'provider' => 'recruitee', 'slug' => 'kodland'],
    ['company' => 'TechBiz Global', 'provider' => 'recruitee', 'slug' => 'techbizglobal'],
    ['company' => 'Ticimax', 'provider' => 'recruitee', 'slug' => 'ticimax'],
    ['company' => 'Jotform', 'provider' => 'recruitee', 'slug' => 'jotform'],
    ['company' => 'Shopside', 'provider' => 'recruitee', 'slug' => 'shopside'],
    ['company' => 'Emukellef', 'provider' => 'recruitee', 'slug' => 'emukellef'],
    ['company' => 'Macellan', 'provider' => 'recruitee', 'slug' => 'macellan'],
    ['company' => 'Infina', 'provider' => 'recruitee', 'slug' => 'infina'],
    ['company' => 'Hangikredi', 'provider' => 'recruitee', 'slug' => 'hangikredi'],
    ['company' => 'Sipay', 'provider' => 'recruitee', 'slug' => 'sipay'],
    ['company' => 'OBSS', 'provider' => 'recruitee', 'slug' => 'obss'],
    ['company' => 'Etiya', 'provider' => 'recruitee', 'slug' => 'etiya'],
    ['company' => 'DFDS', 'provider' => 'recruitee', 'slug' => 'dfds'],
    ['company' => 'Pisano', 'provider' => 'recruitee', 'slug' => 'pisano'],
];

$dbBefore = ['jobs' => Job::count(), 'job_sources' => JobSource::count()];
$coverage = f1ExistingCoverage();
$results = [];

foreach ($candidates as $i => $candidate) {
    $provider = $candidate['provider'];
    $slug = $candidate['slug'];
    $company = $candidate['company'];

    $duplicate = f1DuplicateStatus($company, $provider, $slug, $coverage);

    $fetch = match ($provider) {
        'lever' => f1LeverJobs($slug),
        'greenhouse' => (static function (string $slug): array {
            $response = httpGetJson('https://boards-api.greenhouse.io/v1/boards/'.$slug.'/jobs', ['content' => 'true']);
            $jobs = is_array($response['json']['jobs'] ?? null) ? $response['json']['jobs'] : [];

            return ['http_status' => $response['http_status'], 'jobs' => $jobs];
        })($slug),
        'workable' => (static function (string $slug): array {
            $response = httpGetJson('https://apply.workable.com/api/v1/widget/accounts/'.$slug, ['details' => 'true']);
            $jobs = is_array($response['json']['jobs'] ?? null) ? $response['json']['jobs'] : [];

            return ['http_status' => $response['http_status'], 'jobs' => $jobs];
        })($slug),
        'ashby' => (static function (string $slug): array {
            $response = httpGetJson('https://api.ashbyhq.com/posting-api/job-board/'.$slug);
            $jobs = is_array($response['json']['jobs'] ?? null) ? $response['json']['jobs'] : [];

            return ['http_status' => $response['http_status'], 'jobs' => $jobs];
        })($slug),
        'recruitee' => (static function (string $slug): array {
            $response = httpGetJson('https://'.$slug.'.recruitee.com/api/offers');
            $jobs = is_array($response['json']['offers'] ?? null) ? $response['json']['offers'] : [];

            return ['http_status' => $response['http_status'], 'jobs' => $jobs];
        })($slug),
        default => ['http_status' => null, 'jobs' => []],
    };

    $httpStatus = $fetch['http_status'];
    $jobs = $fetch['jobs'];
    $extracted = f1ExtractTitlesAndBlobs($provider, $jobs);
    $persona = f1PersonaCounts($extracted['tr_titles']);
    $accessible = $httpStatus === 200 && $jobs !== [];

    $row = [
        'company' => $company,
        'provider' => $provider,
        'slug' => $slug,
        'http_status' => $httpStatus,
        'accessible' => $accessible,
        'total_jobs' => count($jobs),
        'turkey_jobs' => $extracted['turkey'],
        'istanbul_jobs' => $extracted['istanbul'],
        'junior_jobs' => $persona['junior'],
        'frontend_jobs' => $persona['frontend'],
        'qa_jobs' => $persona['qa'],
        'devops_jobs' => $persona['devops'],
        'duplicate_status' => $duplicate,
        'location_quality' => $extracted['location_quality'],
        'staffing' => f1IsStaffing($company),
        'sample_tr_titles' => array_slice($extracted['tr_titles'], 0, 8),
        'pollution_risk' => count($jobs) > 0 ? round((count($jobs) - $extracted['turkey']) / count($jobs), 3) : 0,
        'confidence' => $httpStatus === 200 ? 'HIGH' : 'LOW',
    ];
    $row['decision'] = f1ClassifyCandidate($row);
    $results[] = $row;

    usleep(F1_DELAY_US);
    echo sprintf(
        "[%d/%d] %s/%s %s HTTP=%s total=%d TR=%d IST=%d jr=%d fe=%d qa=%d devops=%d => %s\n",
        $i + 1,
        count($candidates),
        $provider,
        $slug,
        $company,
        (string) $httpStatus,
        $row['total_jobs'],
        $row['turkey_jobs'],
        $row['istanbul_jobs'],
        $row['junior_jobs'],
        $row['frontend_jobs'],
        $row['qa_jobs'],
        $row['devops_jobs'],
        $row['decision'],
    );
}

$dbAfter = ['jobs' => Job::count(), 'job_sources' => JobSource::count()];

$byDecision = ['SEED_NOW' => [], 'MANUAL_REVIEW' => [], 'REJECT' => []];
foreach ($results as $row) {
    $byDecision[$row['decision']][] = $row;
}

$output = [
    'audit' => [
        'title' => 'Phase F.1 targeted tech employer discovery',
        'generated_at' => now()->toIso8601String(),
        'scope' => 'read_only',
    ],
    'database_integrity' => [
        'before' => $dbBefore,
        'after' => $dbAfter,
        'writes' => ($dbBefore['jobs'] !== $dbAfter['jobs'] || $dbBefore['job_sources'] !== $dbAfter['job_sources']) ? 1 : 0,
    ],
    'counts' => [
        'employers_checked' => count($results),
        'seed_now' => count($byDecision['SEED_NOW']),
        'manual_review' => count($byDecision['MANUAL_REVIEW']),
        'reject' => count($byDecision['REJECT']),
    ],
    'seed_now' => $byDecision['SEED_NOW'],
    'manual_review' => $byDecision['MANUAL_REVIEW'],
    'reject' => $byDecision['REJECT'],
    'all' => $results,
];

$outPath = __DIR__.'/../storage/phase-f1-tech-employer-discovery.json';
file_put_contents($outPath, json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

echo "\nSEED_NOW: ".count($byDecision['SEED_NOW'])."\n";
echo 'MANUAL_REVIEW: '.count($byDecision['MANUAL_REVIEW'])."\n";
echo 'REJECT: '.count($byDecision['REJECT'])."\n";
echo 'DB writes: '.$output['database_integrity']['writes']."\n";
echo "Output: {$outPath}\n";

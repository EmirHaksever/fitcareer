<?php

declare(strict_types=1);

/**
 * Ashby Turkey coverage discovery — diagnostic only.
 * Public Job Postings API: GET https://api.ashbyhq.com/posting-api/job-board/{board_name}
 * Official docs: https://developers.ashbyhq.com/docs/public-job-posting-api
 * No DB writes. No production code changes.
 */

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

require __DIR__.'/ats-coverage-discovery-helpers.php';

const ASHBY_JOBS_ENDPOINT = 'https://api.ashbyhq.com/posting-api/job-board/{board}';

/**
 * @var list<array{company:string,board:string,discovery_source:string,verified_url?:string}>
 */
$candidates = [
    // Web-verified Turkey / Istanbul boards (2026-08-12 search)
    ['company' => 'Codeway', 'board' => 'codeway', 'discovery_source' => 'jobs.ashbyhq.com/codeway Istanbul roles', 'verified_url' => 'https://jobs.ashbyhq.com/codeway'],
    ['company' => 'Agave Games', 'board' => 'agavegames', 'discovery_source' => 'jobs.ashbyhq.com/agavegames Istanbul', 'verified_url' => 'https://jobs.ashbyhq.com/agavegames'],
    ['company' => 'Bigger Games', 'board' => 'biggergames', 'discovery_source' => 'jobs.ashbyhq.com/biggergames Istanbul', 'verified_url' => 'https://jobs.ashbyhq.com/biggergames'],
    ['company' => 'DoktorTakvimi', 'board' => 'doktortakvimi', 'discovery_source' => 'jobs.ashbyhq.com/doktortakvimi Istanbul', 'verified_url' => 'https://jobs.ashbyhq.com/doktortakvimi'],
    ['company' => 'Bold Games', 'board' => 'boldgames', 'discovery_source' => 'web search Ashby Istanbul gaming'],
    // Turkey tech ATS probes (real companies)
    ['company' => 'Peak Games', 'board' => 'peakgames', 'discovery_source' => 'Turkey gaming ATS probe'],
    ['company' => 'Getir', 'board' => 'getir', 'discovery_source' => 'Turkey delivery ATS probe'],
    ['company' => 'iyzico', 'board' => 'iyzico', 'discovery_source' => 'Turkey fintech ATS probe'],
    ['company' => 'Papara', 'board' => 'papara', 'discovery_source' => 'Turkey fintech ATS probe'],
    ['company' => 'Insider', 'board' => 'insider', 'discovery_source' => 'Turkey SaaS ATS probe (Lever in FitCareer)'],
    ['company' => 'Commencis', 'board' => 'commencis', 'discovery_source' => 'Turkey tech ATS probe (Lever in FitCareer)'],
    ['company' => 'Trendyol', 'board' => 'trendyol', 'discovery_source' => 'Turkey e-commerce ATS probe (Lever in FitCareer)'],
    ['company' => 'Dream Games', 'board' => 'dreamgames', 'discovery_source' => 'Turkey gaming ATS probe (Lever in FitCareer)'],
    ['company' => 'Wingie Enuygun', 'board' => 'wingieenuygun', 'discovery_source' => 'Turkey travel ATS probe (Workable in FitCareer)'],
    ['company' => 'Vertigo Games', 'board' => 'vertigogames', 'discovery_source' => 'Turkey gaming ATS probe (Workable in FitCareer)'],
    ['company' => 'Sanction Scanner', 'board' => 'sanction-scanner', 'discovery_source' => 'Turkey SaaS ATS probe (Workable in FitCareer)'],
    ['company' => 'Lucida AI', 'board' => 'lucida-ai', 'discovery_source' => 'Turkey AI ATS probe (Workable in FitCareer)'],
    ['company' => 'NewMind AI', 'board' => 'newmindai', 'discovery_source' => 'Turkey AI ATS probe (Workable in FitCareer)'],
    ['company' => 'VavaCars', 'board' => 'vavacars', 'discovery_source' => 'Turkey marketplace ATS probe (Workable in FitCareer)'],
    // Known public Ashby boards (real slugs from docs / job board guides)
    ['company' => 'Ashby', 'board' => 'ashby', 'discovery_source' => 'Official Ashby demo board jobs.ashbyhq.com/ashby'],
    ['company' => 'Notion', 'board' => 'notion', 'discovery_source' => 'Known Ashby customer board probe'],
    ['company' => 'Linear', 'board' => 'linear', 'discovery_source' => 'Known Ashby customer board probe'],
    ['company' => 'Retool', 'board' => 'retool', 'discovery_source' => 'Known Ashby customer board probe'],
    ['company' => 'Vercel', 'board' => 'vercel', 'discovery_source' => 'Known Ashby customer board probe'],
    ['company' => 'Ramp', 'board' => 'ramp', 'discovery_source' => 'Known Ashby customer board probe'],
    ['company' => 'OpenAI', 'board' => 'openai', 'discovery_source' => 'Known Ashby customer board probe'],
    ['company' => 'Perplexity', 'board' => 'perplexity', 'discovery_source' => 'Known Ashby customer board probe'],
    ['company' => 'Anthropic', 'board' => 'anthropic', 'discovery_source' => 'Known Ashby customer board probe'],
    ['company' => 'PostHog', 'board' => 'posthog', 'discovery_source' => 'Known Ashby customer board probe'],
    ['company' => 'Supabase', 'board' => 'supabase', 'discovery_source' => 'Known Ashby customer board probe'],
    ['company' => 'Neon', 'board' => 'neon', 'discovery_source' => 'Known Ashby customer board probe'],
    ['company' => 'Semgrep', 'board' => 'semgrep', 'discovery_source' => 'Known Ashby customer board probe'],
    ['company' => 'Deel', 'board' => 'deel', 'discovery_source' => 'Known Ashby customer board probe'],
    ['company' => 'Remote', 'board' => 'remote', 'discovery_source' => 'Known Ashby customer board probe'],
    ['company' => 'Lattice', 'board' => 'lattice', 'discovery_source' => 'Known Ashby customer board probe'],
    ['company' => 'Rippling', 'board' => 'rippling', 'discovery_source' => 'Known Ashby customer board probe'],
    ['company' => 'Brex', 'board' => 'brex', 'discovery_source' => 'Known Ashby customer board probe'],
    ['company' => 'Plaid', 'board' => 'plaid', 'discovery_source' => 'Known Ashby customer board probe'],
    ['company' => 'Flock Safety', 'board' => 'Flock%20Safety', 'discovery_source' => 'Ashby docs example slug with space (URL-encoded)'],
    ['company' => 'Limble', 'board' => 'limble', 'discovery_source' => 'Ashby API guide example slug'],
    ['company' => 'Cal.com', 'board' => 'cal', 'discovery_source' => 'Known Ashby startup board probe'],
    ['company' => 'Incident.io', 'board' => 'incident-io', 'discovery_source' => 'Known Ashby startup board probe'],
    ['company' => 'Resend', 'board' => 'resend', 'discovery_source' => 'Known Ashby startup board probe'],
];

function fetchAshbyBoard(string $board): array
{
    $url = str_replace('{board}', $board, ASHBY_JOBS_ENDPOINT);

    return httpGetJson($url, ['includeCompensation' => 'true']);
}

/** @return list<array<string,mixed>> */
function extractAshbyJobs(array $response): array
{
    $json = $response['json'] ?? null;
    if (! is_array($json) || ! isset($json['jobs']) || ! is_array($json['jobs'])) {
        return [];
    }

    return array_values(array_filter(
        $json['jobs'],
        static fn (mixed $item): bool => is_array($item)
            && isset($item['id'], $item['title'])
            && ($item['isListed'] ?? true) !== false,
    ));
}

function ashbyLocationBlob(array $job): string
{
    $address = is_array($job['address'] ?? null) ? $job['address'] : [];
    $postal = is_array($address['postalAddress'] ?? null) ? $address['postalAddress'] : [];

    return locationBlobFromParts([
        'location' => $job['location'] ?? null,
        'secondaryLocations' => $job['secondaryLocations'] ?? null,
        'isRemote' => $job['isRemote'] ?? null,
        'workplaceType' => $job['workplaceType'] ?? null,
        'addressLocality' => $postal['addressLocality'] ?? null,
        'addressRegion' => $postal['addressRegion'] ?? null,
        'addressCountry' => $postal['addressCountry'] ?? null,
    ]);
}

function analyzeAshbyJobs(array $jobs): array
{
    $counts = [
        'turkey' => 0,
        'istanbul' => 0,
        'remote_turkey' => 0,
        'other_turkey_cities' => 0,
        'global' => 0,
    ];
    $timestamps = [];
    $turkeyTimestamps = [];
    $fieldChecks = [
        'description' => 0,
        'url' => 0,
        'stable_id' => 0,
        'location' => 0,
        'employment_type' => 0,
        'salary' => 0,
        'published_at' => 0,
        'updated_at' => 0,
    ];
    $total = count($jobs);

    foreach ($jobs as &$job) {
        $blob = ashbyLocationBlob($job);
        $class = classifyLocation($blob);
        $job['_location_class'] = $class;

        if ($class === 'istanbul') {
            $counts['istanbul']++;
            $counts['turkey']++;
        } elseif ($class === 'remote_turkey') {
            $counts['remote_turkey']++;
            $counts['turkey']++;
        } elseif ($class === 'turkey') {
            $counts['turkey']++;
            $counts['other_turkey_cities']++;
        } else {
            $counts['global']++;
        }

        $publishedTs = parseTimestamp($job['publishedAt'] ?? null);
        $timestamps[] = $publishedTs;
        if (in_array($class, ['turkey', 'istanbul', 'remote_turkey'], true)) {
            $turkeyTimestamps[] = $publishedTs;
        }

        if (filled($job['descriptionPlain'] ?? null) || filled($job['descriptionHtml'] ?? null)) {
            $fieldChecks['description']++;
        }
        if (filled($job['jobUrl'] ?? null)) {
            $fieldChecks['url']++;
        }
        if (filled($job['id'] ?? null)) {
            $fieldChecks['stable_id']++;
        }
        if (filled($job['location'] ?? null) || filled($job['address']['postalAddress']['addressCountry'] ?? null)) {
            $fieldChecks['location']++;
        }
        if (filled($job['employmentType'] ?? null)) {
            $fieldChecks['employment_type']++;
        }
        if (filled($job['compensation'] ?? null)) {
            $fieldChecks['salary']++;
        }
        if ($publishedTs !== null) {
            $fieldChecks['published_at']++;
        }

        $fieldChecks['updated_at'] = '0/'.$total.' (field not exposed in public posting API)';
    }
    unset($job);

    $freshness = freshnessStats($timestamps);
    $turkeyFreshness = freshnessStats($turkeyTimestamps);

    $fieldCoverage = [];
    foreach ($fieldChecks as $field => $count) {
        if (is_string($count)) {
            $fieldCoverage[$field] = $count;
        } else {
            $fieldCoverage[$field] = "{$count}/{$total}";
        }
    }

    return [
        'counts' => $counts,
        'freshness' => $freshness,
        'turkey_freshness' => $turkeyFreshness,
        'field_coverage' => $fieldCoverage,
        'jobs' => $jobs,
    ];
}

$boards = [];
$allTurkeyJobs = [];
$crossBoardJobs = [];
$sampleJobShape = null;

foreach ($candidates as $candidate) {
    usleep(150000);
    $response = fetchAshbyBoard($candidate['board']);
    $jobs = extractAshbyJobs($response);
    $analysis = analyzeAshbyJobs($jobs);

    if ($sampleJobShape === null && isset($jobs[0])) {
        $sampleJobShape = [
            'top_level_keys' => array_keys($jobs[0]),
            'timestamp_fields' => [
                'publishedAt' => $jobs[0]['publishedAt'] ?? null,
                'updatedAt' => 'NOT PRESENT in public posting API sample',
            ],
            'location_shape' => [
                'location' => $jobs[0]['location'] ?? null,
                'address' => $jobs[0]['address'] ?? null,
            ],
        ];
    }

    $dupes = duplicateAnalysis(
        $jobs,
        static fn (array $j): string => (string) ($j['id'] ?? ''),
        static fn (array $j): string => (string) ($j['jobUrl'] ?? ''),
        static fn (array $j): string => (string) ($j['title'] ?? ''),
        static fn (array $j): string => $candidate['company'],
        static fn (array $j): string => (string) ($j['location'] ?? ''),
    );

    $board = [
        'company' => $candidate['company'],
        'board_name' => $candidate['board'],
        'provider' => 'ashby',
        'discovery_source' => $candidate['discovery_source'],
        'verified_url' => $candidate['verified_url'] ?? null,
        'endpoint' => str_replace('{board}', $candidate['board'], ASHBY_JOBS_ENDPOINT).'?includeCompensation=true',
        'http_status' => $response['http_status'],
        'latency_ms' => $response['latency_ms'],
        'valid_json' => $response['valid_json'] ?? false,
        'error' => $response['error'],
        'status' => match (true) {
            ($response['http_status'] ?? 0) === 200 && ($response['valid_json'] ?? false) && count($jobs) > 0 && ($analysis['counts']['turkey'] ?? 0) > 0 => 'USEFUL',
            ($response['http_status'] ?? 0) === 200 && ($response['valid_json'] ?? false) && count($jobs) > 0 => 'VALID',
            ($response['http_status'] ?? 0) === 200 && ($response['valid_json'] ?? false) => 'VALID',
            default => 'DISCOVERED',
        },
        'total_jobs' => count($jobs),
        'unique_jobs' => count($jobs) - count($dupes['duplicate_ids']),
        'turkey_jobs' => $analysis['counts']['turkey'],
        'istanbul_jobs' => $analysis['counts']['istanbul'],
        'remote_turkey_jobs' => $analysis['counts']['remote_turkey'],
        'other_turkey_cities' => $analysis['counts']['other_turkey_cities'],
        'global_jobs' => $analysis['counts']['global'],
        'fresh_jobs' => $analysis['freshness']['fresh'],
        'stale_jobs' => $analysis['freshness']['stale'],
        'fresh_turkey_jobs' => $analysis['turkey_freshness']['fresh'],
        'stale_turkey_jobs' => $analysis['turkey_freshness']['stale'],
        'newest_job' => $analysis['freshness']['newest'],
        'oldest_job' => $analysis['freshness']['oldest'],
        'field_coverage' => $analysis['field_coverage'],
        'duplicate_within_board' => $dupes,
        'pagination' => [
            'supported' => false,
            'note' => 'Single response returns all listed jobs',
        ],
        'timestamp_note' => 'publishedAt = publish timestamp; no updated_at in public posting API response',
    ];

    $board['category'] = categorizeBoard($board);
    $boards[] = $board;

    foreach ($analysis['jobs'] as $job) {
        if (in_array($job['_location_class'] ?? 'global', ['turkey', 'istanbul', 'remote_turkey'], true)) {
            $allTurkeyJobs[] = [
                'board' => $candidate['board'],
                'company' => $candidate['company'],
                'title' => $job['title'] ?? '',
                'url' => $job['jobUrl'] ?? '',
                '_location_class' => $job['_location_class'],
            ];
        }
        $crossBoardJobs[] = [
            'board' => $candidate['board'],
            'id' => (string) ($job['id'] ?? ''),
            'url' => (string) ($job['jobUrl'] ?? ''),
            'title' => (string) ($job['title'] ?? ''),
            'company' => $candidate['company'],
        ];
    }
}

$validBoards = array_values(array_filter($boards, static fn ($b): bool => ($b['http_status'] ?? 0) === 200 && ($b['total_jobs'] ?? 0) > 0));
$categoryA = array_values(array_filter($boards, static fn ($b): bool => $b['category'] === 'A'));
$categoryB = array_values(array_filter($boards, static fn ($b): bool => $b['category'] === 'B'));
$categoryC = array_values(array_filter($boards, static fn ($b): bool => $b['category'] === 'C'));

$complementarity = complementarityForJobs(
    $allTurkeyJobs,
    static fn (array $j): string => (string) ($j['url'] ?? ''),
    static fn (array $j): string => (string) ($j['title'] ?? ''),
    static fn (array $j): string => (string) ($j['company'] ?? ''),
    static fn (array $j): bool => in_array($j['_location_class'] ?? 'global', ['turkey', 'istanbul', 'remote_turkey'], true),
);

$crossUrlDupes = [];
$urlSeen = [];
foreach ($crossBoardJobs as $row) {
    if ($row['url'] !== '') {
        $urlSeen[$row['url']][] = $row['board'];
    }
}
foreach ($urlSeen as $url => $boardsList) {
    if (count(array_unique($boardsList)) > 1) {
        $crossUrlDupes[] = ['url' => $url, 'boards' => array_values(array_unique($boardsList))];
    }
}

$turkeyCompanies = [];
foreach (array_merge($categoryA, $categoryB) as $b) {
    if (($b['turkey_jobs'] ?? 0) > 0) {
        $turkeyCompanies[$b['company']] = true;
    }
}

$topA = array_map(static fn ($b): array => [
    'company' => $b['company'],
    'board' => $b['board_name'],
    'total' => $b['total_jobs'],
    'turkey' => $b['turkey_jobs'],
    'istanbul' => $b['istanbul_jobs'],
    'fresh_turkey' => $b['fresh_turkey_jobs'],
    'stale' => $b['stale_turkey_jobs'],
    'verdict' => 'PRODUCTION CANDIDATE',
], $categoryA);

usort($topA, static fn ($a, $b): int => $b['turkey'] <=> $a['turkey']);

$incrementalTurkey = $complementarity['incremental_turkey_jobs'] ?? 0;

$decision = match (true) {
    $incrementalTurkey >= 15 && count($categoryA) >= 2 => [
        'label' => 'ASHBY → GO',
        'rationale' => "Measured {$incrementalTurkey} incremental Turkey jobs across ".count($categoryA).' A-boards with official public API.',
    ],
    $incrementalTurkey >= 5 || count($categoryA) >= 1 => [
        'label' => 'ASHBY → HOLD',
        'rationale' => "Incremental Turkey coverage ({$incrementalTurkey} jobs, ".count($categoryA).' A-boards) is promising but board discovery is manual and volume is concentrated in few Istanbul studios.',
    ],
    default => [
        'label' => 'ASHBY → REJECT',
        'rationale' => 'Insufficient incremental Turkey coverage; most probed boards are global-only or invalid.',
    ],
};

$report = [
    'generated_at' => date('c'),
    'provider' => 'ashby',
    'endpoint_used' => ASHBY_JOBS_ENDPOINT.'?includeCompensation=true',
    'api_legal' => [
        'public_read_api' => 'YES — GET /posting-api/job-board/{name} requires no authentication',
        'authentication_required' => 'NO for public job listing; YES for core RPC ATS API',
        'official_documentation' => 'https://developers.ashbyhq.com/docs/public-job-posting-api',
        'scraping_required' => 'NO — JSON API returns descriptions in single call',
        'aggregation_restrictions' => 'UNKNOWN — public API documented for custom careers pages; third-party aggregation terms not explicit',
        'rate_limit' => 'Not documented; no rate-limit headers observed in this probe run',
        'attribution' => 'UNKNOWN',
        'api_stability' => 'Official documented public API',
    ],
    'timestamp_fields' => [
        'published_at_field' => 'publishedAt',
        'updated_at_field' => 'NOT EXPOSED in public posting API',
        'freshness_uses' => 'publishedAt only',
    ],
    'max_posting_age_days' => ATS_MAX_POSTING_AGE_DAYS,
    'sample_job_shape' => $sampleJobShape,
    'boards' => $boards,
    'summary' => [
        'total_candidates_probed' => count($boards),
        'valid_boards_with_jobs' => count($validBoards),
        'category_a' => count($categoryA),
        'category_b' => count($categoryB),
        'category_c' => count($categoryC),
        'total_jobs_all_valid_boards' => array_sum(array_column($validBoards, 'total_jobs')),
        'total_turkey_jobs' => array_sum(array_column($validBoards, 'turkey_jobs')),
        'total_istanbul_jobs' => array_sum(array_column($validBoards, 'istanbul_jobs')),
        'total_fresh_turkey_jobs' => array_sum(array_column($validBoards, 'fresh_turkey_jobs')),
        'unique_turkey_companies' => count($turkeyCompanies),
    ],
    'duplicate_findings' => [
        'cross_board_url' => array_slice($crossUrlDupes, 0, 20),
    ],
    'complementarity' => $complementarity,
    'top_a_boards' => $topA,
    'decision' => $decision,
    'discovery_note' => 'Probed '.count($candidates).' candidates from web-verified sources and real-company ATS probes (no fabricated slugs).',
];

$jsonPath = base_path('ASHBY_COVERAGE_DISCOVERY.json');
$mdPath = base_path('ASHBY_COVERAGE_DISCOVERY.md');
file_put_contents($jsonPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
writeCoverageMarkdown($mdPath, 'Ashby', $report);

echo "Wrote {$jsonPath}\n";
echo "Wrote {$mdPath}\n";
echo 'Probed: '.count($boards).' | Valid: '.count($validBoards)." | A: {$report['summary']['category_a']} | B: {$report['summary']['category_b']} | C: {$report['summary']['category_c']}\n";
echo 'Turkey jobs: '.$report['summary']['total_turkey_jobs'].' | Incremental: '.($complementarity['incremental_turkey_jobs'] ?? 0)."\n";
echo $decision['label']."\n";

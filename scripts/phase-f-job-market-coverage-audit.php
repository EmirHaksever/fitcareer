<?php

declare(strict_types=1);

/**
 * Phase F read-only job market coverage & gap analysis.
 * NO DB writes.
 */

use App\Enums\ExperienceLevel;
use App\Enums\JobStatus;
use App\Enums\WorkType;
use App\Models\Job;
use App\Models\JobSource;
use App\Services\Scraper\LocationClassificationService;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$now = now();
$dbBefore = ['jobs' => Job::count(), 'job_sources' => JobSource::count()];

$publishedScope = static function ($query) use ($now): void {
    $query->where('status', JobStatus::Published)
        ->where(function ($inner) use ($now): void {
            $inner->whereNull('expires_at')->orWhere('expires_at', '>', $now);
        });
};

/** @return list<array<string,mixed>> */
function loadPublishedJobs(): array
{
    global $publishedScope;

    return Job::query()
        ->with('sourceProvider')
        ->where($publishedScope)
        ->get()
        ->map(fn (Job $j): array => [
            'id' => $j->id,
            'title' => (string) $j->title,
            'description' => mb_substr(strip_tags((string) $j->description), 0, 500),
            'category' => $j->category,
            'city' => $j->city,
            'country' => $j->country,
            'work_type' => $j->work_type?->value,
            'experience_level' => $j->experience_level?->value,
            'employment_type' => $j->employment_type?->value,
            'source_company_name' => $j->source_company_name,
            'job_source_id' => $j->job_source_id,
            'provider' => $j->sourceProvider?->config['provider'] ?? null,
            'source_name' => $j->sourceProvider?->name ?? null,
            'trust_score' => $j->trust_score,
            'trust_analysis_status' => $j->trust_analysis_status?->value,
            'published_at' => $j->published_at?->toDateString(),
            'last_seen_at' => $j->last_seen_at?->toDateString(),
            'scrape_status' => $j->scrape_status?->value,
        ])
        ->all();
}

function norm(string $text): string
{
    $text = mb_strtolower($text);
    $text = str_replace(['ı', 'İ', 'ş', 'Ş', 'ğ', 'Ğ', 'ü', 'Ü', 'ö', 'Ö', 'ç', 'Ç'], ['i', 'i', 's', 's', 'g', 'g', 'u', 'u', 'o', 'o', 'c', 'c'], $text);

    return $text;
}

function matchesAny(string $blob, array $patterns): bool
{
    $blob = norm($blob);
    foreach ($patterns as $p) {
        if (str_contains($blob, norm($p))) {
            return true;
        }
    }

    return false;
}

function isTurkeyVisible(array $job): bool
{
    $country = norm((string) ($job['country'] ?? ''));
    $city = norm((string) ($job['city'] ?? ''));
    if (in_array($country, ['turkey', 'turkiye', 'türkiye', 'tr'], true)) {
        return true;
    }
    $trCities = ['istanbul', 'ankara', 'izmir', 'bursa', 'antalya', 'kocaeli', 'adana'];
    foreach ($trCities as $c) {
        if (str_contains($city, $c) || str_contains($country, $c)) {
            return true;
        }
    }
    if (($job['work_type'] ?? '') === 'remote' && ($country !== '' || $city !== '')) {
        return in_array($country, ['turkey', 'turkiye', 'tr'], true) || str_contains($city, 'istanbul') || str_contains($city, 'ankara') || str_contains($city, 'izmir');
    }

    return false;
}

function isIstanbul(array $job): bool
{
    $city = norm((string) ($job['city'] ?? ''));

    return str_contains($city, 'istanbul') || str_contains($city, 'stanbul');
}

function inferExperienceFromTitle(string $title): string
{
    $t = norm($title);
    if (matchesAny($t, ['intern', 'staj', 'trainee', 'internship'])) {
        return 'intern';
    }
    if (matchesAny($t, ['junior', 'jr.', 'entry level', 'graduate', 'new grad', 'fresh'])) {
        return 'entry';
    }
    if (matchesAny($t, ['senior', 'sr.', 'lead', 'principal', 'staff', 'head of', 'director', 'manager', 'müdür', 'kıdemli'])) {
        return 'senior';
    }
    if (matchesAny($t, ['mid', 'intermediate'])) {
        return 'mid';
    }

    return 'unknown';
}

/** @return array<string,array<string,mixed>> */
function personaDefinitions(): array
{
    return [
        'junior_software_developer' => ['label' => 'Junior Software Developer', 'patterns' => ['junior developer', 'junior software', 'jr developer', 'entry level developer', 'graduate developer', 'yeni mezun yazilim', 'junior engineer']],
        'frontend_developer' => ['label' => 'Frontend Developer', 'patterns' => ['frontend', 'front-end', 'front end', 'react', 'vue', 'angular', 'javascript developer', 'web developer', 'ui developer']],
        'backend_developer' => ['label' => 'Backend Developer', 'patterns' => ['backend', 'back-end', 'back end', 'laravel', 'php developer', 'java developer', 'node.js', 'golang', 'python developer', '.net developer']],
        'fullstack_developer' => ['label' => 'Full Stack Developer', 'patterns' => ['full stack', 'fullstack', 'full-stack']],
        'mobile_developer' => ['label' => 'Mobile Developer', 'patterns' => ['mobile developer', 'android', 'ios', 'flutter', 'react native', 'swift developer', 'kotlin developer']],
        'qa_engineer' => ['label' => 'QA / Test Engineer', 'patterns' => ['qa ', 'quality assurance', 'test engineer', 'sdet', 'automation tester', 'manual tester', 'test analyst']],
        'data_analyst' => ['label' => 'Data Analyst', 'patterns' => ['data analyst', 'business analyst', 'bi analyst', 'analytics analyst', 'veri analist']],
        'data_scientist_ai_ml' => ['label' => 'Data Scientist / AI / ML', 'patterns' => ['data scientist', 'machine learning', 'ml engineer', 'ai engineer', 'deep learning', 'nlp engineer', 'computer vision']],
        'devops_cloud' => ['label' => 'DevOps / Cloud', 'patterns' => ['devops', 'sre', 'site reliability', 'cloud engineer', 'platform engineer', 'kubernetes', 'aws engineer', 'azure engineer']],
        'ui_ux_designer' => ['label' => 'UI/UX Designer', 'patterns' => ['ui designer', 'ux designer', 'ui/ux', 'product designer', 'graphic designer', 'visual designer']],
        'product_project_manager' => ['label' => 'Product / Project Manager', 'patterns' => ['product manager', 'project manager', 'program manager', 'product owner', 'scrum master', 'proje yönetic', 'ürün yönetic']],
        'digital_marketing' => ['label' => 'Digital Marketing', 'patterns' => ['digital marketing', 'performance marketing', 'seo ', 'sem ', 'growth marketing', 'social media', 'dijital pazarlama', 'marketing specialist', 'marketing manager']],
        'finance_accounting' => ['label' => 'Finance / Accounting', 'patterns' => ['finance', 'accountant', 'accounting', 'muhasebe', 'financial analyst', 'controller', 'treasury', 'finans']],
        'operations' => ['label' => 'Operations', 'patterns' => ['operations', 'operasyon', 'supply chain', 'logistics', 'procurement', 'tedarik']],
        'sales_bd' => ['label' => 'Sales / Business Development', 'patterns' => ['sales', 'business development', 'account executive', 'account manager', 'satış', 'iç satış', 'bd manager', 'customer success']],
        'hr_recruitment' => ['label' => 'HR / Recruitment', 'patterns' => ['human resources', 'hr ', 'recruiter', 'talent acquisition', 'people & culture', 'insan kaynak']],
        'fresh_grad_intern' => ['label' => 'Fresh Graduate / Internship', 'patterns' => ['intern', 'internship', 'staj', 'trainee', 'new grad', 'graduate program', 'yeni mezun', 'genel başvuru']],
        'non_technical_white_collar' => ['label' => 'Non-technical White-collar', 'patterns' => ['administrative', 'office assistant', 'executive assistant', 'customer service', 'müşteri hizmet', 'call center', 'legal', 'hukuk', 'consultant', 'danışman']],
    ];
}

function classifySupply(int $trJobs, int $employers, float $topShare): string
{
    if ($trJobs < 5 || $employers < 2) {
        return 'CRITICAL_SUPPLY_GAP';
    }
    if ($trJobs < 15 || $employers < 3 || $topShare > 0.5) {
        return 'WEAK_SUPPLY';
    }
    if ($trJobs < 30 || $employers < 5) {
        return 'MODERATE_SUPPLY';
    }

    return 'HEALTHY_SUPPLY';
}

$jobs = loadPublishedJobs();
$totalPublished = count($jobs);
$turkeyJobs = array_values(array_filter($jobs, 'isTurkeyVisible'));
$istanbulJobs = array_values(array_filter($jobs, 'isIstanbul'));

// Unique employers (normalized company name)
$employerCounts = [];
foreach ($turkeyJobs as $job) {
    $emp = norm(trim((string) ($job['source_company_name'] ?? 'unknown')));
    if ($emp === '') {
        $emp = 'unknown';
    }
    $employerCounts[$emp] = ($employerCounts[$emp] ?? 0) + 1;
}
arsort($employerCounts);
$uniqueEmployers = count($employerCounts);
$topEmployerShare = $uniqueEmployers > 0 ? (max($employerCounts) / max(1, count($turkeyJobs))) : 0;

// Persona analysis
$personas = personaDefinitions();
$personaResults = [];
foreach ($personas as $key => $def) {
    $matched = [];
    foreach ($turkeyJobs as $job) {
        $blob = ($job['title'] ?? '').' '.($job['description'] ?? '').' '.($job['category'] ?? '');
        if (matchesAny($blob, $def['patterns'])) {
            $matched[] = $job;
        }
    }
    $empSet = [];
    foreach ($matched as $m) {
        $empSet[norm((string) ($m['source_company_name'] ?? 'unknown'))] = true;
    }
    $empCount = count($empSet);
    $topEmpShare = 0.0;
    if ($empCount > 0) {
        $counts = [];
        foreach ($matched as $m) {
            $e = norm((string) ($m['source_company_name'] ?? 'unknown'));
            $counts[$e] = ($counts[$e] ?? 0) + 1;
        }
        $topEmpShare = max($counts) / max(1, count($matched));
    }
    $personaResults[$key] = [
        'label' => $def['label'],
        'total_matched_tr' => count($matched),
        'istanbul' => count(array_filter($matched, 'isIstanbul')),
        'remote' => count(array_filter($matched, fn ($j) => ($j['work_type'] ?? '') === 'remote')),
        'hybrid' => count(array_filter($matched, fn ($j) => ($j['work_type'] ?? '') === 'hybrid')),
        'onsite' => count(array_filter($matched, fn ($j) => ($j['work_type'] ?? '') === 'onsite')),
        'unique_employers' => $empCount,
        'classification' => classifySupply(count($matched), $empCount, $topEmpShare),
        'top_employer_share_pct' => round($topEmpShare * 100, 1),
    ];
}

// Experience level
$expDb = ['intern' => 0, 'entry' => 0, 'mid' => 0, 'senior' => 0, 'lead' => 0, 'executive' => 0, 'null' => 0];
$expInferred = ['intern' => 0, 'entry' => 0, 'mid' => 0, 'senior' => 0, 'unknown' => 0];
foreach ($turkeyJobs as $job) {
    if ($job['experience_level'] === null) {
        $expDb['null']++;
    } else {
        $expDb[$job['experience_level']] = ($expDb[$job['experience_level']] ?? 0) + 1;
    }
    $inf = inferExperienceFromTitle((string) $job['title']);
    $expInferred[$inf] = ($expInferred[$inf] ?? 0) + 1;
}

// Location breakdown
$locationBreakdown = [
    'istanbul' => count($istanbulJobs),
    'ankara' => 0,
    'izmir' => 0,
    'other_tr_city' => 0,
    'turkey_unspecified_city' => 0,
    'remote_tr' => 0,
    'hybrid' => 0,
    'onsite' => 0,
    'unknown_location' => 0,
];
foreach ($turkeyJobs as $job) {
    $city = norm((string) ($job['city'] ?? ''));
    if ($city === '' && norm((string) ($job['country'] ?? '')) !== '') {
        $locationBreakdown['turkey_unspecified_city']++;
    } elseif (str_contains($city, 'ankara')) {
        $locationBreakdown['ankara']++;
    } elseif (str_contains($city, 'izmir')) {
        $locationBreakdown['izmir']++;
    } elseif ($city !== '' && ! isIstanbul($job)) {
        $locationBreakdown['other_tr_city']++;
    }
    if (($job['work_type'] ?? '') === 'remote') {
        $locationBreakdown['remote_tr']++;
    }
    if (($job['work_type'] ?? '') === 'hybrid') {
        $locationBreakdown['hybrid']++;
    }
    if (($job['work_type'] ?? '') === 'onsite') {
        $locationBreakdown['onsite']++;
    }
    if (($job['city'] ?? '') === '' && ($job['country'] ?? '') === '') {
        $locationBreakdown['unknown_location']++;
    }
}

// Provider / source concentration
$providerCounts = [];
$sourceCounts = [];
foreach ($turkeyJobs as $job) {
    $p = (string) ($job['provider'] ?? 'unknown');
    $providerCounts[$p] = ($providerCounts[$p] ?? 0) + 1;
    $s = (string) ($job['source_name'] ?? 'unknown');
    $sourceCounts[$s] = ($sourceCounts[$s] ?? 0) + 1;
}
arsort($providerCounts);
arsort($sourceCounts);

// Top 20 employers
$top20Employers = array_slice($employerCounts, 0, 20, true);
$employersWithOneJob = count(array_filter($employerCounts, fn ($c) => $c === 1));
$employersOver10Pct = count(array_filter($employerCounts, fn ($c) => ($c / max(1, count($turkeyJobs))) > 0.10));

// Stale jobs
$staleCount = Job::query()->where($publishedScope)->where('scrape_status', 'stale')->count();
$oldSeen = Job::query()->where($publishedScope)->where('last_seen_at', '<', $now->copy()->subDays(30))->count();

// Global accidentally visible? Jobs with non-TR country in published set
$globalVisible = array_filter($jobs, function (array $j): bool {
    $c = norm((string) ($j['country'] ?? ''));
    if ($c === '') {
        return false;
    }

    return ! in_array($c, ['turkey', 'turkiye', 'türkiye', 'tr'], true)
        && ! str_contains(norm((string) ($j['city'] ?? '')), 'istanbul')
        && ! str_contains(norm((string) ($j['city'] ?? '')), 'ankara')
        && ! str_contains(norm((string) ($j['city'] ?? '')), 'izmir');
});

// Search simulation using FULLTEXT (same as repository)
/** @return int */
function simulateSearch(string $keyword, ?string $location = null): int
{
    global $publishedScope;
    $builder = Job::query()->where($publishedScope);
    $kw = trim($keyword);
    if ($kw !== '') {
        $builder->whereFullText(['title', 'description'], $kw);
    }
    if ($location !== null && trim($location) !== '') {
        $loc = trim($location);
        $builder->where(function ($q) use ($loc): void {
            $q->where('city', 'like', '%'.$loc.'%')->orWhere('country', 'like', '%'.$loc.'%');
        });
    }
    app(LocationClassificationService::class)->applyTurkeyRelevantScope($builder, false);

    return $builder->count();
}

$searchTests = [
    ['query' => 'React Developer', 'location' => null],
    ['query' => 'Frontend Engineer', 'location' => null],
    ['query' => 'Yazılım Geliştirici', 'location' => null],
    ['query' => 'Software Engineer', 'location' => null],
    ['query' => 'QA', 'location' => null],
    ['query' => 'Quality Assurance', 'location' => null],
    ['query' => 'Flutter', 'location' => null],
    ['query' => 'Android', 'location' => null],
    ['query' => 'Laravel', 'location' => null],
    ['query' => 'PHP', 'location' => null],
    ['query' => 'Data Analyst', 'location' => null],
    ['query' => 'Machine Learning', 'location' => null],
    ['query' => 'DevOps', 'location' => null],
    ['query' => 'Product Manager', 'location' => null],
    ['query' => 'Digital Marketing', 'location' => null],
    ['query' => 'Muhasebe', 'location' => null],
    ['query' => 'Intern', 'location' => null],
    ['query' => 'Junior', 'location' => null],
    ['query' => 'Software Engineer', 'location' => 'İstanbul'],
    ['query' => 'Software Engineer', 'location' => 'Istanbul'],
    ['query' => 'Developer', 'location' => 'Ankara'],
];

$searchResults = [];
foreach ($searchTests as $test) {
    $searchResults[] = [
        'keyword' => $test['query'],
        'location' => $test['location'],
        'results' => simulateSearch($test['query'], $test['location']),
    ];
}

// CV/Fit persona simulation (title/skill keyword overlap)
$fitPersonas = [
    'A_junior_react' => ['skills' => ['react', 'javascript', 'html', 'css'], 'patterns' => ['frontend', 'react', 'javascript', 'web developer', 'junior']],
    'B_laravel_php' => ['skills' => ['laravel', 'php', 'mysql'], 'patterns' => ['laravel', 'php', 'backend']],
    'C_flutter_mobile' => ['skills' => ['flutter', 'dart', 'mobile'], 'patterns' => ['flutter', 'mobile', 'android', 'ios']],
    'D_qa' => ['skills' => ['qa', 'selenium', 'testing'], 'patterns' => ['qa', 'quality assurance', 'test engineer', 'sdet']],
    'E_data_analyst' => ['skills' => ['sql', 'excel', 'power bi'], 'patterns' => ['data analyst', 'business analyst', 'analytics']],
    'F_fresh_grad' => ['skills' => ['computer science'], 'patterns' => ['intern', 'junior', 'graduate', 'entry', 'yeni mezun', 'staj']],
    'G_digital_marketer' => ['skills' => ['seo', 'google ads'], 'patterns' => ['marketing', 'seo', 'growth', 'digital']],
    'H_finance' => ['skills' => ['accounting', 'excel'], 'patterns' => ['finance', 'accounting', 'muhasebe', 'financial analyst']],
];

$fitResults = [];
foreach ($fitPersonas as $key => $persona) {
    $relevant = [];
    foreach ($turkeyJobs as $job) {
        $blob = norm(($job['title'] ?? '').' '.($job['description'] ?? ''));
        foreach ($persona['patterns'] as $p) {
            if (str_contains($blob, norm($p))) {
                $relevant[] = $job;
                break;
            }
        }
    }
    $employers = count(array_unique(array_map(fn ($j) => norm((string) ($j['source_company_name'] ?? '')), $relevant)));
    $fitResults[$key] = [
        'relevant_tr_jobs' => count($relevant),
        'unique_employers' => $employers,
        'istanbul' => count(array_filter($relevant, 'isIstanbul')),
        'actionable' => count($relevant) >= 10 && $employers >= 3,
    ];
}

// Source scorecard
$sources = JobSource::query()->orderBy('id')->get();
$sourceScorecard = [];
foreach ($sources as $source) {
    $provider = (string) ($source->config['provider'] ?? 'unknown');
    $srcJobs = array_filter($jobs, fn ($j) => $j['job_source_id'] === $source->id);
    $srcTr = array_filter($srcJobs, 'isTurkeyVisible');
    $srcIst = array_filter($srcTr, 'isIstanbul');
    $published = count($srcJobs);
    $tr = count($srcTr);
    $ist = count($srcIst);
    $trShare = $published > 0 ? round($tr / $published * 100, 1) : 0;

    $classification = 'MONITOR';
    if (! $source->is_active && $published === 0) {
        $classification = 'DEPRECATE_CANDIDATE';
    } elseif ($source->is_active && $tr === 0) {
        $classification = 'LOW_VALUE';
    } elseif ($source->is_active && $tr >= 5 && $trShare >= 20) {
        $classification = 'KEEP';
    } elseif ($source->is_active && $tr > 0) {
        $classification = 'MONITOR';
    }

    $sourceScorecard[] = [
        'id' => $source->id,
        'name' => $source->name,
        'provider' => $provider,
        'is_active' => $source->is_active,
        'ingest_policy' => $source->config['ingest_policy'] ?? 'global',
        'published_jobs' => $published,
        'turkey_visible' => $tr,
        'istanbul' => $ist,
        'turkey_share_pct' => $trShare,
        'last_items_found' => $source->last_items_found,
        'last_items_created' => $source->last_items_created,
        'consecutive_failures' => $source->consecutive_failures,
        'classification' => $classification,
    ];
}

// Bottleneck scoring
$bottlenecks = [
    [
        'issue' => 'Category/persona supply gaps (non-tech + junior)',
        'user_impact' => 9,
        'supply_impact' => 9,
        'complexity' => 6,
        'maintenance' => 5,
        'evidence_confidence' => 9,
        'score' => 0,
    ],
    [
        'issue' => 'Experience level metadata null (filter useless)',
        'user_impact' => 7,
        'supply_impact' => 6,
        'complexity' => 4,
        'maintenance' => 3,
        'evidence_confidence' => 10,
        'score' => 0,
    ],
    [
        'issue' => 'Search FULLTEXT misses Turkish/synonym queries',
        'user_impact' => 8,
        'supply_impact' => 5,
        'complexity' => 5,
        'maintenance' => 4,
        'evidence_confidence' => 8,
        'score' => 0,
    ],
    [
        'issue' => 'Employer concentration (top employers dominate)',
        'user_impact' => 7,
        'supply_impact' => 7,
        'complexity' => 7,
        'maintenance' => 6,
        'evidence_confidence' => 9,
        'score' => 0,
    ],
    [
        'issue' => 'Another ATS provider (Recruitee Phase 2 / Teamtailor)',
        'user_impact' => 6,
        'supply_impact' => 6,
        'complexity' => 7,
        'maintenance' => 6,
        'evidence_confidence' => 7,
        'score' => 0,
    ],
    [
        'issue' => 'Ankara/Izmir geographic undercoverage',
        'user_impact' => 5,
        'supply_impact' => 4,
        'complexity' => 7,
        'maintenance' => 5,
        'evidence_confidence' => 9,
        'score' => 0,
    ],
];

foreach ($bottlenecks as &$b) {
    $b['score'] = round(
        ($b['user_impact'] * $b['supply_impact'] * $b['evidence_confidence'])
        / max(1, $b['complexity'] * $b['maintenance']),
        2
    );
}
unset($b);
usort($bottlenecks, fn ($a, $b) => $b['score'] <=> $a['score']);

$dbAfter = ['jobs' => Job::count(), 'job_sources' => JobSource::count()];

$output = [
    'audit' => [
        'title' => 'Phase F — Job Market Coverage & Gap Analysis',
        'generated_at' => $now->toIso8601String(),
        'scope' => 'read_only',
    ],
    'database_integrity' => [
        'before' => $dbBefore,
        'after' => $dbAfter,
        'writes' => ($dbBefore['jobs'] !== $dbAfter['jobs'] || $dbBefore['job_sources'] !== $dbAfter['job_sources']) ? 1 : 0,
    ],
    'inventory' => [
        'total_published_active' => $totalPublished,
        'turkey_visible' => count($turkeyJobs),
        'istanbul' => count($istanbulJobs),
        'unique_employers_tr' => $uniqueEmployers,
        'active_sources' => JobSource::where('is_active', true)->count(),
        'total_sources' => JobSource::count(),
        'top_employer_share_pct' => round($topEmployerShare * 100, 1),
    ],
    'persona_coverage' => $personaResults,
    'experience_level' => [
        'db_field' => $expDb,
        'inferred_from_title' => $expInferred,
        'null_rate_pct' => round(($expDb['null'] / max(1, count($turkeyJobs))) * 100, 1),
    ],
    'location_breakdown' => $locationBreakdown,
    'diversity' => [
        'top_20_employers' => $top20Employers,
        'top_providers' => $providerCounts,
        'top_sources' => array_slice($sourceCounts, 0, 20, true),
        'employers_with_one_job' => $employersWithOneJob,
        'employers_over_10pct_share' => $employersOver10Pct,
        'istanbul_share_of_tr_pct' => round(count($istanbulJobs) / max(1, count($turkeyJobs)) * 100, 1),
    ],
    'freshness' => [
        'stale_scrape_status' => $staleCount,
        'last_seen_older_30d' => $oldSeen,
    ],
    'global_visible_leak_check' => [
        'count' => count($globalVisible),
        'sample_titles' => array_slice(array_column(array_values($globalVisible), 'title'), 0, 10),
    ],
    'search_simulation' => $searchResults,
    'fit_persona_simulation' => $fitResults,
    'source_scorecard' => $sourceScorecard,
    'bottleneck_ranking' => $bottlenecks,
];

$outPath = __DIR__.'/../storage/phase-f-job-market-coverage.json';
file_put_contents($outPath, json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

echo "Phase F analysis complete\n";
echo "Published: {$totalPublished}\n";
echo "Turkey-visible: ".count($turkeyJobs)."\n";
echo "Istanbul: ".count($istanbulJobs)."\n";
echo "Unique employers: {$uniqueEmployers}\n";
echo "Experience null rate: ".round(($expDb['null'] / max(1, count($turkeyJobs))) * 100, 1)."%\n";
echo "DB writes: {$output['database_integrity']['writes']}\n";
echo "Output: {$outPath}\n";

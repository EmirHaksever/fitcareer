<?php

declare(strict_types=1);

/**
 * Turkey location classification dry-run — read-only diagnostic.
 * Does NOT modify, delete, or expire any jobs.
 */

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Job;

const GLOBAL_REMOTE_PATTERNS = [
    'remote - europe',
    'remote - emea',
    'remote - worldwide',
    'remote - eu',
    'remote, europe',
    'remote, emea',
    'remote, worldwide',
    'worldwide',
    'emea',
    'europe only',
];

const TURKEY_COUNTRY_TOKENS = ['turkey', 'türkiye', 'turkiye', 'tr'];

const TURKEY_CITY_PATTERNS = [
    'istanbul', 'i̇stanbul', 'ankara', 'izmir', 'bursa', 'antalya', 'adana',
    'konya', 'gaziantep', 'kocaeli', 'mersin', 'gebze', 'maslak', 'levent',
    'sarıyer', 'ataşehir', 'üsküdar', 'kadıköy', 'beşiktaş', 'bomonti',
    'mecidiyeköy', 'esenyurt', 'pendik', 'tuzla', 'kartal', 'maltepe',
];

function normalizeBlob(?string $city, ?string $country, ?string $workType): string
{
    $parts = array_filter([
        $city,
        $country,
        $workType,
    ], static fn ($v) => is_string($v) && trim($v) !== '');

    $blob = mb_strtolower(implode(' | ', $parts));
    $blob = str_replace(['i̇', 'ı'], 'i', $blob);

    return $blob;
}

function isTurkeyCountry(?string $country): bool
{
    if ($country === null || trim($country) === '') {
        return false;
    }

    $n = mb_strtolower(trim($country));

    foreach (TURKEY_COUNTRY_TOKENS as $token) {
        if ($n === $token || str_contains($n, $token)) {
            return true;
        }
    }

    return false;
}

function detectTurkeyCity(?string $city, string $blob): ?string
{
    if ($city !== null && trim($city) !== '') {
        $cityNorm = mb_strtolower(trim($city));
        $cityNorm = str_replace(['i̇', 'ı'], 'i', $cityNorm);

        foreach (TURKEY_CITY_PATTERNS as $pattern) {
            if ($cityNorm === $pattern || str_contains($cityNorm, $pattern)) {
                return trim($city);
            }
        }
    }

    foreach (TURKEY_CITY_PATTERNS as $pattern) {
        if (str_contains($blob, $pattern)) {
            return $city ?? ucfirst($pattern);
        }
    }

    return null;
}

function isIstanbul(?string $city, string $blob): bool
{
    if ($city !== null) {
        $c = str_replace(['i̇', 'ı'], 'i', mb_strtolower(trim($city)));

        return str_contains($c, 'istanbul');
    }

    return str_contains($blob, 'istanbul');
}

function isExplicitGlobalRemote(string $blob): bool
{
    foreach (GLOBAL_REMOTE_PATTERNS as $pattern) {
        if (str_contains($blob, $pattern)) {
            return true;
        }
    }

    if (preg_match('/\bremote\b.*\b(europe|emea|eu|worldwide|global)\b/u', $blob) === 1) {
        return true;
    }

    return false;
}

function isExplicitTurkeyRemote(string $blob): bool
{
    if (! str_contains($blob, 'remote')) {
        return false;
    }

    foreach (TURKEY_COUNTRY_TOKENS as $token) {
        if (str_contains($blob, $token)) {
            return true;
        }
    }

    foreach (TURKEY_CITY_PATTERNS as $pattern) {
        if (str_contains($blob, $pattern)) {
            return true;
        }
    }

    if (preg_match('/remote\s*[-–—,]\s*(turkey|türkiye|turkiye|tr)\b/u', $blob) === 1) {
        return true;
    }

    return false;
}

function isForeignCountry(?string $country, string $blob): bool
{
    if ($country !== null && trim($country) !== '' && ! isTurkeyCountry($country)) {
        $knownForeign = [
            'united states', 'usa', 'uk', 'united kingdom', 'germany', 'france',
            'netherlands', 'spain', 'india', 'canada', 'australia', 'singapore',
            'poland', 'romania', 'ukraine', 'brazil', 'mexico', 'japan', 'china',
            'ireland', 'portugal', 'italy', 'sweden', 'norway', 'denmark', 'finland',
            'belgium', 'austria', 'switzerland', 'czech', 'hungary', 'greece',
            'cyprus', 'serbia', 'bulgaria', 'georgia', 'armenia', 'azerbaijan',
        ];
        $n = mb_strtolower(trim($country));

        foreach ($knownForeign as $foreign) {
            if ($n === $foreign || str_contains($n, $foreign)) {
                return true;
            }
        }

        return true;
    }

    return false;
}

/**
 * @return array{category:string,is_turkey_relevant:bool,reason:string}
 */
function classifyJob(Job $job): array
{
    $city = $job->city;
    $country = $job->country;
    $workType = $job->work_type?->value;
    $blob = normalizeBlob($city, $country, $workType);

    if (isExplicitGlobalRemote($blob)) {
        return [
            'category' => 'foreign_global',
            'is_turkey_relevant' => false,
            'reason' => 'explicit_global_remote_pattern',
        ];
    }

    if (isForeignCountry($country, $blob) && ! isTurkeyCountry($country) && ! detectTurkeyCity($city, $blob)) {
        return [
            'category' => 'foreign_global',
            'is_turkey_relevant' => false,
            'reason' => 'foreign_country_no_turkey_signal',
        ];
    }

    $turkeyCity = detectTurkeyCity($city, $blob);
    $turkeyCountry = isTurkeyCountry($country);

    if ($workType === 'remote') {
        if (isExplicitTurkeyRemote($blob) || $turkeyCountry || $turkeyCity !== null) {
            return [
                'category' => 'remote_turkey',
                'is_turkey_relevant' => true,
                'reason' => 'remote_with_turkey_signal',
            ];
        }

        if ($country === null && $city === null) {
            return [
                'category' => 'unknown',
                'is_turkey_relevant' => false,
                'reason' => 'remote_without_location',
            ];
        }
    }

    if ($turkeyCountry || $turkeyCity !== null) {
        if (isIstanbul($city, $blob)) {
            return [
                'category' => 'istanbul',
                'is_turkey_relevant' => true,
                'reason' => 'turkey_city_istanbul',
            ];
        }

        return [
            'category' => 'other_turkey_cities',
            'is_turkey_relevant' => true,
            'reason' => 'turkey_city_or_country',
        ];
    }

    if ($country === null && $city === null) {
        return [
            'category' => 'unknown',
            'is_turkey_relevant' => false,
            'reason' => 'missing_location',
        ];
    }

    if ($country !== null || $city !== null) {
        return [
            'category' => 'foreign_global',
            'is_turkey_relevant' => false,
            'reason' => 'non_turkey_location',
        ];
    }

    return [
        'category' => 'unknown',
        'is_turkey_relevant' => false,
        'reason' => 'unclassified',
    ];
}

$jobs = Job::query()
    ->with('sourceProvider')
    ->where('source', 'scraped')
    ->where('status', 'published')
    ->orderBy('id')
    ->get();

$categories = [
    'istanbul' => [],
    'other_turkey_cities' => [],
    'remote_turkey' => [],
    'foreign_global' => [],
    'unknown' => [],
];

$turkeyRelevant = 0;

foreach ($jobs as $job) {
    $result = classifyJob($job);
    $provider = (string) ($job->sourceProvider?->config['provider'] ?? 'unknown');

    $entry = [
        'id' => $job->id,
        'title' => $job->title,
        'company' => $job->source_company_name,
        'city' => $job->city,
        'country' => $job->country,
        'work_type' => $job->work_type?->value,
        'provider' => $provider,
        'reason' => $result['reason'],
        'external_url' => $job->external_url,
    ];

    $categories[$result['category']][] = $entry;

    if ($result['is_turkey_relevant']) {
        $turkeyRelevant++;
    }
}

$report = [
    'generated_at' => date('c'),
    'mode' => 'dry_run_read_only',
    'total_jobs' => $jobs->count(),
    'turkey_relevant' => $turkeyRelevant,
    'by_category' => [
        'istanbul' => count($categories['istanbul']),
        'other_turkey_cities' => count($categories['other_turkey_cities']),
        'remote_turkey' => count($categories['remote_turkey']),
        'foreign_global' => count($categories['foreign_global']),
        'unknown' => count($categories['unknown']),
    ],
    'samples' => array_map(
        static fn (array $items): array => array_slice($items, 0, 10),
        $categories,
    ),
    'by_provider' => [],
];

foreach ($jobs->groupBy(fn (Job $j) => $j->sourceProvider?->config['provider'] ?? 'unknown') as $provider => $group) {
    $tr = 0;
    foreach ($group as $job) {
        if (classifyJob($job)['is_turkey_relevant']) {
            $tr++;
        }
    }
    $report['by_provider'][$provider] = [
        'total' => $group->count(),
        'turkey_relevant' => $tr,
    ];
}

$jsonPath = base_path('TURKEY_LOCATION_DRY_RUN.json');
file_put_contents($jsonPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));

echo "TURKEY LOCATION DRY-RUN (read-only)\n";
echo 'Total published scraped jobs: '.$report['total_jobs']."\n";
echo 'Turkey-relevant: '.$report['turkey_relevant']."\n";
echo 'Istanbul: '.$report['by_category']['istanbul']."\n";
echo 'Other TR cities: '.$report['by_category']['other_turkey_cities']."\n";
echo 'Remote Turkey: '.$report['by_category']['remote_turkey']."\n";
echo 'Foreign/Global: '.$report['by_category']['foreign_global']."\n";
echo 'Unknown: '.$report['by_category']['unknown']."\n";
echo "Wrote {$jsonPath}\n";

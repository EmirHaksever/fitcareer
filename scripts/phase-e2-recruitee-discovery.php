<?php

declare(strict_types=1);

/**
 * Phase E.2 read-only Recruitee Turkey expansion discovery.
 * NO DB writes, imports, or application mutations.
 */

require __DIR__.'/ats-coverage-discovery-helpers.php';
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Job;
use App\Models\JobSource;

const E2_DELAY_US = 180_000;
const E2_MAX_POSTING_AGE_DAYS = 365;

/** @return array<string,mixed> */
function dbSnapshot(): array
{
    return [
        'job_sources' => JobSource::count(),
        'jobs' => Job::count(),
        'timestamp' => now()->toIso8601String(),
    ];
}

function normalizeCompanyName(string $name): string
{
    $name = mb_strtolower($name);
    $name = str_replace(['ı', 'İ', 'ş', 'Ş', 'ğ', 'Ğ', 'ü', 'Ü', 'ö', 'Ö', 'ç', 'Ç'], ['i', 'i', 's', 's', 'g', 'g', 'u', 'u', 'o', 'o', 'c', 'c'], $name);
    $name = preg_replace('/[^a-z0-9]+/', '', $name) ?? $name;

    return $name;
}

/** @return list<array<string,mixed>> */
function loadExistingCoverage(): array
{
    $coverage = [];
    foreach (JobSource::query()->get() as $source) {
        $provider = (string) ($source->config['provider'] ?? 'unknown');
        $slug = (string) ($source->config['site_slug'] ?? '');
        $display = (string) ($source->config['company_display_name'] ?? $source->name);
        $coverage[] = [
            'source_id' => $source->id,
            'name' => $source->name,
            'provider' => $provider,
            'site_slug' => $slug,
            'company_display_name' => $display,
            'normalized_name' => normalizeCompanyName($display),
            'normalized_slug' => normalizeCompanyName($slug),
            'is_active' => (bool) $source->is_active,
            'published_jobs' => Job::where('job_source_id', $source->id)->count(),
        ];
    }

    return $coverage;
}

/** @return array<string,mixed> */
function classifyRecruiteeCoverage(string $companyName, string $slug, array $existingCoverage): array
{
    $normName = normalizeCompanyName($companyName);
    $normSlug = normalizeCompanyName($slug);
    $matches = [];

    // Aggregator sources are not employer-equivalent for duplicate detection.
    $aggregatorProviders = ['remotive', 'kariyer-net'];

    foreach ($existingCoverage as $row) {
        $provider = (string) ($row['provider'] ?? '');
        if (in_array($provider, $aggregatorProviders, true)) {
            continue;
        }

        $rowNormName = (string) ($row['normalized_name'] ?? '');
        $rowNormSlug = (string) ($row['normalized_slug'] ?? '');

        $slugMatch = $normSlug !== '' && $rowNormSlug !== '' && $rowNormSlug === $normSlug;
        $nameMatch = $normName !== '' && $rowNormName !== '' && (
            $rowNormName === $normName
            || str_contains($rowNormName, $normName)
            || str_contains($normName, $rowNormName)
        );

        if ($slugMatch || $nameMatch) {
            $matches[] = $row;
        }
    }

    if ($matches === []) {
        return [
            'classification' => 'NET_NEW',
            'existing_providers' => [],
            'notes' => 'No matching employer-specific JobSource in FitCareer DB',
        ];
    }

    $providers = array_values(array_unique(array_column($matches, 'provider')));
    $active = array_values(array_filter($matches, fn (array $m): bool => $m['is_active']));

    if ($active !== [] && array_sum(array_column($active, 'published_jobs')) > 0) {
        return [
            'classification' => 'FULLY_COVERED',
            'existing_providers' => $providers,
            'matches' => $matches,
            'notes' => 'Active JobSource with published jobs exists for same employer',
        ];
    }

    return [
        'classification' => 'PARTIALLY_DUPLICATE',
        'existing_providers' => $providers,
        'matches' => $matches,
        'notes' => 'JobSource match exists but inactive or zero published jobs',
    ];
}

/** @return array<string,mixed> */
function classifyOfferLocation(array $offer): array
{
    $blob = locationBlobFromParts([
        $offer['country'] ?? null,
        $offer['country_code'] ?? null,
        $offer['city'] ?? null,
        $offer['location'] ?? null,
        $offer['locations'] ?? null,
        $offer['remote'] ?? null,
        $offer['hybrid'] ?? null,
        $offer['on_site'] ?? null,
        $offer['title'] ?? null,
    ]);

    $class = classifyLocation($blob);

    $hasStructuredCountry = ! empty($offer['country']) || ! empty($offer['country_code']);
    $hasStructuredCity = ! empty($offer['city']);
    $hasLocationField = ! empty($offer['location']);
    $hasLocationsArray = is_array($offer['locations'] ?? null) && ($offer['locations'] ?? []) !== [];

    $metadataQuality = 'LOW';
    if ($hasStructuredCountry && ($hasStructuredCity || $hasLocationsArray)) {
        $metadataQuality = 'HIGH';
    } elseif ($hasStructuredCountry || $hasStructuredCity || $hasLocationField) {
        $metadataQuality = 'MEDIUM';
    }

    $locationEmpty = ! $hasStructuredCountry && ! $hasStructuredCity && ! $hasLocationField && ! $hasLocationsArray;

    return [
        'class' => $class,
        'metadata_quality' => $locationEmpty ? 'LOW' : $metadataQuality,
        'location_empty' => $locationEmpty,
    ];
}

/** @return array<string,mixed> */
function probeRecruiteeEmployer(string $companyName, string $slug, string $tier, string $discoverySource, array $existingCoverage): array
{
    $url = "https://{$slug}.recruitee.com/api/offers";
    $resp = httpGetJson($url);
    $json = $resp['json'] ?? null;

    $base = [
        'company_name' => $companyName,
        'recruitee_slug' => $slug,
        'api_url' => $url,
        'career_url' => "https://{$slug}.recruitee.com/",
        'tier' => $tier,
        'discovery_source' => $discoverySource,
        'http_status' => $resp['http_status'],
        'latency_ms' => $resp['latency_ms'] ?? null,
        'accessible' => false,
    ];

    if (! is_array($json)) {
        return array_merge($base, [
            'error' => $resp['error'] ?? 'invalid_json',
            'coverage' => classifyRecruiteeCoverage($companyName, $slug, $existingCoverage),
        ]);
    }

    $offers = $json['offers'] ?? $json;
    if (! is_array($offers)) {
        return array_merge($base, [
            'error' => 'missing_offers_array',
            'coverage' => classifyRecruiteeCoverage($companyName, $slug, $existingCoverage),
        ]);
    }

    $offerList = array_values(array_filter($offers, 'is_array'));
    $total = count($offerList);

    $counts = [
        'turkey' => 0,
        'istanbul' => 0,
        'ankara' => 0,
        'izmir' => 0,
        'remote_turkey' => 0,
        'global' => 0,
        'global_remote' => 0,
        'unknown_location' => 0,
    ];
    $metadataScores = ['HIGH' => 0, 'MEDIUM' => 0, 'LOW' => 0];
    $timestamps = [];
    $sampleOffers = [];
    $schemaKeys = $total > 0 ? array_keys($offerList[0]) : [];

    foreach ($offerList as $offer) {
        $loc = classifyOfferLocation($offer);
        $class = $loc['class'];
        $metadataScores[$loc['metadata_quality']] = ($metadataScores[$loc['metadata_quality']] ?? 0) + 1;

        if ($loc['location_empty']) {
            $counts['unknown_location']++;
        }

        match ($class) {
            'istanbul' => $counts['istanbul']++,
            'turkey' => $counts['turkey']++,
            'remote_turkey' => $counts['remote_turkey']++,
            'global_remote' => $counts['global_remote']++,
            default => $counts['global']++,
        };

        $cityBlob = mb_strtolower((string) json_encode($offer['city'] ?? $offer['location'] ?? '', JSON_UNESCAPED_UNICODE));
        if (str_contains($cityBlob, 'ankara')) {
            $counts['ankara']++;
        }
        if (str_contains($cityBlob, 'izmir')) {
            $counts['izmir']++;
        }

        $ts = parseTimestamp($offer['published_at'] ?? $offer['created_at'] ?? $offer['updated_at'] ?? null);
        $timestamps[] = $ts;

        if (count($sampleOffers) < 2 && in_array($class, ['turkey', 'istanbul', 'remote_turkey'], true)) {
            $sampleOffers[] = [
                'id' => $offer['id'] ?? null,
                'slug' => $offer['slug'] ?? null,
                'title' => $offer['title'] ?? null,
                'country' => $offer['country'] ?? null,
                'country_code' => $offer['country_code'] ?? null,
                'city' => $offer['city'] ?? null,
                'location' => $offer['location'] ?? null,
                'published_at' => $offer['published_at'] ?? null,
                'class' => $class,
            ];
        }
    }

    $trRelevant = $counts['turkey'] + $counts['istanbul'] + $counts['remote_turkey'];
    $freshness = freshnessStats($timestamps);
    $activeTr = 0;
    foreach ($offerList as $i => $offer) {
        $loc = classifyOfferLocation($offer);
        if (! in_array($loc['class'], ['turkey', 'istanbul', 'remote_turkey'], true)) {
            continue;
        }
        $ts = $timestamps[$i] ?? null;
        if ($ts === null || (int) floor((time() - $ts) / 86400) <= E2_MAX_POSTING_AGE_DAYS) {
            $activeTr++;
        }
    }

    $employerMetadata = 'LOW';
    if (($metadataScores['HIGH'] ?? 0) >= max(1, (int) floor($total * 0.5))) {
        $employerMetadata = 'HIGH';
    } elseif (($metadataScores['HIGH'] ?? 0) + ($metadataScores['MEDIUM'] ?? 0) >= max(1, (int) floor($total * 0.5))) {
        $employerMetadata = 'MEDIUM';
    }

    $coverage = classifyRecruiteeCoverage($companyName, $slug, $existingCoverage);

    return array_merge($base, [
        'accessible' => ($resp['http_status'] ?? 0) === 200,
        'total_offers' => $total,
        'turkey_relevant' => $trRelevant,
        'active_turkey_relevant' => $activeTr,
        'istanbul' => $counts['istanbul'],
        'ankara' => $counts['ankara'],
        'izmir' => $counts['izmir'],
        'remote_turkey' => $counts['remote_turkey'],
        'global_rejected' => $counts['global'] + $counts['global_remote'],
        'unknown_location' => $counts['unknown_location'],
        'location_metadata_quality' => $employerMetadata,
        'metadata_breakdown' => $metadataScores,
        'freshness' => $freshness,
        'sample_tr_offers' => $sampleOffers,
        'schema_keys' => $schemaKeys,
        'coverage' => $coverage,
        'net_new_tr_jobs' => $coverage['classification'] === 'NET_NEW' ? $activeTr : 0,
    ]);
}

/** @return list<array{company:string,slug:string,tier:string,source:string}> */
function candidateEmployers(): array
{
    $candidates = [
        // Phase E confirmed
        ['company' => 'Mikro Yazılım', 'slug' => 'mikroyazilim', 'tier' => '1', 'source' => 'phase_e_confirmed'],
        ['company' => 'Trio Mobil', 'slug' => 'triomobil', 'tier' => '1', 'source' => 'phase_e_confirmed'],
        ['company' => 'Krila Consultancy', 'slug' => 'krila', 'tier' => '1', 'source' => 'phase_e_confirmed'],
        ['company' => 'Paraşüt', 'slug' => 'parasut', 'tier' => '1', 'source' => 'phase_e_confirmed'],

        // Phase E.2 web search discoveries
        ['company' => 'Nucs AI', 'slug' => 'nucsai', 'tier' => '1', 'source' => 'web_search_istanbul'],
        ['company' => 'Pisano', 'slug' => 'pisanoco', 'tier' => '1', 'source' => 'web_search_istanbul'],
        ['company' => 'Zirve Yazılım', 'slug' => 'zirvebilgiteknolojilerisanayiticaretanonimsirketi', 'tier' => '1', 'source' => 'web_search_ankara'],
        ['company' => 'Aikido Security', 'slug' => 'aikidosecurity', 'tier' => '3', 'source' => 'web_search_turkey_role'],
        ['company' => 'finbyte GmbH', 'slug' => 'finbyte', 'tier' => '3', 'source' => 'web_search_istanbul'],

        // Phase E probed — Tier 1 TR tech
        ['company' => 'Craftgate', 'slug' => 'craftgate', 'tier' => '1', 'source' => 'phase_e_probe'],
        ['company' => 'Figopara', 'slug' => 'figopara', 'tier' => '1', 'source' => 'phase_e_probe'],
        ['company' => 'Kolay İK', 'slug' => 'kolayik', 'tier' => '1', 'source' => 'phase_e_probe'],
        ['company' => 'Logo Yazılım', 'slug' => 'logo', 'tier' => '1', 'source' => 'phase_e_probe'],
        ['company' => 'Softtech', 'slug' => 'softtech', 'tier' => '1', 'source' => 'phase_e_probe'],
        ['company' => 'Intertech', 'slug' => 'intertech', 'tier' => '1', 'source' => 'phase_e_probe'],
        ['company' => 'BiTaksi', 'slug' => 'bitaksi', 'tier' => '1', 'source' => 'phase_e_probe'],
        ['company' => 'Moka', 'slug' => 'moka', 'tier' => '1', 'source' => 'phase_e_probe'],
        ['company' => 'Param', 'slug' => 'param', 'tier' => '1', 'source' => 'phase_e_probe'],
        ['company' => 'Teknasyon', 'slug' => 'teknasyon', 'tier' => '1', 'source' => 'phase_e_probe'],
        ['company' => 'Jotform', 'slug' => 'jotform', 'tier' => '1', 'source' => 'phase_e_probe'],
        ['company' => 'Shopside', 'slug' => 'shopside', 'tier' => '1', 'source' => 'phase_e_probe'],
        ['company' => 'e-Mükellef', 'slug' => 'emukellef', 'tier' => '1', 'source' => 'phase_e_probe'],
        ['company' => 'Zirve', 'slug' => 'zirve', 'tier' => '1', 'source' => 'phase_e_probe'],
        ['company' => 'Ticimax', 'slug' => 'ticimax', 'tier' => '1', 'source' => 'phase_e_probe'],

        // Tier 1 — major TR employers (likely other ATS)
        ['company' => 'iyzico', 'slug' => 'iyzico', 'tier' => '1', 'source' => 'phase_e_probe'],
        ['company' => 'Papara', 'slug' => 'papara', 'tier' => '1', 'source' => 'phase_e_probe'],
        ['company' => 'Getir', 'slug' => 'getir', 'tier' => '1', 'source' => 'phase_e_probe'],
        ['company' => 'Trendyol', 'slug' => 'trendyol', 'tier' => '1', 'source' => 'phase_e_probe'],
        ['company' => 'Hepsiburada', 'slug' => 'hepsiburada', 'tier' => '1', 'source' => 'phase_e_probe'],
        ['company' => 'Turkcell', 'slug' => 'turkcell', 'tier' => '1', 'source' => 'phase_e_probe'],
        ['company' => 'Boyner', 'slug' => 'boyner', 'tier' => '1', 'source' => 'phase_e_probe'],
        ['company' => 'Netas', 'slug' => 'netas', 'tier' => '1', 'source' => 'phase_e_probe'],
        ['company' => 'Peak Games', 'slug' => 'peakgames', 'tier' => '1', 'source' => 'phase_e_probe'],
        ['company' => 'Midas', 'slug' => 'midas', 'tier' => '1', 'source' => 'phase_e_probe'],
        ['company' => 'Dream Games', 'slug' => 'dreamgames', 'tier' => '1', 'source' => 'phase_e_probe'],
        ['company' => 'Insider', 'slug' => 'insider', 'tier' => '1', 'source' => 'phase_e_probe'],
        ['company' => 'Insider One', 'slug' => 'insiderone', 'tier' => '1', 'source' => 'phase_e_probe'],

        // Tier 2 — Istanbul / scaleups
        ['company' => 'Commencis', 'slug' => 'commencis', 'tier' => '2', 'source' => 'phase_c_list'],
        ['company' => 'Commencis alt', 'slug' => 'commencis1', 'tier' => '2', 'source' => 'slug_variant'],
        ['company' => 'VavaCars', 'slug' => 'vavacars', 'tier' => '2', 'source' => 'workable_seed_crosscheck'],
        ['company' => 'FERASET', 'slug' => 'feraset', 'tier' => '2', 'source' => 'workable_seed_crosscheck'],
        ['company' => 'Sanction Scanner', 'slug' => 'sanctionscanner', 'tier' => '2', 'source' => 'workable_seed_crosscheck'],
        ['company' => 'Lucida AI', 'slug' => 'lucidaai', 'tier' => '2', 'source' => 'workable_seed_crosscheck'],
        ['company' => 'NewMind AI', 'slug' => 'newmindai', 'tier' => '2', 'source' => 'workable_seed_crosscheck'],
        ['company' => 'Wingie Enuygun', 'slug' => 'wingie', 'tier' => '2', 'source' => 'workable_seed_crosscheck'],
        ['company' => 'Enuygun', 'slug' => 'enuygun', 'tier' => '2', 'source' => 'slug_variant'],
        ['company' => 'Good Job Games', 'slug' => 'goodjobgames', 'tier' => '2', 'source' => 'greenhouse_seed_crosscheck'],
        ['company' => 'Codeway', 'slug' => 'codeway', 'tier' => '2', 'source' => 'ashby_seed_crosscheck'],
        ['company' => 'Bigger Games', 'slug' => 'biggergames', 'tier' => '2', 'source' => 'ashby_seed_crosscheck'],
        ['company' => 'Agave Games', 'slug' => 'agavegames', 'tier' => '2', 'source' => 'ashby_seed_crosscheck'],
        ['company' => 'DoktorTakvimi', 'slug' => 'doktortakvimi', 'tier' => '2', 'source' => 'ashby_seed_crosscheck'],

        // Tier 3 — foreign with TR ops
        ['company' => 'Revolut', 'slug' => 'revolut', 'tier' => '3', 'source' => 'phase_e_probe'],
        ['company' => 'DFDS', 'slug' => 'dfds', 'tier' => '3', 'source' => 'phase_e_probe'],
        ['company' => 'Volue', 'slug' => 'volue', 'tier' => '3', 'source' => 'phase_e_probe'],
        ['company' => 'TradingView', 'slug' => 'tradingview', 'tier' => '3', 'source' => 'phase_e_probe'],
        ['company' => 'Delivery Hero', 'slug' => 'deliveryhero', 'tier' => '3', 'source' => 'phase_e_probe'],

        // Additional slug variants from research
        ['company' => 'TechBiz Global', 'slug' => 'techbizglobal', 'tier' => '1', 'source' => 'web_search'],
        ['company' => 'Konecta Digital', 'slug' => 'konectadigital', 'tier' => '3', 'source' => 'web_search'],
        ['company' => 'Kodland', 'slug' => 'kodland', 'tier' => '3', 'source' => 'web_search'],
        ['company' => 'CBS Corporate', 'slug' => 'cbscorporate', 'tier' => '2', 'source' => 'web_search'],
        ['company' => 'CBS', 'slug' => 'cbs', 'tier' => '2', 'source' => 'slug_variant'],
        ['company' => 'Mikrogrup', 'slug' => 'mikrogrup', 'tier' => '1', 'source' => 'slug_variant'],
        ['company' => 'DST Technology', 'slug' => 'dst', 'tier' => '1', 'source' => 'slug_variant'],
        ['company' => 'Dia Yazılım', 'slug' => 'diayazilim', 'tier' => '1', 'source' => 'slug_variant'],
        ['company' => 'Pisano alt', 'slug' => 'pisano', 'tier' => '1', 'source' => 'slug_variant'],
        ['company' => 'Zirve alt', 'slug' => 'zirveyazilim', 'tier' => '1', 'source' => 'slug_variant'],
        ['company' => 'Rollic', 'slug' => 'rollic', 'tier' => '2', 'source' => 'phase_c_list'],
        ['company' => 'Nomagic', 'slug' => 'nomagic', 'tier' => '2', 'source' => 'phase_c_list'],
        ['company' => 'Çiçeksepeti', 'slug' => 'ciceksepeti', 'tier' => '1', 'source' => 'phase_c_list'],
        ['company' => 'Grand Games', 'slug' => 'grand', 'tier' => '2', 'source' => 'lever_seed_crosscheck'],
        ['company' => 'Ajax Systems', 'slug' => 'ajax', 'tier' => '2', 'source' => 'lever_seed_crosscheck'],
        ['company' => 'Binance', 'slug' => 'binance', 'tier' => '3', 'source' => 'phase_c_list'],
        ['company' => 'Protel', 'slug' => 'protel', 'tier' => '1', 'source' => 'phase_e_teamtailor'],
        ['company' => 'Sovos', 'slug' => 'sovos', 'tier' => '1', 'source' => 'phase_c_list'],
        ['company' => 'Infinia', 'slug' => 'infinia', 'tier' => '1', 'source' => 'tr_fintech_list'],
        ['company' => 'Sipay', 'slug' => 'sipay', 'tier' => '1', 'source' => 'tr_fintech_list'],
        ['company' => 'Paycore', 'slug' => 'paycore', 'tier' => '1', 'source' => 'tr_fintech_list'],
        ['company' => 'TomPay', 'slug' => 'tompay', 'tier' => '1', 'source' => 'tr_fintech_list'],
        ['company' => 'Tom Bank', 'slug' => 'tombank', 'tier' => '1', 'source' => 'tr_fintech_list'],
        ['company' => 'Moneta', 'slug' => 'moneta', 'tier' => '1', 'source' => 'tr_fintech_list'],
        ['company' => 'Opsgenie', 'slug' => 'opsgenie', 'tier' => '2', 'source' => 'tr_tech_list'],
        ['company' => 'Insider legacy', 'slug' => 'useinsider', 'tier' => '1', 'source' => 'slug_variant'],
        ['company' => 'Peak', 'slug' => 'peak', 'tier' => '1', 'source' => 'slug_variant'],
        ['company' => 'Peak Games alt', 'slug' => 'peakgames1', 'tier' => '1', 'source' => 'slug_variant'],
        ['company' => 'Migros', 'slug' => 'migros', 'tier' => '1', 'source' => 'tr_retail_list'],
        ['company' => 'Arçelik', 'slug' => 'arcelik', 'tier' => '1', 'source' => 'tr_corporate_list'],
        ['company' => 'Vodafone TR', 'slug' => 'vodafone', 'tier' => '1', 'source' => 'tr_corporate_list'],
        ['company' => 'Turk Telekom', 'slug' => 'turktelekom', 'tier' => '1', 'source' => 'tr_corporate_list'],
        ['company' => 'Garanti BBVA Tech', 'slug' => 'garanti', 'tier' => '1', 'source' => 'tr_fintech_list'],
        ['company' => 'Denizbank', 'slug' => 'denizbank', 'tier' => '1', 'source' => 'tr_fintech_list'],
        ['company' => 'Akbank', 'slug' => 'akbank', 'tier' => '1', 'source' => 'tr_fintech_list'],
        ['company' => 'Yemeksepeti', 'slug' => 'yemeksepeti', 'tier' => '1', 'source' => 'tr_marketplace_list'],
        ['company' => 'Getir alt', 'slug' => 'getircom', 'tier' => '1', 'source' => 'slug_variant'],
        ['company' => 'Insider AI', 'slug' => 'insiderai', 'tier' => '1', 'source' => 'slug_variant'],
        ['company' => 'Peak Games Studio', 'slug' => 'peakgamesstudio', 'tier' => '1', 'source' => 'slug_variant'],
        ['company' => 'Scalefocus', 'slug' => 'scalefocus', 'tier' => '2', 'source' => 'tr_agency_list'],
        ['company' => 'Bulutistan', 'slug' => 'bulutistan', 'tier' => '2', 'source' => 'tr_cloud_list'],
        ['company' => 'Logo', 'slug' => 'logoyazilim', 'tier' => '1', 'source' => 'slug_variant'],
        ['company' => 'Foreks', 'slug' => 'foreks', 'tier' => '1', 'source' => 'tr_fintech_list'],
        ['company' => 'Matters', 'slug' => 'matters', 'tier' => '1', 'source' => 'tr_startup_list'],
        ['company' => 'Martı', 'slug' => 'marti', 'tier' => '1', 'source' => 'tr_mobility_list'],
        ['company' => 'Mobildev', 'slug' => 'mobildev', 'tier' => '1', 'source' => 'tr_tech_list'],
        ['company' => 'Infobip TR', 'slug' => 'infobip', 'tier' => '3', 'source' => 'tr_tech_list'],
        ['company' => 'SemperTech', 'slug' => 'sempertech', 'tier' => '1', 'source' => 'tr_tech_list'],
        ['company' => 'Atlassian TR', 'slug' => 'atlassian', 'tier' => '3', 'source' => 'global_crosscheck'],
        ['company' => 'Personio TR', 'slug' => 'personio', 'tier' => '3', 'source' => 'global_crosscheck'],
    ];

    // Deduplicate by slug
    $seen = [];
    $unique = [];
    foreach ($candidates as $c) {
        if (isset($seen[$c['slug']])) {
            continue;
        }
        $seen[$c['slug']] = true;
        $unique[] = $c;
    }

    return $unique;
}

function aggregateResults(array $probes): array
{
    $validBoards = array_values(array_filter($probes, fn (array $p): bool => ($p['accessible'] ?? false) && (($p['total_offers'] ?? 0) > 0 || ($p['turkey_relevant'] ?? 0) > 0)));
    $trEmployers = array_values(array_filter($validBoards, fn (array $p): bool => ($p['active_turkey_relevant'] ?? 0) > 0));
    $netNewEmployers = array_values(array_filter($trEmployers, fn (array $p): bool => ($p['coverage']['classification'] ?? '') === 'NET_NEW'));

    $totals = [
        'candidates_researched' => count($probes),
        'valid_recruitee_boards' => count($validBoards),
        'confirmed_tr_employers' => count($trEmployers),
        'net_new_tr_employers' => count($netNewEmployers),
        'total_offers' => array_sum(array_column($validBoards, 'total_offers')),
        'turkey_relevant' => array_sum(array_column($trEmployers, 'turkey_relevant')),
        'active_turkey_relevant' => array_sum(array_column($trEmployers, 'active_turkey_relevant')),
        'istanbul' => array_sum(array_column($trEmployers, 'istanbul')),
        'net_new_tr_jobs' => array_sum(array_column($trEmployers, 'net_new_tr_jobs')),
        'duplicate_employers' => count(array_filter($trEmployers, fn (array $p): bool => ($p['coverage']['classification'] ?? '') !== 'NET_NEW')),
        'unknown_location' => array_sum(array_column($validBoards, 'unknown_location')),
        'global_rejected' => array_sum(array_column($validBoards, 'global_rejected')),
    ];

    // Employer concentration
    $trJobCounts = array_column($trEmployers, 'active_turkey_relevant', 'recruitee_slug');
    arsort($trJobCounts);
    $topEmployerJobs = reset($trJobCounts) ?: 0;
    $totalTrJobs = $totals['active_turkey_relevant'] ?: 1;
    $concentrationTop1Pct = round(($topEmployerJobs / $totalTrJobs) * 100, 1);

    $totals['employer_concentration'] = [
        'top_employer_slug' => array_key_first($trJobCounts),
        'top_employer_jobs' => $topEmployerJobs,
        'top1_share_pct' => $concentrationTop1Pct,
    ];

    return $totals;
}

function evaluateThreshold(array $totals): array
{
    $employers = (int) ($totals['net_new_tr_employers'] ?? 0);
    $jobs = (int) ($totals['net_new_tr_jobs'] ?? 0);
    $concentration = (float) ($totals['employer_concentration']['top1_share_pct'] ?? 0);

    $checks = [
        'employers_gte_6' => [
            'required' => 6,
            'actual' => $employers,
            'pass' => $employers >= 6,
        ],
        'net_new_tr_jobs_gte_20' => [
            'required' => 20,
            'actual' => $jobs,
            'pass' => $jobs >= 20,
        ],
        'employer_diversity' => [
            'required' => 'top1_share <= 60%',
            'actual' => $concentration.'%',
            'pass' => $concentration <= 60.0,
        ],
        'net_new_supply_meaningful' => [
            'required' => '>= 15 net-new TR jobs',
            'actual' => $jobs,
            'pass' => $jobs >= 15,
        ],
    ];

    $allPass = array_reduce($checks, fn (bool $carry, array $c): bool => $carry && $c['pass'], true);

    $decision = 'DO_NOT_IMPLEMENT';
    if ($allPass) {
        $decision = 'IMPLEMENT';
    } elseif ($employers >= 5 && $jobs >= 15) {
        $decision = 'MORE_DISCOVERY';
    } elseif ($employers >= 4 && $jobs >= 15) {
        $decision = 'MORE_DISCOVERY';
    } elseif ($jobs < 15 || $employers < 6) {
        $decision = 'DO_NOT_IMPLEMENT';
    }

    return [
        'checks' => $checks,
        'all_pass' => $allPass,
        'decision' => $decision,
    ];
}

// --- Main ---

$dbBefore = dbSnapshot();
$existingCoverage = loadExistingCoverage();
$candidates = candidateEmployers();
$probes = [];

foreach ($candidates as $i => $candidate) {
    $probes[] = probeRecruiteeEmployer(
        $candidate['company'],
        $candidate['slug'],
        $candidate['tier'],
        $candidate['source'],
        $existingCoverage,
    );
    if ($i < count($candidates) - 1) {
        usleep(E2_DELAY_US);
    }
}

$dbAfter = dbSnapshot();
$totals = aggregateResults($probes);
$threshold = evaluateThreshold($totals);

$trEmployers = array_values(array_filter(
    $probes,
    fn (array $p): bool => ($p['accessible'] ?? false) && ($p['active_turkey_relevant'] ?? 0) > 0
));
usort($trEmployers, fn (array $a, array $b): int => ($b['active_turkey_relevant'] ?? 0) <=> ($a['active_turkey_relevant'] ?? 0));

$output = [
    'audit' => [
        'title' => 'Phase E.2 — Recruitee Turkey Expansion Discovery',
        'generated_at' => now()->toIso8601String(),
        'scope' => 'read_only_discovery',
        'prior_phase' => 'Phase E — 4 TR employers, 13 TR jobs',
    ],
    'database_integrity' => [
        'before' => $dbBefore,
        'after' => $dbAfter,
        'database_writes' => ($dbBefore['jobs'] !== $dbAfter['jobs'] || $dbBefore['job_sources'] !== $dbAfter['job_sources']) ? 1 : 0,
        'verified_read_only' => $dbBefore['jobs'] === $dbAfter['jobs'] && $dbBefore['job_sources'] === $dbAfter['job_sources'],
    ],
    'methodology' => [
        'discovery_sources' => [
            'phase_e_confirmed_slugs',
            'web_search_site_recruitee_com',
            'phase_c_d_e_reject_lists',
            'tr_fintech_saas_startup_lists',
            'existing_jobsource_crosscheck',
            'slug_variants',
        ],
        'api_endpoint' => 'GET https://{slug}.recruitee.com/api/offers',
        'authentication' => 'none',
        'turkey_classification' => 'Phase B compatible — country/country_code/city/locations blob classifyLocation()',
        'null_location_policy' => 'counted separately; NOT auto-classified as Turkey',
        'freshness_max_age_days' => E2_MAX_POSTING_AGE_DAYS,
        'delay_between_requests_ms' => E2_DELAY_US / 1000,
    ],
    'metrics' => $totals,
    'threshold_evaluation' => $threshold,
    'confirmed_tr_employers' => array_map(fn (array $p): array => [
        'company_name' => $p['company_name'],
        'recruitee_slug' => $p['recruitee_slug'],
        'career_url' => $p['career_url'],
        'total_offers' => $p['total_offers'],
        'turkey_relevant' => $p['turkey_relevant'],
        'active_turkey_relevant' => $p['active_turkey_relevant'],
        'istanbul' => $p['istanbul'],
        'ankara' => $p['ankara'],
        'remote_turkey' => $p['remote_turkey'],
        'location_metadata_quality' => $p['location_metadata_quality'],
        'coverage_classification' => $p['coverage']['classification'],
        'existing_providers' => $p['coverage']['existing_providers'] ?? [],
        'net_new_tr_jobs' => $p['net_new_tr_jobs'],
        'freshness' => $p['freshness'],
    ], $trEmployers),
    'valid_boards_non_tr' => array_values(array_filter($probes, fn (array $p): bool => ($p['accessible'] ?? false) && ($p['total_offers'] ?? 0) > 0 && ($p['active_turkey_relevant'] ?? 0) === 0)),
    'rejected_candidates' => array_values(array_filter($probes, fn (array $p): bool => ! ($p['accessible'] ?? false) || (($p['total_offers'] ?? 0) === 0 && ($p['http_status'] ?? 0) !== 200))),
    'all_probes' => $probes,
    'existing_coverage_snapshot' => $existingCoverage,
    'final_decision' => $threshold['decision'],
];

$outPath = __DIR__.'/../storage/phase-e2-recruitee-discovery.json';
file_put_contents($outPath, json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

echo "Phase E.2 Recruitee discovery complete\n";
echo "Candidates: {$totals['candidates_researched']}\n";
echo "Confirmed TR employers: {$totals['confirmed_tr_employers']}\n";
echo "Net-new TR employers: {$totals['net_new_tr_employers']}\n";
echo "Active TR jobs: {$totals['active_turkey_relevant']}\n";
echo "Net-new TR jobs: {$totals['net_new_tr_jobs']}\n";
echo "DB writes: {$output['database_integrity']['database_writes']}\n";
echo "Decision: {$threshold['decision']}\n";
echo "Output: {$outPath}\n";

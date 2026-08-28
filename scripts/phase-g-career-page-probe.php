<?php

declare(strict_types=1);

/**
 * Phase G read-only career-page acquisition probe.
 * Fetches official career URLs only. Does NOT guess ATS slugs.
 * No DB writes.
 */

require __DIR__.'/ats-coverage-discovery-helpers.php';
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Job;
use App\Models\JobSource;
use Illuminate\Support\Facades\Http;

const G_UA = 'FitCareer-PhaseG-Discovery/1.0 (+read-only-audit)';
const G_DELAY_US = 200_000;

function gNorm(string $text): string
{
    $text = mb_strtolower($text);
    $text = str_replace(['ı', 'İ', 'ş', 'Ş', 'ğ', 'Ğ', 'ü', 'Ü', 'ö', 'Ö', 'ç', 'Ç', 'i̇'], ['i', 'i', 's', 's', 'g', 'g', 'u', 'u', 'o', 'o', 'c', 'c', 'i'], $text);

    return $text;
}

function gDbSnapshot(): array
{
    return [
        'jobs' => Job::count(),
        'job_sources' => JobSource::count(),
        'timestamp' => now()->toIso8601String(),
    ];
}

function gExistingCoverage(): array
{
    $out = [];
    foreach (JobSource::query()->get() as $source) {
        $out[] = [
            'name' => $source->name,
            'provider' => (string) ($source->config['provider'] ?? ''),
            'slug' => (string) ($source->config['site_slug'] ?? ''),
            'is_active' => (bool) $source->is_active,
        ];
    }

    return $out;
}

function gIsCovered(string $name, array $coverage): bool
{
    $n = preg_replace('/[^a-z0-9]+/', '', gNorm($name)) ?? '';
    foreach ($coverage as $row) {
        $rn = preg_replace('/[^a-z0-9]+/', '', gNorm((string) $row['name'])) ?? '';
        if ($n !== '' && $n === $rn) {
            return true;
        }
    }

    return false;
}

function gFetch(string $url): array
{
    $started = microtime(true);
    try {
        $response = Http::timeout(25)
            ->connectTimeout(12)
            ->withHeaders([
                'Accept' => 'text/html,application/json,application/ld+json;q=0.9,*/*;q=0.8',
                'User-Agent' => G_UA,
            ])
            ->get($url);
    } catch (Throwable $e) {
        return [
            'url' => $url,
            'http_status' => null,
            'error' => $e->getMessage(),
            'body' => '',
            'latency_ms' => (int) round((microtime(true) - $started) * 1000),
        ];
    }

    return [
        'url' => $url,
        'http_status' => $response->status(),
        'error' => null,
        'body' => (string) $response->body(),
        'content_type' => (string) $response->header('Content-Type'),
        'latency_ms' => (int) round((microtime(true) - $started) * 1000),
    ];
}

function gDetectPlatform(string $body, string $url): array
{
    $lower = mb_strtolower($body.' '.$url);
    $hits = [];
    $map = [
        'lever' => ['jobs.lever.co', 'api.lever.co'],
        'greenhouse' => ['greenhouse.io', 'boards-api.greenhouse.io'],
        'workable' => ['apply.workable.com', 'workable.com'],
        'ashby' => ['ashbyhq.com', 'jobs.ashbyhq.com'],
        'recruitee' => ['recruitee.com'],
        'teamtailor' => ['teamtailor.com'],
        'personio' => ['personio.de', 'jobs.personio'],
        'smartrecruiters' => ['smartrecruiters.com'],
        'manatal' => ['careers-page.com', 'manatal'],
        'successfactors' => ['successfactors', 'sapsf.com'],
        'workday' => ['myworkdayjobs.com', 'workday.com'],
        'peoplise' => ['peoplise.com'],
        'linkedin' => ['linkedin.com/jobs', 'linkedin.com/company'],
        'kariyer_net' => ['kariyer.net'],
        'secretcv' => ['secretcv.com'],
        'yenibiris' => ['yenibiris.com'],
    ];
    foreach ($map as $platform => $needles) {
        foreach ($needles as $needle) {
            if (str_contains($lower, $needle)) {
                $hits[] = $platform;
                break;
            }
        }
    }

    return array_values(array_unique($hits));
}

function gJsonLdJobCount(string $body): int
{
    return preg_match_all('/"@type"\s*:\s*"JobPosting"/i', $body) ?: 0;
}

function gBlocked(string $body, ?int $status): bool
{
    if (in_array($status, [401, 403, 429], true)) {
        return true;
    }
    $lower = mb_strtolower($body);

    return str_contains($lower, 'cf-browser-verification')
        || str_contains($lower, 'attention required')
        || str_contains($lower, 'perimeterx')
        || str_contains($lower, 'access denied');
}

function gTitleSignals(array $titles): array
{
    $counts = ['junior' => 0, 'frontend' => 0, 'backend' => 0, 'qa' => 0, 'devops' => 0, 'mobile' => 0, 'data' => 0, 'intern' => 0, 'tech' => 0];
    foreach ($titles as $title) {
        $t = ' '.gNorm((string) $title).' ';
        $tech = false;
        if (str_contains($t, ' intern') || str_contains($t, 'staj') || str_contains($t, 'internship')) {
            $counts['intern']++;
            $tech = true;
        }
        if (str_contains($t, 'junior') || str_contains($t, ' jr ') || str_contains($t, 'yeni mezun') || str_contains($t, 'entry')) {
            $counts['junior']++;
            $tech = true;
        }
        if (str_contains($t, 'frontend') || str_contains($t, 'front-end') || str_contains($t, 'front end') || str_contains($t, 'react')) {
            $counts['frontend']++;
            $tech = true;
        }
        if (str_contains($t, 'backend') || str_contains($t, 'back-end') || str_contains($t, 'java ') || str_contains($t, 'laravel') || str_contains($t, '.net')) {
            $counts['backend']++;
            $tech = true;
        }
        if (str_contains($t, ' qa') || str_contains($t, 'quality assurance') || str_contains($t, 'test engineer') || str_contains($t, 'sdet')) {
            $counts['qa']++;
            $tech = true;
        }
        if (str_contains($t, 'devops') || str_contains($t, 'sre') || str_contains($t, 'cloud engineer') || str_contains($t, 'platform engineer')) {
            $counts['devops']++;
            $tech = true;
        }
        if (str_contains($t, 'android') || str_contains($t, 'ios') || str_contains($t, 'flutter') || str_contains($t, 'mobile')) {
            $counts['mobile']++;
            $tech = true;
        }
        if (str_contains($t, 'data ') || str_contains($t, 'machine learning') || str_contains($t, ' ml ') || str_contains($t, 'ai engineer')) {
            $counts['data']++;
            $tech = true;
        }
        if ($tech || str_contains($t, 'software') || str_contains($t, 'developer') || str_contains($t, 'engineer') || str_contains($t, 'yazilim')) {
            $counts['tech']++;
        }
    }

    return $counts;
}

function gClassify(array $row): string
{
    if ($row['already_in_fitcareer']) {
        return 'SEED_EXISTING_PROVIDER';
    }
    if ($row['blocked']) {
        return 'BLOCKED_OR_HIGH_RISK';
    }
    $platforms = $row['platforms'];
    if ($platforms === ['linkedin'] || (in_array('linkedin', $platforms, true) && $row['json_ld_jobs'] === 0 && $row['structured_titles'] === [])) {
        if ($row['http_status'] === 200 && $row['json_ld_jobs'] === 0 && count($platforms) <= 2 && in_array('linkedin', $platforms, true) && ! array_intersect($platforms, ['lever', 'greenhouse', 'workable', 'ashby', 'recruitee', 'teamtailor', 'manatal'])) {
            return 'LINKEDIN_ONLY';
        }
    }
    if (array_intersect($platforms, ['lever', 'greenhouse', 'workable', 'ashby', 'recruitee'])) {
        return $row['already_in_fitcareer'] ? 'SEED_EXISTING_PROVIDER' : 'PUBLIC_STRUCTURED_API';
    }
    if (in_array('teamtailor', $platforms, true) || in_array('manatal', $platforms, true)) {
        return 'PUBLIC_STRUCTURED_API';
    }
    if ($row['json_ld_jobs'] >= 1) {
        return 'JSON_LD_FEASIBLE';
    }
    if (in_array('peoplise', $platforms, true) || in_array('successfactors', $platforms, true) || in_array('workday', $platforms, true)) {
        return 'OFFICIAL_PARTNERSHIP_REQUIRED';
    }
    if (in_array('kariyer_net', $platforms, true) && $row['json_ld_jobs'] === 0) {
        return 'BLOCKED_OR_HIGH_RISK';
    }
    if ($row['http_status'] !== 200) {
        return 'NO_CURRENT_SUPPLY';
    }
    if ($row['body_bytes'] > 2000) {
        return 'CUSTOM_ADAPTER_FEASIBLE';
    }

    return 'NO_CURRENT_SUPPLY';
}

function gCatalog(): array
{
    return [
        // Already in FitCareer
        ['name' => 'Commencis', 'industry' => 'software_agency', 'career_url' => 'https://jobs.lever.co/commencis'],
        ['name' => 'Midas', 'industry' => 'fintech', 'career_url' => 'https://jobs.lever.co/getmidas'],
        ['name' => 'Insider One', 'industry' => 'saas', 'career_url' => 'https://jobs.lever.co/insiderone'],
        ['name' => 'Trendyol', 'industry' => 'ecommerce', 'career_url' => 'https://jobs.lever.co/trendyol'],
        ['name' => 'Dream Games', 'industry' => 'gaming', 'career_url' => 'https://jobs.lever.co/dreamgames'],
        ['name' => 'iyzico', 'industry' => 'fintech', 'career_url' => 'https://jobs.lever.co/iyzico'],
        ['name' => 'Grand Games', 'industry' => 'gaming', 'career_url' => 'https://jobs.lever.co/grand'],
        ['name' => 'Ajax Systems', 'industry' => 'cybersecurity', 'career_url' => 'https://jobs.lever.co/ajax'],
        ['name' => 'Wingie Enuygun', 'industry' => 'travel_tech', 'career_url' => 'https://apply.workable.com/wingieenuygun'],
        ['name' => 'Vertigo Games', 'industry' => 'gaming', 'career_url' => 'https://apply.workable.com/vertigogames'],
        ['name' => 'Sanction Scanner', 'industry' => 'fintech', 'career_url' => 'https://apply.workable.com/sanction-scanner'],
        ['name' => 'Lucida AI', 'industry' => 'ai', 'career_url' => 'https://apply.workable.com/lucida-ai'],
        ['name' => 'NewMind AI', 'industry' => 'ai', 'career_url' => 'https://apply.workable.com/newmindai'],
        ['name' => 'VavaCars', 'industry' => 'marketplace', 'career_url' => 'https://apply.workable.com/vavacars'],
        ['name' => 'Codeway', 'industry' => 'mobile', 'career_url' => 'https://jobs.ashbyhq.com/codeway'],
        ['name' => 'Bigger Games', 'industry' => 'gaming', 'career_url' => 'https://jobs.ashbyhq.com/biggergames'],
        ['name' => 'Good Job Games', 'industry' => 'gaming', 'career_url' => 'https://job-boards.greenhouse.io/goodjobgames'],
        ['name' => 'Mikro Yazılım', 'industry' => 'enterprise_software', 'career_url' => 'https://mikroyazilim.recruitee.com/'],
        ['name' => 'Paraşüt', 'industry' => 'saas', 'career_url' => 'https://parasut.recruitee.com/'],
        ['name' => 'Trio Mobil', 'industry' => 'mobile', 'career_url' => 'https://triomobil.recruitee.com/'],

        // Confirmed structured but unseeded / other platforms
        ['name' => 'Macellan', 'industry' => 'mobile', 'career_url' => 'https://macellan.recruitee.com/'],
        ['name' => 'Çiçeksepeti', 'industry' => 'ecommerce', 'career_url' => 'https://jobs.lever.co/ciceksepeti'],
        ['name' => 'Ticimax', 'industry' => 'ecommerce_saas', 'career_url' => 'https://teamblueticimax.teamtailor.com/'],
        ['name' => 'DFDS Türkiye', 'industry' => 'logistics_tech', 'career_url' => 'https://dfdsturkey.teamtailor.com/'],
        ['name' => 'Getir', 'industry' => 'logistics_tech', 'career_url' => 'https://getir.careers-page.com/'],
        ['name' => 'Getir Careers Site', 'industry' => 'logistics_tech', 'career_url' => 'https://career.getir.com/'],
        ['name' => 'Kodland', 'industry' => 'edtech', 'career_url' => 'https://kodland.recruitee.com/'],

        // Large TR tech employers — official career pages
        ['name' => 'Hepsiburada', 'industry' => 'ecommerce', 'career_url' => 'https://kurumsal.hepsiburada.com/tr/kariyer'],
        ['name' => 'Hepsiburada Contact', 'industry' => 'ecommerce', 'career_url' => 'https://www.hepsiburada.com/iletisim'],
        ['name' => 'Softtech', 'industry' => 'banking_tech', 'career_url' => 'https://softtech.com.tr/kariyer'],
        ['name' => 'Logo Yazılım', 'industry' => 'enterprise_software', 'career_url' => 'https://www.logo.com.tr/kariyer'],
        ['name' => 'Papara', 'industry' => 'fintech', 'career_url' => 'https://www.papara.com/career'],
        ['name' => 'Jotform', 'industry' => 'saas', 'career_url' => 'https://www.jotform.com/jobs'],
        ['name' => 'Teknasyon', 'industry' => 'mobile', 'career_url' => 'https://teknasyon.com/career'],
        ['name' => 'Turkcell Kariyer', 'industry' => 'telecom', 'career_url' => 'https://kariyer.turkcell.com.tr'],
        ['name' => 'Netaş', 'industry' => 'enterprise_software', 'career_url' => 'https://www.netas.com.tr/kariyer'],
        ['name' => 'Intertech', 'industry' => 'banking_tech', 'career_url' => 'https://www.intertech.com.tr/kariyer'],
        ['name' => 'OBSS', 'industry' => 'software_agency', 'career_url' => 'https://obss.tech/careers'],
        ['name' => 'Etiya', 'industry' => 'telecom_software', 'career_url' => 'https://www.etiya.com/careers'],
        ['name' => 'Sahibinden', 'industry' => 'marketplace', 'career_url' => 'https://www.sahibinden.com/kariyer'],
        ['name' => 'n11', 'industry' => 'ecommerce', 'career_url' => 'https://www.n11.com/kariyer'],
        ['name' => 'Yemeksepeti', 'industry' => 'ecommerce', 'career_url' => 'https://careers.deliveryhero.com'],
        ['name' => 'Flo', 'industry' => 'ecommerce', 'career_url' => 'https://www.flo.com.tr/kariyer'],
        ['name' => 'Boyner', 'industry' => 'ecommerce', 'career_url' => 'https://kariyer.boyner.com.tr'],
        ['name' => 'Peak Games', 'industry' => 'gaming', 'career_url' => 'https://jobs.lever.co/peakgames'],
        ['name' => 'Rollic', 'industry' => 'gaming', 'career_url' => 'https://rollic.com/careers'],
        ['name' => 'Craftgate', 'industry' => 'fintech', 'career_url' => 'https://craftgate.io/careers'],
        ['name' => 'Figopara', 'industry' => 'fintech', 'career_url' => 'https://figopara.com/careers'],
        ['name' => 'Param', 'industry' => 'fintech', 'career_url' => 'https://param.com.tr/tr/kariyer'],
        ['name' => 'PayTR', 'industry' => 'fintech', 'career_url' => 'https://www.paytr.com/kariyer'],
        ['name' => 'Kolay İK', 'industry' => 'saas', 'career_url' => 'https://www.kolayik.com/kariyer'],
        ['name' => 'ikas', 'industry' => 'ecommerce_saas', 'career_url' => 'https://ikas.com/tr/kariyer'],
        ['name' => 'IdeaSoft', 'industry' => 'ecommerce_saas', 'career_url' => 'https://www.ideasoft.com.tr/kariyer'],
        ['name' => 'BiTaksi', 'industry' => 'mobility', 'career_url' => 'https://bitaksi.com/kariyer'],
        ['name' => 'Martı', 'industry' => 'mobility', 'career_url' => 'https://www.marti.tech/careers'],
        ['name' => 'Togg', 'industry' => 'automotive_tech', 'career_url' => 'https://www.togg.com.tr/kariyer'],
        ['name' => 'Vodafone Turkey', 'industry' => 'telecom', 'career_url' => 'https://www.vodafone.com.tr/kariyer'],
        ['name' => 'Türk Telekom', 'industry' => 'telecom', 'career_url' => 'https://www.turktelekom.com.tr/kariyer'],
        ['name' => 'Arçelik', 'industry' => 'enterprise', 'career_url' => 'https://www.arcelikglobal.com/tr/kariyer'],
        ['name' => 'Vestel', 'industry' => 'enterprise', 'career_url' => 'https://www.vestel.com.tr/kariyer'],
        ['name' => 'KoçSistem', 'industry' => 'enterprise_software', 'career_url' => 'https://www.kocsistem.com.tr/kariyer'],
        ['name' => 'Innova', 'industry' => 'enterprise_software', 'career_url' => 'https://www.innova.com.tr/kariyer'],
        ['name' => 'Aselsan', 'industry' => 'defense_tech', 'career_url' => 'https://www.aselsan.com.tr/tr/kariyer'],
        ['name' => 'Havelsan', 'industry' => 'defense_tech', 'career_url' => 'https://www.havelsan.com.tr/kariyer'],
        ['name' => 'Baykar', 'industry' => 'defense_tech', 'career_url' => 'https://kariyer.baykartech.com'],
        ['name' => 'Roketsan', 'industry' => 'defense_tech', 'career_url' => 'https://www.roketsan.com.tr/tr/kariyer'],
        ['name' => 'Siemens Turkey', 'industry' => 'enterprise', 'career_url' => 'https://jobs.siemens.com/careers?query=&location=Turkey'],
        ['name' => 'Amadeus Istanbul', 'industry' => 'travel_tech', 'career_url' => 'https://careers.amadeus.com'],
        ['name' => 'Makrops', 'industry' => 'software_agency', 'career_url' => 'https://makrops.com/en/careers'],
        ['name' => 'Protel', 'industry' => 'saas', 'career_url' => 'https://www.protel.com.tr/kariyer'],
        ['name' => 'HotelRunner', 'industry' => 'travel_tech', 'career_url' => 'https://www.hotelrunner.com/careers'],
        ['name' => 'Hitit', 'industry' => 'travel_tech', 'career_url' => 'https://hitit.com/careers'],
        ['name' => 'Pegasus', 'industry' => 'travel_tech', 'career_url' => 'https://www.flypgs.com/kariyer'],
        ['name' => 'THY', 'industry' => 'travel_tech', 'career_url' => 'https://kariyer.thy.com'],
        ['name' => 'Garanti BBVA', 'industry' => 'banking_tech', 'career_url' => 'https://www.garantibbva.com.tr/kariyer'],
        ['name' => 'Akbank', 'industry' => 'banking_tech', 'career_url' => 'https://www.akbank.com/kariyer'],
        ['name' => 'İş Bankası', 'industry' => 'banking_tech', 'career_url' => 'https://www.isbank.com.tr/kariyer'],
        ['name' => 'Yapı Kredi', 'industry' => 'banking_tech', 'career_url' => 'https://www.yapikredi.com.tr/kariyer'],
        ['name' => 'QNB Finansbank', 'industry' => 'banking_tech', 'career_url' => 'https://www.qnbfinansbank.com/kariyer'],
        ['name' => 'Sipay', 'industry' => 'fintech', 'career_url' => 'https://sipay.com.tr/kariyer'],
        ['name' => 'Paycell', 'industry' => 'fintech', 'career_url' => 'https://www.paycell.com.tr/kariyer'],
        ['name' => 'Ininal', 'industry' => 'fintech', 'career_url' => 'https://www.ininal.com/kariyer'],
        ['name' => 'Ozan SuperApp', 'industry' => 'fintech', 'career_url' => 'https://ozan.com/careers'],
        ['name' => 'Hangikredi', 'industry' => 'fintech', 'career_url' => 'https://www.hangikredi.com/kariyer'],
        ['name' => 'Moka United', 'industry' => 'fintech', 'career_url' => 'https://mokaunited.com/careers'],
        ['name' => 'Pisano', 'industry' => 'saas', 'career_url' => 'https://www.pisano.com/careers'],
        ['name' => 'Infina', 'industry' => 'fintech', 'career_url' => 'https://www.infina.com.tr/kariyer'],
        ['name' => 'Sovos Foriba', 'industry' => 'saas', 'career_url' => 'https://sovos.com/careers'],
        ['name' => 'TAV Technologies', 'industry' => 'enterprise_software', 'career_url' => 'https://www.tavtechnologies.aero/careers'],
        ['name' => 'Ford Otosan', 'industry' => 'automotive_tech', 'career_url' => 'https://www.fordotosan.com.tr/tr/kariyer'],
        ['name' => 'TOFAŞ', 'industry' => 'automotive_tech', 'career_url' => 'https://www.tofas.com.tr/kariyer'],
        ['name' => 'LC Waikiki', 'industry' => 'ecommerce', 'career_url' => 'https://www.lcwaikiki.com/tr-TR/TR/kariyer'],
        ['name' => 'Defacto', 'industry' => 'ecommerce', 'career_url' => 'https://www.defacto.com.tr/kariyer'],
        ['name' => 'Migros', 'industry' => 'ecommerce', 'career_url' => 'https://www.migros.com.tr/kariyer'],
        ['name' => 'CarrefourSA', 'industry' => 'ecommerce', 'career_url' => 'https://www.carrefoursa.com/kariyer'],
        ['name' => 'Biletbank', 'industry' => 'travel_tech', 'career_url' => 'https://www.biletbank.com/kariyer'],
        ['name' => 'Obilet', 'industry' => 'travel_tech', 'career_url' => 'https://www.obilet.com/kariyer'],
        ['name' => 'Setur', 'industry' => 'travel_tech', 'career_url' => 'https://www.setur.com.tr/kariyer'],
        ['name' => 'STM', 'industry' => 'defense_tech', 'career_url' => 'https://www.stm.com.tr/kariyer'],
        ['name' => 'MilSOFT', 'industry' => 'defense_tech', 'career_url' => 'https://www.milsoft.com.tr/kariyer'],
        ['name' => 'KoçDigital', 'industry' => 'enterprise_software', 'career_url' => 'https://www.kocdigital.com/kariyer'],
        ['name' => 'Accenture Turkey', 'industry' => 'software_agency', 'career_url' => 'https://www.accenture.com/tr-en/careers'],
        ['name' => 'IBM Turkey', 'industry' => 'enterprise_software', 'career_url' => 'https://www.ibm.com/tr-tr/careers'],
        ['name' => 'Microsoft Turkey', 'industry' => 'enterprise_software', 'career_url' => 'https://careers.microsoft.com/v2/global/en/locations/turkey.html'],
        ['name' => 'Amazon Turkey', 'industry' => 'ecommerce', 'career_url' => 'https://www.amazon.jobs/en/locations/istanbul-turkey'],
        ['name' => 'Huawei Turkey', 'industry' => 'telecom', 'career_url' => 'https://career.huawei.com/reccampportal/portal5/index.html'],
        ['name' => 'Bosch Turkey', 'industry' => 'enterprise', 'career_url' => 'https://www.bosch.com.tr/kariyer'],
        ['name' => 'Analog Devices Turkey', 'industry' => 'semiconductor', 'career_url' => 'https://analogdevices.wd1.myworkdayjobs.com'],
        ['name' => 'Armut', 'industry' => 'marketplace', 'career_url' => 'https://armut.com/kariyer'],
        ['name' => 'Hop', 'industry' => 'mobility', 'career_url' => 'https://hop.ie/careers'],
        ['name' => 'Hepsijet', 'industry' => 'logistics_tech', 'career_url' => 'https://www.hepsijet.com/kariyer'],
        ['name' => 'Trendyol Tech Blog Careers', 'industry' => 'ecommerce', 'career_url' => 'https://jobs.lever.co/trendyol'],
        ['name' => 'Useinsider', 'industry' => 'saas', 'career_url' => 'https://useinsider.com/careers'],
        ['name' => 'Insider Careers', 'industry' => 'saas', 'career_url' => 'https://jobs.lever.co/insiderone'],
        ['name' => 'Shopside', 'industry' => 'saas', 'career_url' => 'https://www.shopside.com.tr/kariyer'],
        ['name' => 'Emukellef', 'industry' => 'saas', 'career_url' => 'https://www.emukellef.com.tr/kariyer'],
        ['name' => 'Zirve Yazılım', 'industry' => 'enterprise_software', 'career_url' => 'https://zirvebilgiteknolojilerisanayiticaretanonimsirketi.recruitee.com/'],
        ['name' => 'Nucs AI', 'industry' => 'ai', 'career_url' => 'https://nucsai.recruitee.com/'],
        ['name' => 'DoktorTakvimi', 'industry' => 'healthtech', 'career_url' => 'https://jobs.ashbyhq.com/doktortakvimi'],
        ['name' => 'Medsien', 'industry' => 'healthtech', 'career_url' => 'https://job-boards.greenhouse.io/medsien'],
        ['name' => 'FERASET', 'industry' => 'fintech', 'career_url' => 'https://apply.workable.com/feraset'],
        ['name' => 'Agave Games', 'industry' => 'gaming', 'career_url' => 'https://jobs.ashbyhq.com/agavegames'],
        ['name' => 'Bold Games', 'industry' => 'gaming', 'career_url' => 'https://jobs.ashbyhq.com/boldgames'],
        ['name' => 'Krila Consultancy', 'industry' => 'recruitment', 'career_url' => 'https://krila.recruitee.com/'],
        ['name' => 'Makrops Junior Role', 'industry' => 'software_agency', 'career_url' => 'https://makrops.com/en/careers/junior-full-stack-developer'],
        ['name' => 'Ticimax Jobs JSON', 'industry' => 'ecommerce_saas', 'career_url' => 'https://teamblueticimax.teamtailor.com/jobs.json'],
        ['name' => 'DFDS Jobs JSON', 'industry' => 'logistics_tech', 'career_url' => 'https://dfdsturkey.teamtailor.com/jobs.json'],
        ['name' => 'Getir Manatal API', 'industry' => 'logistics_tech', 'career_url' => 'https://api.careers-page.com/open/v1/career-pages/getir/job-posts?size=50'],
        ['name' => 'Peoplise', 'industry' => 'hrtech', 'career_url' => 'https://www.peoplise.com'],
        ['name' => 'Kariyer.net Jobs', 'industry' => 'job_board', 'career_url' => 'https://www.kariyer.net/is-ilanlari/yazilim'],
        ['name' => 'LinkedIn Jobs Istanbul Software', 'industry' => 'job_board', 'career_url' => 'https://www.linkedin.com/jobs/software-engineer-jobs-istanbul'],
    ];
}

$before = gDbSnapshot();
$coverage = gExistingCoverage();
$results = [];

foreach (gCatalog() as $i => $company) {
    $fetch = gFetch($company['career_url']);
    $body = $fetch['body'] ?? '';
    $status = $fetch['http_status'] ?? null;
    $platforms = $body !== '' ? gDetectPlatform($body, $company['career_url']) : [];
    $jsonLd = $body !== '' ? gJsonLdJobCount($body) : 0;

    $titles = [];
    $json = json_decode($body, true);
    if (is_array($json)) {
        if (isset($json['jobs']) && is_array($json['jobs'])) {
            foreach ($json['jobs'] as $job) {
                if (is_array($job)) {
                    $titles[] = (string) ($job['title'] ?? $job['text'] ?? '');
                }
            }
        } elseif (isset($json['data']) && is_array($json['data'])) {
            foreach ($json['data'] as $job) {
                if (is_array($job)) {
                    $titles[] = (string) ($job['title'] ?? $job['name'] ?? '');
                }
            }
        } elseif (array_is_list($json)) {
            foreach ($json as $job) {
                if (is_array($job)) {
                    $titles[] = (string) ($job['title'] ?? $job['text'] ?? '');
                }
            }
        }
    }

    $signals = gTitleSignals($titles);
    $row = [
        'company' => $company['name'],
        'industry' => $company['industry'],
        'career_url' => $company['career_url'],
        'http_status' => $status,
        'accessible' => $status === 200,
        'blocked' => gBlocked($body, $status),
        'platforms' => $platforms,
        'json_ld_jobs' => $jsonLd,
        'structured_titles' => array_values(array_filter($titles)),
        'structured_job_count' => count(array_filter($titles)),
        'persona_signals' => $signals,
        'already_in_fitcareer' => gIsCovered($company['name'], $coverage),
        'body_bytes' => strlen($body),
        'latency_ms' => $fetch['latency_ms'] ?? null,
        'error' => $fetch['error'] ?? null,
    ];
    $row['classification'] = gClassify($row);
    $results[] = $row;

    echo sprintf(
        "[%d] %s HTTP=%s class=%s platforms=%s jsonld=%d titles=%d\n",
        $i + 1,
        $company['name'],
        (string) $status,
        $row['classification'],
        implode(',', $platforms) ?: '-',
        $jsonLd,
        $row['structured_job_count'],
    );
    usleep(G_DELAY_US);
}

$after = gDbSnapshot();
$byClass = [];
foreach ($results as $row) {
    $byClass[$row['classification']] = ($byClass[$row['classification']] ?? 0) + 1;
}

$output = [
    'audit' => [
        'title' => 'Phase G career-page acquisition probe',
        'generated_at' => now()->toIso8601String(),
        'scope' => 'read_only',
        'method' => 'official_career_url_fetch_no_slug_guessing',
    ],
    'database_integrity' => [
        'before' => $before,
        'after' => $after,
        'writes' => ($before['jobs'] !== $after['jobs'] || $before['job_sources'] !== $after['job_sources']) ? 1 : 0,
    ],
    'counts' => [
        'employers_checked' => count($results),
        'by_classification' => $byClass,
    ],
    'results' => $results,
];

$out = __DIR__.'/../storage/phase-g-career-page-probe.json';
file_put_contents($out, json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
echo "\nChecked: ".count($results)."\n";
echo 'Writes: '.$output['database_integrity']['writes']."\n";
echo json_encode($byClass, JSON_UNESCAPED_UNICODE)."\n";
echo "Output: {$out}\n";

<?php

declare(strict_types=1);

require __DIR__.'/ats-coverage-discovery-helpers.php';
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

const PHASE_C_USER_AGENT = 'FitCareer-PhaseC-Supplement/1.0 (+read-only-audit)';

function fetchHtml(string $url): array
{
    try {
        $response = Illuminate\Support\Facades\Http::timeout(30)
            ->withHeaders(['User-Agent' => PHASE_C_USER_AGENT, 'Accept' => 'text/html,*/*'])
            ->get($url);
        $body = (string) $response->body();
        $lower = mb_strtolower($body);

        return [
            'url' => $url,
            'status' => $response->status(),
            'bytes' => strlen($body),
            'json_ld_jobs' => preg_match_all('/"@type"\s*:\s*"JobPosting"/i', $body) ?: 0,
            'fingerprints' => array_values(array_unique(array_filter([
                str_contains($lower, 'lever.co') ? 'lever' : null,
                str_contains($lower, 'greenhouse.io') ? 'greenhouse' : null,
                str_contains($lower, 'workable.com') ? 'workable' : null,
                str_contains($lower, 'ashbyhq.com') ? 'ashby' : null,
                str_contains($lower, 'smartrecruiters.com') ? 'smartrecruiters' : null,
                str_contains($lower, 'teamtailor.com') ? 'teamtailor' : null,
                str_contains($lower, 'personio.de') ? 'personio' : null,
                str_contains($lower, 'careers-page.com') ? 'careers-page' : null,
                str_contains($lower, 'linkedin.com') ? 'linkedin' : null,
            ]))),
        ];
    } catch (Throwable $e) {
        return ['url' => $url, 'status' => null, 'error' => $e->getMessage()];
    }
}

$ats = [
    'papara_lever' => httpGetJson('https://api.lever.co/v0/postings/papara?mode=json&limit=200'),
    'craftgate_lever' => httpGetJson('https://api.lever.co/v0/postings/craftgate?mode=json&limit=200'),
    'peak_lever' => httpGetJson('https://api.lever.co/v0/postings/peakgames?mode=json&limit=200'),
    'nomagic_lever' => httpGetJson('https://api.lever.co/v0/postings/nomagic?mode=json&limit=200'),
    'bitaksi_lever' => httpGetJson('https://api.lever.co/v0/postings/bitaksi?mode=json&limit=200'),
    'figopara_workable' => httpGetJson('https://apply.workable.com/api/v1/widget/accounts/figopara?details=true'),
    'jotform_workable' => httpGetJson('https://apply.workable.com/api/v1/widget/accounts/jotform?details=true'),
    'peak_gh' => httpGetJson('https://boards-api.greenhouse.io/v1/boards/peakgames/jobs?content=true'),
    'rollic_gh' => httpGetJson('https://boards-api.greenhouse.io/v1/boards/rollic/jobs?content=true'),
];

$sr = [];
foreach (['Papara', 'Getir', 'Hepsiburada', 'Turkcell', 'Trendyol', 'Param', 'Boyner', 'Arcelik', 'Logo', 'Softtech'] as $companyId) {
    $sr[$companyId] = httpGetJson('https://api.smartrecruiters.com/v1/companies/'.$companyId.'/postings?limit=100');
    usleep(250_000);
}

$pages = [
    'getir_careers_page' => fetchHtml('https://getir.careers-page.com/'),
    'career_getir' => fetchHtml('https://career.getir.com/'),
    'papara_careers' => fetchHtml('https://careers.papara.com/'),
    'hepsiburada_kurumsal' => fetchHtml('https://kurumsal.hepsiburada.com/tr/kariyer'),
    'jotform_jobs' => fetchHtml('https://www.jotform.com/jobs/'),
    'peak_careers' => fetchHtml('https://www.peak.com/careers/'),
    'turkcell_kariyer' => fetchHtml('https://kariyer.turkcell.com.tr/'),
    'garanti_tech' => fetchHtml('https://kariyer.garantibbvatechnology.com.tr/'),
    'intertech' => fetchHtml('https://kariyer.intertech.com.tr/'),
    'softtech' => fetchHtml('https://kariyer.softtech.com.tr/'),
    'teknasyon' => fetchHtml('https://teknasyon.com/career/'),
    'craftgate' => fetchHtml('https://craftgate.io/careers'),
];

echo json_encode(['ats' => $ats, 'smartrecruiters' => $sr, 'pages' => $pages], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n";

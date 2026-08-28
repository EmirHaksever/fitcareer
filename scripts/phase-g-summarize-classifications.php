<?php

declare(strict_types=1);

$p = json_decode(file_get_contents(__DIR__.'/../storage/phase-g-career-page-probe.json'), true);
$by = [];
foreach ($p['results'] as $r) {
    $c = $r['classification'];
    $by[$c][] = $r;
}

foreach ($by as $k => $rows) {
    echo "\n==== {$k} (".count($rows).") ====\n";
    foreach ($rows as $r) {
        $plat = implode(',', $r['platforms'] ?? []);
        $s = $r['persona_signals'];
        $in = ! empty($r['already_in_fitcareer']) ? 'Y' : 'N';
        echo $r['company'].' | '.$r['industry'].' | http='.$r['http_status'].' | plat='.$plat
            .' | jsonld='.$r['json_ld_jobs'].' | jobs='.$r['structured_job_count']
            .' | in_db='.$in
            .' | jr='.$s['junior'].' fe='.$s['frontend'].' be='.$s['backend']
            .' qa='.$s['qa'].' do='.$s['devops'].' mo='.$s['mobile']
            .' da='.$s['data'].' in='.$s['intern'].' tech='.$s['tech']
            ."\n";
    }
}

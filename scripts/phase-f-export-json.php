<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$json = json_decode(file_get_contents(__DIR__.'/../storage/phase-f-job-market-coverage.json'), true);
$json['inventory']['turkey_visible_heuristic'] = $json['inventory']['turkey_visible'];
$json['inventory']['turkey_visible_official'] = 225;
$json['inventory']['turkey_visible'] = 225;

$json['methodology'] = [
    'persona_thresholds' => [
        'CRITICAL_SUPPLY_GAP' => '<5 TR jobs OR <2 employers',
        'WEAK_SUPPLY' => '<15 TR jobs OR <3 employers OR top employer >50%',
        'MODERATE_SUPPLY' => '<30 TR jobs OR <5 employers',
        'HEALTHY_SUPPLY' => '>=30 TR jobs AND >=5 employers AND top employer <=50%',
    ],
    'fit_actionable_threshold' => '>=10 relevant TR jobs AND >=3 unique employers',
    'turkey_visible_official_source' => 'LocationClassificationService::applyTurkeyRelevantScope()',
    'search_simulation_source' => 'MySqlFulltextJobSearchRepository whereFullText + Turkey scope',
];

$json['verdict'] = [
    'phase_f_status' => 'COMPLETE',
    'application_code_changed' => false,
    'final_verdict' => 'READY FOR NEXT IMPLEMENTATION',
    'biggest_supply_gap' => 'Critical gaps in core tech personas (junior/frontend/QA/DevOps) despite adequate aggregate counts',
    'recommended_next_task' => 'Phase F.1 targeted TR tech employer discovery on existing ATS providers',
];

$json['facts_vs_inference'] = [
    'facts' => [
        '450 published active jobs in DB',
        '225 jobs visible under official Turkey scope (user default search)',
        '94% experience_level null on TR-visible jobs',
        'QA FULLTEXT search returns 0 vs Quality Assurance returns 46',
        'Zynga/OLIVER is_active=true in DB; seed-greenhouse-sources.php sets is_active=false',
        '0 DB writes during audit',
    ],
    'inferences' => [
        'Platform is effectively Istanbul-centric for tech roles (74.7% of heuristic TR set)',
        'Fit Score experience signal is largely inert for scraped jobs',
        'Another generic ATS provider is lower ROI than targeted employer discovery',
        'Global jobs (206 in DB) are hidden from default search — not a user-facing leak',
    ],
];

$out = __DIR__.'/../FITCAREER_PHASE_F_JOB_MARKET_COVERAGE_AUDIT.json';
file_put_contents($out, json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
echo "Written: {$out}\n";

<?php

declare(strict_types=1);

/**
 * Ghost Job Observation — READ-ONLY reporting layer.
 *
 * Usage:
 *   php scripts/ghost-job-observation.php
 *   php scripts/ghost-job-observation.php --provider=lever
 *   php scripts/ghost-job-observation.php --source=8
 *   php scripts/ghost-job-observation.php --limit=50
 */

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Enums\JobOrigin;
use App\Enums\JobStatus;
use App\Models\Job;
use App\Services\Job\GhostJobObservationService;
use App\Services\Job\GhostJobScoreService;
use Illuminate\Support\Carbon;

const OUTPUT_JSON = __DIR__.'/../GHOST_JOB_OBSERVATION.json';
const OUTPUT_MD = __DIR__.'/../GHOST_JOB_OBSERVATION.md';
const SNAPSHOT_DIR = __DIR__.'/../storage/ghost-observations';

$providerFilter = null;
$sourceFilter = null;
$limit = null;

foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--provider=')) {
        $providerFilter = substr($arg, strlen('--provider='));
    } elseif (str_starts_with($arg, '--source=')) {
        $sourceFilter = (int) substr($arg, strlen('--source='));
    } elseif (str_starts_with($arg, '--limit=')) {
        $limit = (int) substr($arg, strlen('--limit='));
    }
}

$referenceDate = Carbon::now();
$observationService = new GhostJobObservationService(new GhostJobScoreService);

$query = Job::query()
    ->with('sourceProvider')
    ->where('source', JobOrigin::Scraped)
    ->where('status', JobStatus::Published)
    ->orderBy('id');

if ($sourceFilter !== null) {
    $query->where('job_source_id', $sourceFilter);
}

if ($limit !== null && $limit > 0) {
    $query->limit($limit);
}

$jobs = $query->get();

if ($providerFilter !== null) {
    $jobs = $jobs->filter(function (Job $job) use ($providerFilter): bool {
        $provider = $job->sourceProvider?->config['provider'] ?? 'unknown';

        return $provider === $providerFilter;
    })->values();
}

$report = $observationService->analyze($jobs, $referenceDate);

$previousSnapshot = findLatestPreviousSnapshot(SNAPSHOT_DIR);
$previousReport = $previousSnapshot !== null
    ? json_decode((string) file_get_contents($previousSnapshot), true)
    : null;

$report['observation_history_comparison'] = $observationService->compareSnapshots(
    is_array($previousReport) ? $previousReport : null,
    $report,
);

$report['filters'] = [
    'provider' => $providerFilter,
    'source_id' => $sourceFilter,
    'limit' => $limit,
];

if (! is_dir(SNAPSHOT_DIR)) {
    mkdir(SNAPSHOT_DIR, 0755, true);
}

$timestamp = $referenceDate->format('Y-m-d_His');
$snapshotJson = SNAPSHOT_DIR.'/ghost-job-observation-'.$timestamp.'.json';
$snapshotMd = SNAPSHOT_DIR.'/ghost-job-observation-'.$timestamp.'.md';

$json = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
file_put_contents(OUTPUT_JSON, $json);
file_put_contents($snapshotJson, $json);

$markdown = renderMarkdown($report);
file_put_contents(OUTPUT_MD, $markdown);
file_put_contents($snapshotMd, $markdown);

echo "=== Ghost Job Observation (READ-ONLY) ===\n";
echo 'Jobs analyzed: '.($report['dataset_summary']['total_jobs_analyzed'] ?? 0)."\n";
echo 'LOW: '.($report['risk_distribution']['low']['count'] ?? 0)."\n";
echo 'MEDIUM: '.($report['risk_distribution']['medium']['count'] ?? 0)."\n";
echo 'HIGH: '.($report['risk_distribution']['high']['count'] ?? 0)."\n";
echo 'Average score: '.($report['risk_distribution']['score_stats']['average'] ?? 0)."\n";
echo 'first_seen_at coverage: '.($report['dataset_summary']['tracking_coverage']['first_seen_at']['coverage_pct'] ?? 0)."%\n";
echo 'provider_updated_at coverage: '.($report['dataset_summary']['tracking_coverage']['provider_updated_at']['coverage_pct'] ?? 0)."%\n";
echo 'Report JSON: '.OUTPUT_JSON."\n";
echo 'Report MD: '.OUTPUT_MD."\n";
echo 'Snapshot JSON: '.$snapshotJson."\n";
echo 'Snapshot MD: '.$snapshotMd."\n";
echo "Database writes: NONE (file output only)\n";

/**
 * @return string|null
 */
function findLatestPreviousSnapshot(string $dir): ?string
{
    if (! is_dir($dir)) {
        return null;
    }

    $files = glob($dir.'/ghost-job-observation-*.json') ?: [];

    if ($files === []) {
        return null;
    }

    rsort($files);

    return $files[0];
}

/**
 * @param  array<string, mixed>  $report
 */
function renderMarkdown(array $report): string
{
    $summary = $report['dataset_summary'];
    $tracking = $summary['tracking_coverage'];
    $risk = $report['risk_distribution'];
    $stats = $risk['score_stats'];

    $lines = [];
    $lines[] = '# Ghost Job Observation Report';
    $lines[] = '';
    $lines[] = '**Observation timestamp:** '.($report['observation_timestamp'] ?? 'n/a');
    $lines[] = '**Score version:** '.($report['score_version'] ?? 'n/a');
    $lines[] = '**Mode:** READ-ONLY — no database writes';
    $lines[] = '';

    $lines[] = '## 1. Dataset Summary';
    $lines[] = '';
    $lines[] = '| Metric | Value |';
    $lines[] = '|--------|------:|';
    $lines[] = '| Total jobs analyzed | '.($summary['total_jobs_analyzed'] ?? 0).' |';
    $lines[] = '| Published scraped jobs | '.($summary['published_scraped_jobs'] ?? 0).' |';
    $lines[] = '| Active jobs | '.($summary['active_jobs'] ?? 0).' |';
    $lines[] = '| Jobs with first_seen_at | '.($tracking['first_seen_at']['with'] ?? 0).' ('.($tracking['first_seen_at']['coverage_pct'] ?? 0).'%) |';
    $lines[] = '| Jobs without first_seen_at | '.($tracking['first_seen_at']['without'] ?? 0).' |';
    $lines[] = '| Jobs with provider_updated_at | '.($tracking['provider_updated_at']['with'] ?? 0).' ('.($tracking['provider_updated_at']['coverage_pct'] ?? 0).'%) |';
    $lines[] = '| Jobs without provider_updated_at | '.($tracking['provider_updated_at']['without'] ?? 0).' |';
    $lines[] = '';

    $lines[] = '## 2. Risk Distribution';
    $lines[] = '';
    $lines[] = '| Level | Count | % |';
    $lines[] = '|-------|------:|--:|';
    foreach (['low', 'medium', 'high'] as $level) {
        $lines[] = sprintf(
            '| %s | %d | %.2f%% |',
            strtoupper($level),
            $risk[$level]['count'] ?? 0,
            $risk[$level]['percentage'] ?? 0,
        );
    }
    $lines[] = '';
    $lines[] = sprintf(
        'Average: %s | Median: %s | Min: %s | Max: %s',
        $stats['average'] ?? 0,
        $stats['median'] ?? 0,
        $stats['minimum'] ?? 0,
        $stats['maximum'] ?? 0,
    );
    $lines[] = '';

    $lines[] = '## 3. Provider Breakdown';
    $lines[] = '';
    $lines[] = '| Provider | Jobs | Avg | Median | LOW | MED | HIGH | first_seen % | provider_updated % |';
    $lines[] = '|----------|-----:|----:|-------:|----:|----:|-----:|-------------:|-------------------:|';
    foreach ($report['provider_breakdown'] as $row) {
        $lines[] = sprintf(
            '| %s | %d | %.2f | %.2f | %d | %d | %d | %.2f | %.2f |',
            $row['provider'],
            $row['total_jobs'],
            $row['average_score'],
            $row['median_score'],
            $row['low_count'],
            $row['medium_count'],
            $row['high_count'],
            $row['first_seen_at_coverage_pct'],
            $row['provider_updated_at_coverage_pct'],
        );
    }
    $lines[] = '';

    $lines[] = '## 4. Signal Contribution Analysis';
    $lines[] = '';
    foreach ($report['signal_contribution_analysis'] as $signal) {
        $lines[] = '### '.$signal['signal'];
        $lines[] = '- Jobs affected: '.$signal['jobs_affected'].' ('.$signal['percentage_affected'].'%)';
        $lines[] = '- Average contribution: '.$signal['average_contribution'];
        $lines[] = '- Total contribution: '.$signal['total_contribution'];

        if (isset($signal['reduction_breakdown'])) {
            $rb = $signal['reduction_breakdown'];
            $lines[] = '- Maintenance reduction: no='.$rb['no_reduction'].', -15='.$rb['minus_15_only'].', -25='.$rb['minus_25_total'];
        }

        $lines[] = '';
    }

    $lines[] = '## 5. Tracking Coverage by Provider';
    $lines[] = '';
    $lines[] = '| Provider | Jobs | first_seen with | first_seen % | provider_updated with | provider_updated % |';
    $lines[] = '|----------|-----:|----------------:|-------------:|----------------------:|-------------------:|';
    foreach ($report['tracking_coverage_by_provider'] as $row) {
        $lines[] = sprintf(
            '| %s | %d | %d | %.2f | %d | %.2f |',
            $row['provider'],
            $row['total_jobs'],
            $row['first_seen_at_with'],
            $row['first_seen_at_coverage_pct'],
            $row['provider_updated_at_with'],
            $row['provider_updated_at_coverage_pct'],
        );
    }
    $lines[] = '';

    $lines[] = '## 6. Missing Signal Analysis';
    $lines[] = '';
    foreach ($report['missing_signal_analysis'] as $signal) {
        $lines[] = '### '.$signal['signal'];
        $lines[] = '- Unavailable: '.$signal['unavailable']['count'].' ('.$signal['unavailable']['percentage'].'%)';

        if ($signal['unavailable']['note'] !== null) {
            $lines[] = '  - '.$signal['unavailable']['note'];
        }

        $lines[] = '- Available, score 0: '.$signal['available_zero_score']['count'].' ('.$signal['available_zero_score']['percentage'].'%)';
        $lines[] = '- Contributed: '.$signal['contributed']['count'].' ('.$signal['contributed']['percentage'].'%)';
        $lines[] = '';
    }

    $lines[] = '## 7. High Risk Job Analysis';
    $lines[] = '';
    $high = $report['high_risk_job_analysis'];
    $lines[] = 'Total HIGH-risk jobs: '.($high['total_high_risk'] ?? 0);
    $lines[] = 'Reason distribution: '.json_encode($high['reason_distribution'] ?? []);
    $lines[] = '';

    foreach ($high['jobs'] as $job) {
        $lines[] = '### #'.$job['job_id'].' — '.$job['title'];
        $lines[] = '- Company: '.$job['company'];
        $lines[] = '- Provider: '.$job['provider'];
        $lines[] = '- Score: '.$job['final_score'].' ('.$job['risk_level'].')';
        $lines[] = '- Reason: '.$job['high_risk_reason'];
        $lines[] = '- published_at: '.($job['published_at'] ?? 'NULL');
        $lines[] = '- first_seen_at: '.($job['first_seen_at'] ?? 'NULL');
        $lines[] = '- last_seen_at: '.($job['last_seen_at'] ?? 'NULL');
        $lines[] = '- provider_updated_at: '.($job['provider_updated_at'] ?? 'NULL');
        $contrib = $job['signal_contributions'];
        $lines[] = sprintf(
            '- Contributions: posting=%d persistence=%d freshness=%d maintenance=%d',
            $contrib['posting_age'],
            $contrib['persistence_age'],
            $contrib['last_seen_freshness'],
            $contrib['provider_maintenance_reduction'],
        );
        $lines[] = '';
    }

    $bias = $report['historical_data_bias_analysis'];
    $lines[] = '## 8. Historical Data Bias Analysis';
    $lines[] = '';
    $lines[] = '**Interpretation:** '.($bias['interpretation'] ?? 'n/a');
    $lines[] = '';
    $lines[] = '| Cohort | Count | Avg | Median | LOW | MED | HIGH |';
    $lines[] = '|--------|------:|----:|-------:|----:|----:|-----:|';

    foreach (['with_first_seen_at', 'without_first_seen_at'] as $key) {
        $cohort = $bias[$key];
        $lines[] = sprintf(
            '| %s | %d | %.2f | %.2f | %d | %d | %d |',
            $key,
            $cohort['count'],
            $cohort['average_score'],
            $cohort['median_score'],
            $cohort['low']['count'],
            $cohort['medium']['count'],
            $cohort['high']['count'],
        );
    }

    $lines[] = '';
    $lines[] = 'Score delta (without minus with): avg '.($bias['score_delta_without_minus_with']['average'] ?? 0).', median '.($bias['score_delta_without_minus_with']['median'] ?? 0);
    $lines[] = '';

    $maint = $report['provider_maintenance_analysis'];
    $lines[] = '## 9. Provider Maintenance Analysis';
    $lines[] = '';
    $lines[] = '- Jobs with provider_updated_at: '.($maint['jobs_with_provider_updated_at'] ?? 0);
    $lines[] = '- Jobs with maintenance reduction: '.($maint['jobs_with_maintenance_reduction'] ?? 0);
    $lines[] = '- Coverage: '.($maint['coverage_pct'] ?? 0).'%';
    $rb = $maint['reduction_breakdown'];
    $lines[] = '- Reduction breakdown: no='.$rb['no_reduction'].', -15='.$rb['minus_15_only'].', -25='.$rb['minus_25_total'];
    $lines[] = '- Assessment: '.($maint['material_effect_assessment'] ?? 'n/a');
    $lines[] = '';
    $lines[] = '| Provider | Jobs | with provider_updated_at | with reduction |';
    $lines[] = '|----------|-----:|-------------------------:|---------------:|';
    foreach ($maint['providers'] as $row) {
        $lines[] = sprintf(
            '| %s | %d | %d | %d |',
            $row['provider'],
            $row['total_jobs'],
            $row['jobs_with_provider_updated_at'],
            $row['jobs_with_maintenance_reduction'],
        );
    }
    $lines[] = '';

    $lines[] = '## 10. Calibration Observations';
    $lines[] = '';
    foreach ($report['calibration_observations'] as $obs) {
        $lines[] = '### ['.$obs['status'].'] '.$obs['observation'];
        $lines[] = '- Evidence: '.$obs['evidence'];
        $lines[] = '- Affected jobs: '.$obs['affected_job_count'];
        $lines[] = '- Affected providers: '.implode(', ', $obs['affected_providers']);
        $lines[] = '- Next: '.$obs['recommended_next_observation'];
        $lines[] = '';
    }

    $history = $report['observation_history_comparison'];
    $lines[] = '## 11. Observation History Comparison';
    $lines[] = '';

    if (($history['comparison'] ?? '') === 'unavailable') {
        $lines[] = 'comparison = unavailable';
        $lines[] = 'reason = '.($history['reason'] ?? 'first observation');
    } else {
        $lines[] = 'Previous observation: '.($history['previous_observation_at'] ?? 'n/a');
        $lines[] = '';
        $lines[] = '| Metric | Delta |';
        $lines[] = '|--------|------:|';
        foreach ($history['deltas'] as $metric => $delta) {
            $lines[] = '| '.$metric.' | '.$delta.' |';
        }
    }

    $lines[] = '';

    return implode("\n", $lines);
}

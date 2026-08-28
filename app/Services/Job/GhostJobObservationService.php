<?php

declare(strict_types=1);

namespace App\Services\Job;

use App\Models\Job;
use App\Services\Job\DTO\GhostJobScoreResult;
use Illuminate\Support\Carbon;

class GhostJobObservationService
{
    public const SIGNAL_POSTING_AGE = 'posting_age';

    public const SIGNAL_PERSISTENCE_AGE = 'persistence_age';

    public const SIGNAL_LAST_SEEN = 'last_seen_freshness';

    public const SIGNAL_PROVIDER_MAINTENANCE = 'provider_maintenance';

    public const REASON_AGE_DOMINATED = 'AGE_DOMINATED';

    public const REASON_PERSISTENCE_DOMINATED = 'PERSISTENCE_DOMINATED';

    public const REASON_FRESHNESS_DOMINATED = 'FRESHNESS_DOMINATED';

    public const REASON_MULTI_SIGNAL = 'MULTI_SIGNAL';

    public const REASON_INSUFFICIENT_TRACKING = 'INSUFFICIENT_TRACKING';

    public function __construct(
        private readonly GhostJobScoreService $scoreService,
    ) {}

    /**
     * @param  iterable<int, Job>  $jobs
     * @return array<string, mixed>
     */
    public function analyze(iterable $jobs, ?Carbon $referenceDate = null): array
    {
        $referenceDate ??= Carbon::now();
        $records = [];

        foreach ($jobs as $job) {
            $records[] = $this->observeJob($job, $referenceDate);
        }

        return $this->buildReport($records, $referenceDate);
    }

    /**
     * @return array<string, mixed>
     */
    public function observeJob(Job $job, Carbon $referenceDate): array
    {
        $scoreResult = $this->scoreService->score($job, $referenceDate);
        $availability = $this->resolveSignalAvailability($job);
        $contributions = $this->extractContributions($scoreResult, $availability);

        $record = [
            'job_id' => $job->id,
            'title' => $job->title,
            'company' => $job->source_company_name ?? 'Unknown',
            'provider' => $this->resolveProvider($job),
            'source_id' => $job->job_source_id,
            'source_name' => $job->relationLoaded('sourceProvider') && $job->sourceProvider !== null
                ? $job->sourceProvider->name
                : null,
            'published_at' => $job->published_at?->toIso8601String(),
            'first_seen_at' => $job->first_seen_at?->toIso8601String(),
            'last_seen_at' => $job->last_seen_at?->toIso8601String(),
            'provider_updated_at' => $job->provider_updated_at?->toIso8601String(),
            'last_scraped_at' => $job->last_scraped_at?->toIso8601String(),
            'scrape_status' => $job->scrape_status?->value,
            'final_score' => $scoreResult->score,
            'risk_level' => $scoreResult->riskLevel,
            'score_version' => $scoreResult->version,
            'signals' => $scoreResult->signals,
            'signal_availability' => $availability,
            'signal_contributions' => $contributions,
        ];

        if ($scoreResult->riskLevel === 'high') {
            $record['high_risk_reason'] = $this->classifyHighRiskReason($contributions, $job);
        }

        return $record;
    }

    /**
     * @param  array<string, array{impact: int, status: string}>  $contributions
     */
    public function classifyHighRiskReason(array $contributions, Job $job): string
    {
        $posting = max(0, $contributions[self::SIGNAL_POSTING_AGE]['impact']);
        $persistence = max(0, $contributions[self::SIGNAL_PERSISTENCE_AGE]['impact']);
        $freshness = max(0, $contributions[self::SIGNAL_LAST_SEEN]['impact']);

        $positive = array_filter([
            self::SIGNAL_POSTING_AGE => $posting,
            self::SIGNAL_PERSISTENCE_AGE => $persistence,
            self::SIGNAL_LAST_SEEN => $freshness,
        ], static fn (int $impact): bool => $impact > 0);

        if ($positive === []) {
            return self::REASON_INSUFFICIENT_TRACKING;
        }

        if (count($positive) === 1) {
            return match (array_key_first($positive)) {
                self::SIGNAL_POSTING_AGE => self::REASON_AGE_DOMINATED,
                self::SIGNAL_PERSISTENCE_AGE => self::REASON_PERSISTENCE_DOMINATED,
                self::SIGNAL_LAST_SEEN => self::REASON_FRESHNESS_DOMINATED,
                default => self::REASON_INSUFFICIENT_TRACKING,
            };
        }

        arsort($positive);
        $topKey = array_key_first($positive);
        $topValue = $positive[$topKey];
        $remaining = array_slice($positive, 1, preserve_keys: true);
        $secondValue = $remaining === [] ? 0 : max($remaining);

        if ($topValue >= 20 && $topValue >= ($secondValue * 2)) {
            return match ($topKey) {
                self::SIGNAL_POSTING_AGE => self::REASON_AGE_DOMINATED,
                self::SIGNAL_PERSISTENCE_AGE => self::REASON_PERSISTENCE_DOMINATED,
                self::SIGNAL_LAST_SEEN => self::REASON_FRESHNESS_DOMINATED,
                default => self::REASON_MULTI_SIGNAL,
            };
        }

        return self::REASON_MULTI_SIGNAL;
    }

    /**
     * @param  array<string, mixed>|null  $previous
     * @param  array<string, mixed>  $current
     * @return array<string, mixed>
     */
    public function compareSnapshots(?array $previous, array $current): array
    {
        if ($previous === null) {
            return [
                'comparison' => 'unavailable',
                'reason' => 'first observation',
            ];
        }

        $prevSummary = $previous['dataset_summary'] ?? [];
        $currSummary = $current['dataset_summary'] ?? [];
        $prevRisk = $previous['risk_distribution'] ?? [];
        $currRisk = $current['risk_distribution'] ?? [];
        $prevTracking = $prevSummary['tracking_coverage'] ?? [];
        $currTracking = $currSummary['tracking_coverage'] ?? [];

        return [
            'comparison' => 'available',
            'previous_observation_at' => $previous['observation_timestamp'] ?? null,
            'deltas' => [
                'total_jobs' => ($currSummary['total_jobs_analyzed'] ?? 0) - ($prevSummary['total_jobs_analyzed'] ?? 0),
                'low' => ($currRisk['low']['count'] ?? 0) - ($prevRisk['low']['count'] ?? 0),
                'medium' => ($currRisk['medium']['count'] ?? 0) - ($prevRisk['medium']['count'] ?? 0),
                'high' => ($currRisk['high']['count'] ?? 0) - ($prevRisk['high']['count'] ?? 0),
                'first_seen_at_coverage_pct' => round(
                    ($currTracking['first_seen_at']['coverage_pct'] ?? 0) - ($prevTracking['first_seen_at']['coverage_pct'] ?? 0),
                    2,
                ),
                'provider_updated_at_coverage_pct' => round(
                    ($currTracking['provider_updated_at']['coverage_pct'] ?? 0) - ($prevTracking['provider_updated_at']['coverage_pct'] ?? 0),
                    2,
                ),
                'average_score' => round(
                    ($currRisk['score_stats']['average'] ?? 0) - ($prevRisk['score_stats']['average'] ?? 0),
                    2,
                ),
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $records
     * @return array<string, mixed>
     */
    private function buildReport(array $records, Carbon $referenceDate): array
    {
        $total = count($records);

        $tracking = $this->buildTrackingCoverage($records);
        $risk = $this->buildRiskDistribution($records);
        $providers = $this->buildProviderBreakdown($records);
        $signals = $this->buildSignalAnalysis($records);
        $missing = $this->buildMissingSignalAnalysis($records);
        $highRisk = $this->buildHighRiskAnalysis($records);
        $historicalBias = $this->buildHistoricalBiasAnalysis($records);
        $maintenance = $this->buildProviderMaintenanceAnalysis($records);
        $calibration = $this->buildCalibrationObservations($records, $tracking, $risk, $historicalBias, $maintenance, $highRisk);

        return [
            'observation_timestamp' => $referenceDate->toIso8601String(),
            'score_version' => GhostJobScoreService::VERSION,
            'dataset_summary' => [
                'total_jobs_analyzed' => $total,
                'published_scraped_jobs' => $total,
                'active_jobs' => $this->countActiveJobs($records),
                'observation_timestamp' => $referenceDate->toIso8601String(),
                'tracking_coverage' => $tracking,
            ],
            'risk_distribution' => $risk,
            'provider_breakdown' => $providers,
            'signal_contribution_analysis' => $signals,
            'tracking_coverage_by_provider' => $this->buildTrackingCoverageByProvider($records),
            'missing_signal_analysis' => $missing,
            'high_risk_job_analysis' => $highRisk,
            'historical_data_bias_analysis' => $historicalBias,
            'provider_maintenance_analysis' => $maintenance,
            'calibration_observations' => $calibration,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $records
     */
    private function buildTrackingCoverage(array $records): array
    {
        $total = max(1, count($records));
        $withFirstSeen = 0;
        $withProviderUpdated = 0;

        foreach ($records as $record) {
            if ($record['first_seen_at'] !== null) {
                $withFirstSeen++;
            }

            if ($record['provider_updated_at'] !== null) {
                $withProviderUpdated++;
            }
        }

        return [
            'first_seen_at' => [
                'with' => $withFirstSeen,
                'without' => count($records) - $withFirstSeen,
                'coverage_pct' => round(($withFirstSeen / $total) * 100, 2),
            ],
            'provider_updated_at' => [
                'with' => $withProviderUpdated,
                'without' => count($records) - $withProviderUpdated,
                'coverage_pct' => round(($withProviderUpdated / $total) * 100, 2),
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $records
     */
    private function buildRiskDistribution(array $records): array
    {
        $scores = array_map(static fn (array $record): int => $record['final_score'], $records);
        $buckets = ['low' => 0, 'medium' => 0, 'high' => 0];

        foreach ($records as $record) {
            $buckets[$record['risk_level']]++;
        }

        $total = max(1, count($records));

        return [
            'low' => $this->riskBucket($buckets['low'], $total),
            'medium' => $this->riskBucket($buckets['medium'], $total),
            'high' => $this->riskBucket($buckets['high'], $total),
            'score_stats' => [
                'average' => $scores === [] ? 0 : round(array_sum($scores) / count($scores), 2),
                'median' => $this->median($scores) ?? 0,
                'minimum' => $scores === [] ? 0 : min($scores),
                'maximum' => $scores === [] ? 0 : max($scores),
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $records
     * @return list<array<string, mixed>>
     */
    private function buildProviderBreakdown(array $records): array
    {
        $groups = [];

        foreach ($records as $record) {
            $provider = $record['provider'];
            $groups[$provider][] = $record;
        }

        ksort($groups);
        $breakdown = [];

        foreach ($groups as $provider => $providerRecords) {
            $scores = array_map(static fn (array $row): int => $row['final_score'], $providerRecords);
            $total = count($providerRecords);
            $withFirstSeen = count(array_filter($providerRecords, static fn (array $row): bool => $row['first_seen_at'] !== null));
            $withProviderUpdated = count(array_filter($providerRecords, static fn (array $row): bool => $row['provider_updated_at'] !== null));
            $riskCounts = ['low' => 0, 'medium' => 0, 'high' => 0];

            foreach ($providerRecords as $row) {
                $riskCounts[$row['risk_level']]++;
            }

            $breakdown[] = [
                'provider' => $provider,
                'total_jobs' => $total,
                'average_score' => round(array_sum($scores) / max(1, count($scores)), 2),
                'median_score' => $this->median($scores) ?? 0,
                'low_count' => $riskCounts['low'],
                'medium_count' => $riskCounts['medium'],
                'high_count' => $riskCounts['high'],
                'first_seen_at_coverage_pct' => round(($withFirstSeen / max(1, $total)) * 100, 2),
                'provider_updated_at_coverage_pct' => round(($withProviderUpdated / max(1, $total)) * 100, 2),
            ];
        }

        return $breakdown;
    }

    /**
     * @param  list<array<string, mixed>>  $records
     */
    private function buildSignalAnalysis(array $records): array
    {
        $signalKeys = [
            self::SIGNAL_POSTING_AGE,
            self::SIGNAL_PERSISTENCE_AGE,
            self::SIGNAL_LAST_SEEN,
            self::SIGNAL_PROVIDER_MAINTENANCE,
        ];

        $analysis = [];
        $total = max(1, count($records));

        foreach ($signalKeys as $signalKey) {
            $affected = 0;
            $impactSum = 0;
            $impactValues = [];

            foreach ($records as $record) {
                $contribution = $record['signal_contributions'][$signalKey];
                $impact = $contribution['impact'];

                if ($signalKey === self::SIGNAL_PROVIDER_MAINTENANCE) {
                    if ($impact < 0) {
                        $affected++;
                        $impactSum += $impact;
                        $impactValues[] = $impact;
                    }

                    continue;
                }

                if ($contribution['status'] === 'contributed') {
                    $affected++;
                    $impactSum += $impact;
                    $impactValues[] = $impact;
                }
            }

            $entry = [
                'signal' => $signalKey,
                'jobs_affected' => $affected,
                'percentage_affected' => round(($affected / $total) * 100, 2),
                'average_contribution' => $impactValues === [] ? 0 : round($impactSum / count($impactValues), 2),
                'total_contribution' => $impactSum,
            ];

            if ($signalKey === self::SIGNAL_PROVIDER_MAINTENANCE) {
                $entry['reduction_breakdown'] = $this->maintenanceReductionBreakdown($records);
            }

            $analysis[] = $entry;
        }

        return $analysis;
    }

    /**
     * @param  list<array<string, mixed>>  $records
     */
    private function maintenanceReductionBreakdown(array $records): array
    {
        $minus15Only = 0;
        $minus25Total = 0;
        $noReduction = 0;

        foreach ($records as $record) {
            $impact = $record['signal_contributions'][self::SIGNAL_PROVIDER_MAINTENANCE]['impact'];

            if ($impact === -25) {
                $minus25Total++;
            } elseif ($impact === -15) {
                $minus15Only++;
            } else {
                $noReduction++;
            }
        }

        return [
            'no_reduction' => $noReduction,
            'minus_15_only' => $minus15Only,
            'minus_25_total' => $minus25Total,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $records
     */
    private function buildTrackingCoverageByProvider(array $records): array
    {
        $providers = [];

        foreach ($records as $record) {
            $provider = $record['provider'];
            $providers[$provider]['total'] = ($providers[$provider]['total'] ?? 0) + 1;

            if ($record['first_seen_at'] !== null) {
                $providers[$provider]['first_seen_at'] = ($providers[$provider]['first_seen_at'] ?? 0) + 1;
            }

            if ($record['provider_updated_at'] !== null) {
                $providers[$provider]['provider_updated_at'] = ($providers[$provider]['provider_updated_at'] ?? 0) + 1;
            }
        }

        ksort($providers);
        $result = [];

        foreach ($providers as $provider => $stats) {
            $total = max(1, $stats['total']);
            $result[] = [
                'provider' => $provider,
                'total_jobs' => $stats['total'],
                'first_seen_at_with' => $stats['first_seen_at'] ?? 0,
                'first_seen_at_without' => $stats['total'] - ($stats['first_seen_at'] ?? 0),
                'first_seen_at_coverage_pct' => round((($stats['first_seen_at'] ?? 0) / $total) * 100, 2),
                'provider_updated_at_with' => $stats['provider_updated_at'] ?? 0,
                'provider_updated_at_without' => $stats['total'] - ($stats['provider_updated_at'] ?? 0),
                'provider_updated_at_coverage_pct' => round((($stats['provider_updated_at'] ?? 0) / $total) * 100, 2),
            ];
        }

        return $result;
    }

    /**
     * @param  list<array<string, mixed>>  $records
     */
    private function buildMissingSignalAnalysis(array $records): array
    {
        $signals = [
            self::SIGNAL_POSTING_AGE => ['unavailable' => 0, 'available_zero' => 0, 'contributed' => 0],
            self::SIGNAL_PERSISTENCE_AGE => ['unavailable' => 0, 'available_zero' => 0, 'contributed' => 0],
            self::SIGNAL_LAST_SEEN => ['unavailable' => 0, 'available_zero' => 0, 'contributed' => 0],
            self::SIGNAL_PROVIDER_MAINTENANCE => ['unavailable' => 0, 'available_zero' => 0, 'contributed' => 0],
        ];

        foreach ($records as $record) {
            foreach ($record['signal_contributions'] as $signal => $contribution) {
                $signals[$signal][$contribution['status']]++;
            }
        }

        $total = max(1, count($records));
        $result = [];

        foreach ($signals as $signal => $counts) {
            $result[] = [
                'signal' => $signal,
                'unavailable' => [
                    'count' => $counts['unavailable'],
                    'percentage' => round(($counts['unavailable'] / $total) * 100, 2),
                    'note' => $signal === self::SIGNAL_PERSISTENCE_AGE
                        ? 'first_seen_at NULL — not treated as 0-day persistence'
                        : null,
                ],
                'available_zero_score' => [
                    'count' => $counts['available_zero'],
                    'percentage' => round(($counts['available_zero'] / $total) * 100, 2),
                ],
                'contributed' => [
                    'count' => $counts['contributed'],
                    'percentage' => round(($counts['contributed'] / $total) * 100, 2),
                ],
            ];
        }

        return $result;
    }

    /**
     * @param  list<array<string, mixed>>  $records
     */
    private function buildHighRiskAnalysis(array $records): array
    {
        $highRiskJobs = array_values(array_filter(
            $records,
            static fn (array $record): bool => $record['risk_level'] === 'high',
        ));

        $reasonCounts = [];

        foreach ($highRiskJobs as $job) {
            $reason = $job['high_risk_reason'] ?? self::REASON_INSUFFICIENT_TRACKING;
            $reasonCounts[$reason] = ($reasonCounts[$reason] ?? 0) + 1;
        }

        return [
            'total_high_risk' => count($highRiskJobs),
            'reason_distribution' => $reasonCounts,
            'jobs' => array_map(static function (array $record): array {
                $contributions = $record['signal_contributions'];

                return [
                    'job_id' => $record['job_id'],
                    'title' => $record['title'],
                    'company' => $record['company'],
                    'provider' => $record['provider'],
                    'published_at' => $record['published_at'],
                    'first_seen_at' => $record['first_seen_at'],
                    'last_seen_at' => $record['last_seen_at'],
                    'provider_updated_at' => $record['provider_updated_at'],
                    'final_score' => $record['final_score'],
                    'risk_level' => $record['risk_level'],
                    'high_risk_reason' => $record['high_risk_reason'] ?? self::REASON_INSUFFICIENT_TRACKING,
                    'signal_contributions' => [
                        'posting_age' => $contributions[self::SIGNAL_POSTING_AGE]['impact'],
                        'persistence_age' => $contributions[self::SIGNAL_PERSISTENCE_AGE]['impact'],
                        'last_seen_freshness' => $contributions[self::SIGNAL_LAST_SEEN]['impact'],
                        'provider_maintenance_reduction' => $contributions[self::SIGNAL_PROVIDER_MAINTENANCE]['impact'],
                    ],
                    'signal_availability' => $record['signal_availability'],
                ];
            }, $highRiskJobs),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $records
     */
    private function buildHistoricalBiasAnalysis(array $records): array
    {
        $withFirstSeen = array_values(array_filter($records, static fn (array $row): bool => $row['first_seen_at'] !== null));
        $withoutFirstSeen = array_values(array_filter($records, static fn (array $row): bool => $row['first_seen_at'] === null));

        $withStats = $this->cohortStats($withFirstSeen);
        $withoutStats = $this->cohortStats($withoutFirstSeen);

        $avgDelta = round($withoutStats['average_score'] - $withStats['average_score'], 2);
        $medianDelta = round($withoutStats['median_score'] - $withStats['median_score'], 2);

        $interpretation = $this->interpretHistoricalBias($withStats, $withoutStats, count($withoutFirstSeen), count($records));

        return [
            'with_first_seen_at' => $withStats,
            'without_first_seen_at' => $withoutStats,
            'score_delta_without_minus_with' => [
                'average' => $avgDelta,
                'median' => $medianDelta,
            ],
            'interpretation' => $interpretation,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $records
     */
    private function buildProviderMaintenanceAnalysis(array $records): array
    {
        $byProvider = [];
        $totalWithSignal = 0;
        $breakdown = $this->maintenanceReductionBreakdown($records);

        foreach ($records as $record) {
            $provider = $record['provider'];
            $byProvider[$provider]['total'] = ($byProvider[$provider]['total'] ?? 0) + 1;

            if ($record['provider_updated_at'] !== null) {
                $byProvider[$provider]['with_provider_updated_at'] = ($byProvider[$provider]['with_provider_updated_at'] ?? 0) + 1;
            }

            $impact = $record['signal_contributions'][self::SIGNAL_PROVIDER_MAINTENANCE]['impact'];

            if ($impact < 0) {
                $totalWithSignal++;
                $byProvider[$provider]['with_reduction'] = ($byProvider[$provider]['with_reduction'] ?? 0) + 1;
            }
        }

        ksort($byProvider);
        $providerRows = [];

        foreach ($byProvider as $provider => $stats) {
            $providerRows[] = [
                'provider' => $provider,
                'total_jobs' => $stats['total'],
                'jobs_with_provider_updated_at' => $stats['with_provider_updated_at'] ?? 0,
                'jobs_with_maintenance_reduction' => $stats['with_reduction'] ?? 0,
            ];
        }

        $coveragePct = count($records) === 0
            ? 0
            : round((array_sum(array_column($providerRows, 'jobs_with_provider_updated_at')) / count($records)) * 100, 2);

        return [
            'providers' => $providerRows,
            'jobs_with_provider_updated_at' => array_sum(array_column($providerRows, 'jobs_with_provider_updated_at')),
            'jobs_with_maintenance_reduction' => $totalWithSignal,
            'reduction_breakdown' => $breakdown,
            'coverage_pct' => $coveragePct,
            'material_effect_assessment' => $this->assessMaintenanceMaterialEffect($records, $breakdown, $coveragePct),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $records
     * @param  array<string, mixed>  $tracking
     * @param  array<string, mixed>  $risk
     * @param  array<string, mixed>  $historicalBias
     * @param  array<string, mixed>  $maintenance
     * @param  array<string, mixed>  $highRisk
     * @return list<array<string, mixed>>
     */
    private function buildCalibrationObservations(
        array $records,
        array $tracking,
        array $risk,
        array $historicalBias,
        array $maintenance,
        array $highRisk,
    ): array {
        $observations = [];
        $total = count($records);
        $firstSeenCoverage = $tracking['first_seen_at']['coverage_pct'];
        $providerCoverage = $tracking['provider_updated_at']['coverage_pct'];

        if ($firstSeenCoverage < 5) {
            $observations[] = [
                'status' => 'INSUFFICIENT_DATA',
                'observation' => 'Persistence-age signal coverage is too low for calibration decisions.',
                'evidence' => "Only {$tracking['first_seen_at']['with']}/{$total} jobs ({$firstSeenCoverage}%) have first_seen_at.",
                'affected_job_count' => $tracking['first_seen_at']['without'],
                'affected_providers' => array_column($this->buildTrackingCoverageByProvider($records), 'provider'),
                'recommended_next_observation' => 'Re-run after additional import cycles populate first_seen_at on newly seen jobs.',
            ];
        } else {
            $observations[] = [
                'status' => 'MONITOR',
                'observation' => 'Persistence-age coverage is emerging but still dominated by historical NULL values.',
                'evidence' => "{$tracking['first_seen_at']['with']}/{$total} jobs have first_seen_at ({$firstSeenCoverage}%).",
                'affected_job_count' => $tracking['first_seen_at']['without'],
                'affected_providers' => array_values(array_filter(
                    array_map(
                        static fn (array $row): ?string => $row['first_seen_at_coverage_pct'] < 100 ? $row['provider'] : null,
                        $this->buildTrackingCoverageByProvider($records),
                    ),
                )),
                'recommended_next_observation' => 'Track first_seen_at coverage delta across weekly snapshots.',
            ];
        }

        if ($providerCoverage < 5) {
            $observations[] = [
                'status' => 'INSUFFICIENT_DATA',
                'observation' => 'Provider maintenance reduction cannot be calibrated yet.',
                'evidence' => "Only {$tracking['provider_updated_at']['with']}/{$total} jobs ({$providerCoverage}%) have provider_updated_at.",
                'affected_job_count' => $tracking['provider_updated_at']['without'],
                'affected_providers' => array_column($maintenance['providers'], 'provider'),
                'recommended_next_observation' => 'Observe provider_updated_at coverage after provider re-imports.',
            ];
        } elseif (($maintenance['jobs_with_maintenance_reduction'] ?? 0) === 0) {
            $observations[] = [
                'status' => 'MONITOR',
                'observation' => 'provider_updated_at exists but no jobs currently qualify for maintenance reduction.',
                'evidence' => json_encode($maintenance['reduction_breakdown']),
                'affected_job_count' => $total,
                'affected_providers' => array_column($maintenance['providers'], 'provider'),
                'recommended_next_observation' => 'Wait for provider timestamps older than published_at + 7 days.',
            ];
        } else {
            $observations[] = [
                'status' => 'NO_ACTION',
                'observation' => 'Provider maintenance reduction is active on a subset of jobs.',
                'evidence' => "{$maintenance['jobs_with_maintenance_reduction']} jobs receive reduction; breakdown: ".json_encode($maintenance['reduction_breakdown']),
                'affected_job_count' => $maintenance['jobs_with_maintenance_reduction'],
                'affected_providers' => array_values(array_filter(
                    array_map(
                        static fn (array $row): ?string => ($row['jobs_with_maintenance_reduction'] ?? 0) > 0 ? $row['provider'] : null,
                        $maintenance['providers'],
                    ),
                )),
                'recommended_next_observation' => 'Compare score distribution impact in future snapshots.',
            ];
        }

        $withoutStats = $historicalBias['without_first_seen_at'];
        $withStats = $historicalBias['with_first_seen_at'];

        if ($withoutStats['count'] > 0 && $withStats['count'] === 0) {
            $observations[] = [
                'status' => 'MONITOR',
                'observation' => 'All analyzed jobs lack first_seen_at; historical bias analysis is one-sided.',
                'evidence' => $historicalBias['interpretation'],
                'affected_job_count' => $withoutStats['count'],
                'affected_providers' => array_column($this->buildProviderBreakdown($records), 'provider'),
                'recommended_next_observation' => 'Re-run bias analysis once first_seen_at cohort is non-empty.',
            ];
        } elseif (abs($historicalBias['score_delta_without_minus_with']['average']) >= 5) {
            $observations[] = [
                'status' => 'POTENTIAL_CALIBRATION',
                'observation' => 'Missing first_seen_at may distort score distribution versus tracked cohort.',
                'evidence' => $historicalBias['interpretation'],
                'affected_job_count' => $withoutStats['count'],
                'affected_providers' => array_column($this->buildProviderBreakdown($records), 'provider'),
                'recommended_next_observation' => 'Do not change formula yet; collect paired cohort data over time.',
            ];
        } else {
            $observations[] = [
                'status' => 'NO_ACTION',
                'observation' => 'No strong systematic score distortion detected between first_seen_at cohorts.',
                'evidence' => $historicalBias['interpretation'],
                'affected_job_count' => $withoutStats['count'],
                'affected_providers' => [],
                'recommended_next_observation' => 'Continue observation as first_seen_at coverage grows.',
            ];
        }

        $highCount = $risk['high']['count'] ?? 0;

        if ($highCount > 0) {
            $ageDominated = $highRisk['reason_distribution'][self::REASON_AGE_DOMINATED] ?? 0;
            $observations[] = [
                'status' => $ageDominated >= ($highCount * 0.5) ? 'MONITOR' : 'NO_ACTION',
                'observation' => 'HIGH-risk jobs classification mix indicates primary drivers.',
                'evidence' => json_encode($highRisk['reason_distribution']),
                'affected_job_count' => $highCount,
                'affected_providers' => array_values(array_unique(array_column($highRisk['jobs'], 'provider'))),
                'recommended_next_observation' => 'Review HIGH-risk list manually for false positives before any threshold change.',
            ];
        }

        return $observations;
    }

    /**
     * @return array<string, string>
     */
    private function resolveSignalAvailability(Job $job): array
    {
        return [
            self::SIGNAL_POSTING_AGE => $job->published_at !== null ? 'available' : 'unavailable',
            self::SIGNAL_PERSISTENCE_AGE => $job->first_seen_at !== null ? 'available' : 'unavailable',
            self::SIGNAL_LAST_SEEN => $job->last_seen_at !== null ? 'available' : 'unavailable',
            self::SIGNAL_PROVIDER_MAINTENANCE => ($job->provider_updated_at !== null && $job->published_at !== null)
                ? 'available'
                : 'unavailable',
        ];
    }

    /**
     * @param  array<string, string>  $availability
     * @return array<string, array{impact: int, status: string}>
     */
    private function extractContributions(GhostJobScoreResult $scoreResult, array $availability): array
    {
        $impacts = [
            self::SIGNAL_POSTING_AGE => 0,
            self::SIGNAL_PERSISTENCE_AGE => 0,
            self::SIGNAL_LAST_SEEN => 0,
            self::SIGNAL_PROVIDER_MAINTENANCE => 0,
        ];

        foreach ($scoreResult->signals as $signal) {
            $impacts[$signal['signal']] = $signal['impact'];
        }

        $contributions = [];

        foreach ($impacts as $signal => $impact) {
            if ($availability[$signal] === 'unavailable') {
                $contributions[$signal] = ['impact' => 0, 'status' => 'unavailable'];

                continue;
            }

            if ($signal === self::SIGNAL_PROVIDER_MAINTENANCE) {
                $contributions[$signal] = [
                    'impact' => $impact,
                    'status' => $impact < 0 ? 'contributed' : 'available_zero',
                ];

                continue;
            }

            $contributions[$signal] = [
                'impact' => max(0, $impact),
                'status' => $impact > 0 ? 'contributed' : 'available_zero',
            ];
        }

        return $contributions;
    }

    private function resolveProvider(Job $job): string
    {
        if ($job->relationLoaded('sourceProvider') && $job->sourceProvider !== null) {
            return (string) ($job->sourceProvider->config['provider'] ?? 'unknown');
        }

        return 'unknown';
    }

    /**
     * @param  list<array<string, mixed>>  $records
     */
    private function countActiveJobs(array $records): int
    {
        return count(array_filter($records, static function (array $record): bool {
            return $record['published_at'] !== null;
        }));
    }

    /**
     * @param  list<array<string, mixed>>  $cohort
     * @return array<string, mixed>
     */
    private function cohortStats(array $cohort): array
    {
        $scores = array_map(static fn (array $row): int => $row['final_score'], $cohort);
        $total = count($cohort);
        $risk = ['low' => 0, 'medium' => 0, 'high' => 0];

        foreach ($cohort as $row) {
            $risk[$row['risk_level']]++;
        }

        return [
            'count' => $total,
            'average_score' => $scores === [] ? 0 : round(array_sum($scores) / count($scores), 2),
            'median_score' => $this->median($scores) ?? 0,
            'low' => $this->riskBucket($risk['low'], max(1, $total)),
            'medium' => $this->riskBucket($risk['medium'], max(1, $total)),
            'high' => $this->riskBucket($risk['high'], max(1, $total)),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $withoutCohort
     * @param  list<array<string, mixed>>  $allRecords
     */
    private function interpretHistoricalBias(array $withStats, array $withoutStats, int $withoutCount, int $total): string
    {
        if ($withStats['count'] === 0 && $withoutCount > 0) {
            return 'All '.$withoutCount.' analyzed jobs lack first_seen_at. Persistence-age signal is unavailable for the entire dataset; scores rely on posting_age and last_seen_freshness only. This prevents persistence from inflating scores but also means historical jobs cannot benefit from persistence tracking yet.';
        }

        if ($withoutCount === 0) {
            return 'All analyzed jobs have first_seen_at; no NULL-first_seen_at bias present in this snapshot.';
        }

        $avgDelta = round($withoutStats['average_score'] - $withStats['average_score'], 2);
        $direction = $avgDelta > 0 ? 'higher' : ($avgDelta < 0 ? 'lower' : 'equal');

        return sprintf(
            'Jobs without first_seen_at (n=%d, %.1f%%) score %s on average than tracked jobs (n=%d) by %s points (median delta %s). Missing persistence signal means those jobs cannot accumulate persistence-age impact; observed average delta is computed from actual scores, not inferred zero-day persistence.',
            $withoutCount,
            ($withoutCount / max(1, $total)) * 100,
            $direction,
            $withStats['count'],
            abs($avgDelta),
            abs($withoutStats['median_score'] - $withStats['median_score']),
        );
    }

    /**
     * @param  list<array<string, mixed>>  $records
     * @param  array<string, int>  $breakdown
     */
    private function assessMaintenanceMaterialEffect(array $records, array $breakdown, float $coveragePct): string
    {
        if ($coveragePct < 5) {
            return 'INSUFFICIENT_DATA — provider_updated_at coverage below 5%.';
        }

        $withReduction = ($breakdown['minus_15_only'] ?? 0) + ($breakdown['minus_25_total'] ?? 0);
        $total = max(1, count($records));
        $pct = round(($withReduction / $total) * 100, 2);

        if ($withReduction === 0) {
            return 'INSUFFICIENT_DATA — timestamps present but no jobs meet maintenance reduction criteria yet.';
        }

        if ($pct < 5) {
            return "Limited material effect — {$withReduction}/{$total} jobs ({$pct}%) receive reduction.";
        }

        return "Material effect observed — {$withReduction}/{$total} jobs ({$pct}%) receive maintenance reduction.";
    }

    /**
     * @return array{count: int, percentage: float}
     */
    private function riskBucket(int $count, int $total): array
    {
        return [
            'count' => $count,
            'percentage' => round(($count / $total) * 100, 2),
        ];
    }

    /**
     * @param  list<int>  $values
     */
    private function median(array $values): ?float
    {
        if ($values === []) {
            return null;
        }

        sort($values);
        $count = count($values);
        $middle = intdiv($count, 2);

        if ($count % 2 === 0) {
            return ($values[$middle - 1] + $values[$middle]) / 2;
        }

        return (float) $values[$middle];
    }
}

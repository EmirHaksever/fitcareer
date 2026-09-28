<?php

declare(strict_types=1);

namespace Tests\Unit\Job;

use App\Models\Job;
use App\Models\JobSource;
use App\Services\Job\GhostJobObservationService;
use App\Services\Job\GhostJobScoreService;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GhostJobObservationServiceTest extends TestCase
{
    private GhostJobObservationService $observation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->observation = new GhostJobObservationService(new GhostJobScoreService);
    }

    #[Test]
    public function analyze_aggregates_risk_distribution_and_provider_totals(): void
    {
        $reference = Carbon::parse('2026-08-12 12:00:00');
        $jobs = [
            $this->makeJob([
                'id' => 1,
                'title' => 'Old Lever Job',
                'source_company_name' => 'Acme',
                'published_at' => $reference->copy()->subDays(120),
                'first_seen_at' => null,
                'last_seen_at' => $reference->copy()->subDays(3),
                'provider_updated_at' => null,
            ], 'lever'),
            $this->makeJob([
                'id' => 2,
                'title' => 'Fresh Workable Job',
                'source_company_name' => 'Beta',
                'published_at' => $reference->copy()->subDays(10),
                'first_seen_at' => $reference->copy()->subDays(5),
                'last_seen_at' => $reference->copy()->subDays(1),
                'provider_updated_at' => $reference->copy()->subDays(2),
            ], 'workable'),
        ];

        $report = $this->observation->analyze($jobs, $reference);
        $total = $report['dataset_summary']['total_jobs_analyzed'];

        $this->assertSame(2, $total);
        $this->assertSame(
            $total,
            ($report['risk_distribution']['low']['count'] ?? 0)
            + ($report['risk_distribution']['medium']['count'] ?? 0)
            + ($report['risk_distribution']['high']['count'] ?? 0),
        );

        $providerTotal = array_sum(array_column($report['provider_breakdown'], 'total_jobs'));
        $this->assertSame($total, $providerTotal);
    }

    #[Test]
    public function persistence_signal_is_unavailable_when_first_seen_at_is_null(): void
    {
        $reference = Carbon::parse('2026-08-12 12:00:00');
        $job = $this->makeJob([
            'id' => 10,
            'title' => 'Historical',
            'published_at' => $reference->copy()->subDays(200),
            'first_seen_at' => null,
            'last_seen_at' => $reference->copy()->subDays(2),
        ]);

        $record = $this->observation->observeJob($job, $reference);

        $this->assertSame('unavailable', $record['signal_availability']['persistence_age']);
        $this->assertSame('unavailable', $record['signal_contributions']['persistence_age']['status']);
        $this->assertSame(0, $record['signal_contributions']['persistence_age']['impact']);
    }

    #[Test]
    public function persistence_signal_can_be_available_with_zero_score(): void
    {
        $reference = Carbon::parse('2026-08-12 12:00:00');
        $job = $this->makeJob([
            'id' => 11,
            'title' => 'Tracked New',
            'published_at' => $reference->copy()->subDays(10),
            'first_seen_at' => $reference->copy()->subDays(5),
            'last_seen_at' => $reference->copy()->subDays(1),
        ]);

        $record = $this->observation->observeJob($job, $reference);

        $this->assertSame('available', $record['signal_availability']['persistence_age']);
        $this->assertSame('available_zero', $record['signal_contributions']['persistence_age']['status']);
        $this->assertSame(0, $record['signal_contributions']['persistence_age']['impact']);
    }

    #[Test]
    public function classifies_high_risk_age_dominated_when_only_posting_age_contributes(): void
    {
        $contributions = [
            'posting_age' => ['impact' => 50, 'status' => 'contributed'],
            'persistence_age' => ['impact' => 0, 'status' => 'unavailable'],
            'last_seen_freshness' => ['impact' => 0, 'status' => 'available_zero'],
            'provider_maintenance' => ['impact' => 0, 'status' => 'available_zero'],
        ];

        $job = $this->makeJob(['id' => 1, 'first_seen_at' => null]);

        $this->assertSame(
            GhostJobObservationService::REASON_AGE_DOMINATED,
            $this->observation->classifyHighRiskReason($contributions, $job),
        );
    }

    #[Test]
    public function classifies_high_risk_multi_signal_when_multiple_contributors_are_material(): void
    {
        $contributions = [
            'posting_age' => ['impact' => 35, 'status' => 'contributed'],
            'persistence_age' => ['impact' => 30, 'status' => 'contributed'],
            'last_seen_freshness' => ['impact' => 25, 'status' => 'contributed'],
            'provider_maintenance' => ['impact' => 0, 'status' => 'available_zero'],
        ];

        $job = $this->makeJob(['id' => 1, 'first_seen_at' => now()]);

        $this->assertSame(
            GhostJobObservationService::REASON_MULTI_SIGNAL,
            $this->observation->classifyHighRiskReason($contributions, $job),
        );
    }

    #[Test]
    public function historical_bias_analysis_separates_null_first_seen_cohort(): void
    {
        $reference = Carbon::parse('2026-08-12 12:00:00');
        $jobs = [
            $this->makeJob([
                'id' => 1,
                'published_at' => $reference->copy()->subDays(200),
                'first_seen_at' => null,
                'last_seen_at' => $reference->copy()->subDays(2),
            ]),
            $this->makeJob([
                'id' => 2,
                'published_at' => $reference->copy()->subDays(20),
                'first_seen_at' => $reference->copy()->subDays(10),
                'last_seen_at' => $reference->copy()->subDays(1),
            ]),
        ];

        $report = $this->observation->analyze($jobs, $reference);
        $bias = $report['historical_data_bias_analysis'];

        $this->assertSame(1, $bias['without_first_seen_at']['count']);
        $this->assertSame(1, $bias['with_first_seen_at']['count']);
        $this->assertStringContainsString('first_seen_at', $bias['interpretation']);
        $this->assertArrayHasKey('score_delta_without_minus_with', $bias);
    }

    #[Test]
    public function provider_maintenance_aggregation_counts_reduction_buckets(): void
    {
        $reference = Carbon::parse('2026-08-12 12:00:00');
        $jobs = [
            $this->makeJob([
                'id' => 1,
                'published_at' => $reference->copy()->subDays(60),
                'provider_updated_at' => $reference->copy()->subDays(5),
                'last_seen_at' => $reference->copy()->subDays(1),
            ]),
            $this->makeJob([
                'id' => 2,
                'published_at' => $reference->copy()->subDays(60),
                'provider_updated_at' => null,
                'last_seen_at' => $reference->copy()->subDays(1),
            ]),
        ];

        $report = $this->observation->analyze($jobs, $reference);
        $maintenance = $report['provider_maintenance_analysis'];

        $this->assertSame(1, $maintenance['jobs_with_provider_updated_at']);
        $this->assertSame(1, $maintenance['jobs_with_maintenance_reduction']);
        $this->assertSame(1, $maintenance['reduction_breakdown']['minus_25_total']);
        $this->assertSame(1, $maintenance['reduction_breakdown']['no_reduction']);
    }

    #[Test]
    public function compare_snapshots_reports_unavailable_on_first_observation(): void
    {
        $current = [
            'observation_timestamp' => '2026-08-12T12:00:00+03:00',
            'dataset_summary' => [
                'total_jobs_analyzed' => 10,
                'tracking_coverage' => [
                    'first_seen_at' => ['coverage_pct' => 5.0],
                    'provider_updated_at' => ['coverage_pct' => 2.0],
                ],
            ],
            'risk_distribution' => [
                'low' => ['count' => 6],
                'medium' => ['count' => 3],
                'high' => ['count' => 1],
                'score_stats' => ['average' => 22.5],
            ],
        ];

        $comparison = $this->observation->compareSnapshots(null, $current);

        $this->assertSame('unavailable', $comparison['comparison']);
        $this->assertSame('first observation', $comparison['reason']);
    }

    #[Test]
    public function compare_snapshots_calculates_deltas_against_previous_snapshot(): void
    {
        $previous = [
            'observation_timestamp' => '2026-08-11T12:00:00+03:00',
            'dataset_summary' => [
                'total_jobs_analyzed' => 298,
                'tracking_coverage' => [
                    'first_seen_at' => ['coverage_pct' => 0.0],
                    'provider_updated_at' => ['coverage_pct' => 1.0],
                ],
            ],
            'risk_distribution' => [
                'low' => ['count' => 200],
                'medium' => ['count' => 80],
                'high' => ['count' => 18],
                'score_stats' => ['average' => 30.0],
            ],
        ];

        $current = [
            'observation_timestamp' => '2026-08-12T12:00:00+03:00',
            'dataset_summary' => [
                'total_jobs_analyzed' => 300,
                'tracking_coverage' => [
                    'first_seen_at' => ['coverage_pct' => 2.0],
                    'provider_updated_at' => ['coverage_pct' => 3.0],
                ],
            ],
            'risk_distribution' => [
                'low' => ['count' => 201],
                'medium' => ['count' => 81],
                'high' => ['count' => 18],
                'score_stats' => ['average' => 31.5],
            ],
        ];

        $comparison = $this->observation->compareSnapshots($previous, $current);

        $this->assertSame('available', $comparison['comparison']);
        $this->assertSame(2, $comparison['deltas']['total_jobs']);
        $this->assertSame(1.5, $comparison['deltas']['average_score']);
        $this->assertSame(2.0, $comparison['deltas']['first_seen_at_coverage_pct']);
    }

    #[Test]
    public function provider_breakdown_includes_per_provider_tracking_coverage(): void
    {
        $reference = Carbon::parse('2026-08-12 12:00:00');
        $jobs = [
            $this->makeJob([
                'id' => 1,
                'published_at' => $reference->copy()->subDays(15),
                'first_seen_at' => null,
                'last_seen_at' => $reference->copy()->subDays(1),
            ], 'lever'),
            $this->makeJob([
                'id' => 2,
                'published_at' => $reference->copy()->subDays(15),
                'first_seen_at' => $reference->copy()->subDays(3),
                'last_seen_at' => $reference->copy()->subDays(1),
            ], 'workable'),
        ];

        $report = $this->observation->analyze($jobs, $reference);
        $byProvider = collect($report['provider_breakdown'])->keyBy('provider');

        $this->assertSame(0.0, $byProvider['lever']['first_seen_at_coverage_pct']);
        $this->assertSame(100.0, $byProvider['workable']['first_seen_at_coverage_pct']);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeJob(array $attributes, string $provider = 'lever'): Job
    {
        $job = new Job($attributes);
        $job->id = (int) ($attributes['id'] ?? 1);

        $source = new JobSource([
            'name' => ucfirst($provider).' Source',
            'config' => ['provider' => $provider],
        ]);
        $source->id = 100 + $job->id;
        $job->setRelation('sourceProvider', $source);

        return $job;
    }
}

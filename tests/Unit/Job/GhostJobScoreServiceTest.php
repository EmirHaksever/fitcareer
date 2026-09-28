<?php

declare(strict_types=1);

namespace Tests\Unit\Job;

use App\Models\Job;
use App\Services\Job\GhostJobScoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GhostJobScoreServiceTest extends TestCase
{
    use RefreshDatabase;

    private GhostJobScoreService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new GhostJobScoreService;
    }

    #[Test]
    public function returns_version_constant(): void
    {
        $job = $this->makeJob([
            'published_at' => Carbon::parse('2026-08-01'),
            'last_seen_at' => Carbon::parse('2026-08-10'),
        ]);

        $result = $this->service->score($job, Carbon::parse('2026-08-12'));

        $this->assertSame(GhostJobScoreService::VERSION, $result->version);
    }

    #[Test]
    public function fresh_posting_scores_low_risk(): void
    {
        $reference = Carbon::parse('2026-08-12');
        $job = $this->makeJob([
            'published_at' => $reference->copy()->subDays(10),
            'first_seen_at' => $reference->copy()->subDays(10),
            'last_seen_at' => $reference->copy()->subDays(1),
        ]);

        $result = $this->service->score($job, $reference);

        $this->assertSame('low', $result->riskLevel);
        $this->assertLessThanOrEqual(33, $result->score);
    }

    #[Test]
    public function old_posting_adds_posting_age_signal(): void
    {
        $reference = Carbon::parse('2026-08-12');
        $job = $this->makeJob([
            'published_at' => $reference->copy()->subDays(104),
            'last_seen_at' => $reference->copy()->subDays(1),
        ]);

        $result = $this->service->score($job, $reference);

        $postingSignal = collect($result->signals)->firstWhere('signal', 'posting_age');

        $this->assertNotNull($postingSignal);
        $this->assertSame(35, $postingSignal['impact']);
        $this->assertStringContainsString('104 days', $postingSignal['reason']);
    }

    #[Test]
    public function posting_age_31_to_60_days_scores_10(): void
    {
        $reference = Carbon::parse('2026-08-12');
        $job = $this->makeJob([
            'published_at' => $reference->copy()->subDays(45),
            'last_seen_at' => $reference->copy()->subDays(1),
        ]);

        $result = $this->service->score($job, $reference);
        $postingSignal = collect($result->signals)->firstWhere('signal', 'posting_age');

        $this->assertSame(10, $postingSignal['impact']);
    }

    #[Test]
    public function posting_age_61_to_90_days_scores_20(): void
    {
        $reference = Carbon::parse('2026-08-12');
        $job = $this->makeJob([
            'published_at' => $reference->copy()->subDays(75),
            'last_seen_at' => $reference->copy()->subDays(1),
        ]);

        $result = $this->service->score($job, $reference);
        $postingSignal = collect($result->signals)->firstWhere('signal', 'posting_age');

        $this->assertSame(20, $postingSignal['impact']);
    }

    #[Test]
    public function posting_age_91_to_180_days_scores_35(): void
    {
        $reference = Carbon::parse('2026-08-12');
        $job = $this->makeJob([
            'published_at' => $reference->copy()->subDays(120),
            'last_seen_at' => $reference->copy()->subDays(1),
        ]);

        $result = $this->service->score($job, $reference);
        $postingSignal = collect($result->signals)->firstWhere('signal', 'posting_age');

        $this->assertSame(35, $postingSignal['impact']);
    }

    #[Test]
    public function posting_age_over_365_days_caps_at_50(): void
    {
        $reference = Carbon::parse('2026-08-12');
        $job = $this->makeJob([
            'published_at' => $reference->copy()->subDays(500),
            'last_seen_at' => $reference->copy()->subDays(1),
        ]);

        $result = $this->service->score($job, $reference);
        $postingSignal = collect($result->signals)->firstWhere('signal', 'posting_age');

        $this->assertSame(50, $postingSignal['impact']);
    }

    #[Test]
    public function persistence_age_adds_signal_when_first_seen_available(): void
    {
        $reference = Carbon::parse('2026-08-12');
        $job = $this->makeJob([
            'published_at' => $reference->copy()->subDays(20),
            'first_seen_at' => $reference->copy()->subDays(100),
            'last_seen_at' => $reference->copy()->subDays(1),
        ]);

        $result = $this->service->score($job, $reference);
        $signal = collect($result->signals)->firstWhere('signal', 'persistence_age');

        $this->assertNotNull($signal);
        $this->assertSame(30, $signal['impact']);
    }

    #[Test]
    public function stale_last_seen_adds_freshness_signal(): void
    {
        $reference = Carbon::parse('2026-08-12');
        $job = $this->makeJob([
            'published_at' => $reference->copy()->subDays(20),
            'last_seen_at' => $reference->copy()->subDays(20),
        ]);

        $result = $this->service->score($job, $reference);
        $signal = collect($result->signals)->firstWhere('signal', 'last_seen_freshness');

        $this->assertNotNull($signal);
        $this->assertSame(25, $signal['impact']);
    }

    #[Test]
    public function last_seen_over_30_days_scores_40(): void
    {
        $reference = Carbon::parse('2026-08-12');
        $job = $this->makeJob([
            'published_at' => $reference->copy()->subDays(20),
            'last_seen_at' => $reference->copy()->subDays(45),
        ]);

        $result = $this->service->score($job, $reference);
        $signal = collect($result->signals)->firstWhere('signal', 'last_seen_freshness');

        $this->assertSame(40, $signal['impact']);
    }

    #[Test]
    public function provider_maintenance_reduces_score(): void
    {
        $reference = Carbon::parse('2026-08-12');
        $job = $this->makeJob([
            'published_at' => Carbon::parse('2026-01-01'),
            'provider_updated_at' => Carbon::parse('2026-08-01'),
            'last_seen_at' => $reference->copy()->subDays(2),
        ]);

        $result = $this->service->score($job, $reference);
        $signal = collect($result->signals)->firstWhere('signal', 'provider_maintenance');

        $this->assertNotNull($signal);
        $this->assertSame(-25, $signal['impact']);
    }

    #[Test]
    public function provider_update_within_7_days_of_publish_does_not_reduce(): void
    {
        $reference = Carbon::parse('2026-08-12');
        $job = $this->makeJob([
            'published_at' => Carbon::parse('2026-08-01'),
            'provider_updated_at' => Carbon::parse('2026-08-05'),
            'last_seen_at' => $reference->copy()->subDays(1),
        ]);

        $result = $this->service->score($job, $reference);
        $signal = collect($result->signals)->firstWhere('signal', 'provider_maintenance');

        $this->assertNull($signal);
    }

    #[Test]
    public function score_is_capped_at_100(): void
    {
        $reference = Carbon::parse('2026-08-12');
        $job = $this->makeJob([
            'published_at' => $reference->copy()->subDays(400),
            'first_seen_at' => $reference->copy()->subDays(400),
            'last_seen_at' => $reference->copy()->subDays(60),
        ]);

        $result = $this->service->score($job, $reference);

        $this->assertSame(100, $result->score);
        $this->assertSame('high', $result->riskLevel);
    }

    #[Test]
    public function medium_risk_band_is_34_to_66(): void
    {
        $reference = Carbon::parse('2026-08-12');
        $job = $this->makeJob([
            'published_at' => $reference->copy()->subDays(120),
            'last_seen_at' => $reference->copy()->subDays(10),
        ]);

        $result = $this->service->score($job, $reference);

        $this->assertSame('medium', $result->riskLevel);
        $this->assertGreaterThanOrEqual(34, $result->score);
        $this->assertLessThanOrEqual(66, $result->score);
    }

    #[Test]
    public function score_never_drops_below_zero(): void
    {
        $reference = Carbon::parse('2026-08-12');
        $job = $this->makeJob([
            'published_at' => Carbon::parse('2026-01-01'),
            'provider_updated_at' => Carbon::parse('2026-08-10'),
            'last_seen_at' => $reference->copy()->subDays(1),
        ]);

        $result = $this->service->score($job, $reference);

        $this->assertGreaterThanOrEqual(0, $result->score);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeJob(array $overrides): Job
    {
        return Job::factory()->scraped()->published()->create($overrides);
    }
}

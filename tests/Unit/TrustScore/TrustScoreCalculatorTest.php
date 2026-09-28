<?php

namespace Tests\Unit\TrustScore;

use App\Enums\TrustLabel;
use App\Models\Job;
use App\Services\TrustScore\Contracts\TrustSignalInterface;
use App\Services\TrustScore\SignalResult;
use App\Services\TrustScore\Signals\CompanyVerificationSignal;
use App\Services\TrustScore\Signals\ContactInformationSignal;
use App\Services\TrustScore\Signals\ContentCompletenessSignal;
use App\Services\TrustScore\Signals\JobFreshnessSignal;
use App\Services\TrustScore\Signals\ModerationSignal;
use App\Services\TrustScore\Signals\ReportPenaltySignal;
use App\Services\TrustScore\Signals\SalaryTransparencySignal;
use App\Services\TrustScore\Signals\SourceReliabilitySignal;
use App\Services\TrustScore\TrustScoreCalculator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TrustScoreCalculatorTest extends TestCase
{
    private TrustScoreCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculator = app(TrustScoreCalculator::class);
    }

    #[Test]
    public function weighted_calculation_uses_score_weight_and_confidence(): void
    {
        $calculator = $this->calculator->withSignals([
            new StubTrustSignal('company_verification', new SignalResult(100, 1.0)),
            new StubTrustSignal('source_reliability', new SignalResult(50, 1.0)),
        ]);

        $result = $calculator->calculate(Job::factory()->make());

        $this->assertSame(81, $result->score);
        $this->assertSame(TrustLabel::Verified, $result->label);
    }

    #[Test]
    public function returns_null_score_when_all_signals_are_unknown(): void
    {
        $calculator = $this->calculator->withSignals([
            new StubTrustSignal('company_verification', new SignalResult(null, 0.0)),
            new StubTrustSignal('source_reliability', new SignalResult(null, 0.0)),
        ]);

        $result = $calculator->calculate(Job::factory()->make());

        $this->assertNull($result->score);
        $this->assertSame(TrustLabel::Unrated, $result->label);
    }

    #[Test]
    public function label_thresholds_map_to_existing_trust_labels(): void
    {
        $this->assertSame(TrustLabel::Verified, $this->labelForScore(80));
        $this->assertSame(TrustLabel::Verified, $this->labelForScore(75));
        $this->assertSame(TrustLabel::Moderate, $this->labelForScore(60));
        $this->assertSame(TrustLabel::Moderate, $this->labelForScore(50));
        $this->assertSame(TrustLabel::Suspicious, $this->labelForScore(35));
        $this->assertSame(TrustLabel::LowTrust, $this->labelForScore(10));
    }

    #[Test]
    public function unknown_signal_scores_are_not_treated_as_zero(): void
    {
        $calculator = $this->calculator->withSignals([
            new StubTrustSignal('company_verification', new SignalResult(100, 1.0)),
            new StubTrustSignal('source_reliability', new SignalResult(null, 0.0)),
        ]);

        $result = $calculator->calculate(Job::factory()->make());

        $this->assertSame(100, $result->score);
    }

    private function labelForScore(int $score): TrustLabel
    {
        $calculator = $this->calculator->withSignals([
            new StubTrustSignal('company_verification', new SignalResult($score, 1.0)),
        ]);

        return $calculator->calculate(Job::factory()->make())->label;
    }
}

final class StubTrustSignal implements TrustSignalInterface
{
    public function __construct(
        private readonly string $key,
        private readonly SignalResult $result,
    ) {}

    public function key(): string
    {
        return $this->key;
    }

    public function evaluate(Job $job): SignalResult
    {
        return $this->result;
    }
}

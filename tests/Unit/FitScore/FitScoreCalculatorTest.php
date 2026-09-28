<?php

namespace Tests\Unit\FitScore;

use App\Models\CandidateProfile;
use App\Models\Job;
use App\Services\FitScore\Contracts\FitSignalInterface;
use App\Services\FitScore\FitScoreCalculator;
use App\Services\TrustScore\SignalResult;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FitScoreCalculatorTest extends TestCase
{
    private FitScoreCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculator = app(FitScoreCalculator::class);
    }

    #[Test]
    public function weighted_average_uses_score_weight_and_confidence(): void
    {
        $calculator = $this->calculator->withSignals([
            new StubFitSignal('required_skills', new SignalResult(100, 1.0)),
            new StubFitSignal('experience', new SignalResult(50, 1.0)),
        ]);

        $result = $calculator->calculate(
            CandidateProfile::factory()->make(),
            Job::factory()->make(),
        );

        $this->assertSame(82, $result->score);
    }

    #[Test]
    public function returns_null_score_when_all_signals_are_unknown(): void
    {
        $calculator = $this->calculator->withSignals([
            new StubFitSignal('required_skills', new SignalResult(null, 0.0)),
            new StubFitSignal('experience', new SignalResult(null, 0.0)),
        ]);

        $result = $calculator->calculate(
            CandidateProfile::factory()->make(),
            Job::factory()->make(),
        );

        $this->assertNull($result->score);
    }

    #[Test]
    public function unknown_signal_scores_are_not_treated_as_zero(): void
    {
        $calculator = $this->calculator->withSignals([
            new StubFitSignal('required_skills', new SignalResult(100, 1.0)),
            new StubFitSignal('experience', new SignalResult(null, 0.0)),
        ]);

        $result = $calculator->calculate(
            CandidateProfile::factory()->make(),
            Job::factory()->make(),
        );

        $this->assertSame(100, $result->score);
    }

    #[Test]
    public function score_is_clamped_between_configured_bounds(): void
    {
        $calculator = $this->calculator->withSignals([
            new StubFitSignal('required_skills', new SignalResult(150, 1.0)),
        ]);

        $result = $calculator->calculate(
            CandidateProfile::factory()->make(),
            Job::factory()->make(),
        );

        $this->assertSame(100, $result->score);
    }

    #[Test]
    public function result_includes_version_and_signal_breakdown(): void
    {
        $calculator = $this->calculator->withSignals([
            new StubFitSignal('required_skills', new SignalResult(80, 1.0)),
        ]);

        $result = $calculator->calculate(
            CandidateProfile::factory()->make(),
            Job::factory()->make(),
        );

        $this->assertSame('fit-v1', $result->version);
        $this->assertArrayHasKey('required_skills', $result->signals);
        $this->assertSame(80, $result->signals['required_skills']['score']);
    }
}

final class StubFitSignal implements FitSignalInterface
{
    public function __construct(
        private readonly string $key,
        private readonly SignalResult $result,
    ) {}

    public function key(): string
    {
        return $this->key;
    }

    public function evaluate(CandidateProfile $candidate, Job $job): SignalResult
    {
        return $this->result;
    }
}

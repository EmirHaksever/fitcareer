<?php

declare(strict_types=1);

namespace App\Services\TrustScore;

use App\Enums\TrustLabel;
use App\Models\Job;
use App\Services\TrustScore\Contracts\TrustSignalInterface;
use App\Services\TrustScore\Signals\CompanyVerificationSignal;
use App\Services\TrustScore\Signals\ContactInformationSignal;
use App\Services\TrustScore\Signals\ContentCompletenessSignal;
use App\Services\TrustScore\Signals\JobFreshnessSignal;
use App\Services\TrustScore\Signals\ModerationSignal;
use App\Services\TrustScore\Signals\ReportPenaltySignal;
use App\Services\TrustScore\Signals\SalaryTransparencySignal;
use App\Services\TrustScore\Signals\SourceReliabilitySignal;

class TrustScoreCalculator
{
    /** @var list<TrustSignalInterface>|null */
    private ?array $signals = null;

    public function __construct(
        private readonly CompanyVerificationSignal $companyVerificationSignal,
        private readonly SourceReliabilitySignal $sourceReliabilitySignal,
        private readonly ContactInformationSignal $contactInformationSignal,
        private readonly ContentCompletenessSignal $contentCompletenessSignal,
        private readonly JobFreshnessSignal $jobFreshnessSignal,
        private readonly ReportPenaltySignal $reportPenaltySignal,
        private readonly SalaryTransparencySignal $salaryTransparencySignal,
        private readonly ModerationSignal $moderationSignal,
    ) {}

    /**
     * @param  list<TrustSignalInterface>|null  $signals
     */
    public function withSignals(?array $signals): self
    {
        $clone = clone $this;
        $clone->signals = $signals;

        return $clone;
    }

    public function calculate(Job $job): TrustScoreResult
    {
        $weights = config('trust_score.weights');
        $weightedSum = 0.0;
        $weightTotal = 0.0;
        $breakdown = [];

        foreach ($this->resolveSignals() as $signal) {
            $key = $signal->key();
            $result = $signal->evaluate($job);
            $breakdown[$key] = $result->toArray();

            if (! $result->isUsable()) {
                continue;
            }

            $weight = (float) ($weights[$key] ?? 0);

            if ($weight <= 0) {
                continue;
            }

            $effectiveWeight = $weight * $result->confidence;
            $weightedSum += $result->score * $effectiveWeight;
            $weightTotal += $effectiveWeight;
        }

        if ($weightTotal <= 0) {
            return new TrustScoreResult(null, TrustLabel::Unrated, $breakdown);
        }

        $score = (int) round($weightedSum / $weightTotal);
        $score = max(0, min(100, $score));

        return new TrustScoreResult($score, $this->resolveLabel($score), $breakdown);
    }

    /**
     * @return list<TrustSignalInterface>
     */
    private function resolveSignals(): array
    {
        if ($this->signals !== null) {
            return $this->signals;
        }

        return [
            $this->companyVerificationSignal,
            $this->sourceReliabilitySignal,
            $this->contactInformationSignal,
            $this->contentCompletenessSignal,
            $this->jobFreshnessSignal,
            $this->reportPenaltySignal,
            $this->salaryTransparencySignal,
            $this->moderationSignal,
        ];
    }

    private function resolveLabel(int $score): TrustLabel
    {
        $thresholds = config('trust_score.labels');

        if ($score >= (int) $thresholds['verified']) {
            return TrustLabel::Verified;
        }

        if ($score >= (int) $thresholds['unrated']) {
            return TrustLabel::Unrated;
        }

        if ($score >= (int) $thresholds['suspicious']) {
            return TrustLabel::Suspicious;
        }

        return TrustLabel::LowTrust;
    }
}

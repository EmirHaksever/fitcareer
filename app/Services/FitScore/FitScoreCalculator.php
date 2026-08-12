<?php

declare(strict_types=1);

namespace App\Services\FitScore;

use App\Models\CandidateProfile;
use App\Models\Job;
use App\Services\FitScore\Contracts\FitSignalInterface;
use App\Services\FitScore\Signals\ExperienceLevelSignal;
use App\Services\FitScore\Signals\LocationSignal;
use App\Services\FitScore\Signals\PreferredSkillsSignal;
use App\Services\FitScore\Signals\RequiredSkillsSignal;
use App\Services\FitScore\Signals\SalarySignal;
use App\Services\FitScore\Signals\WorkTypeSignal;

class FitScoreCalculator
{
    /** @var list<FitSignalInterface>|null */
    private ?array $signals = null;

    public function __construct(
        private readonly RequiredSkillsSignal $requiredSkillsSignal,
        private readonly PreferredSkillsSignal $preferredSkillsSignal,
        private readonly ExperienceLevelSignal $experienceLevelSignal,
        private readonly WorkTypeSignal $workTypeSignal,
        private readonly LocationSignal $locationSignal,
        private readonly SalarySignal $salarySignal,
        private readonly FitScoreWeightResolver $fitScoreWeightResolver,
    ) {}

    /**
     * @param  list<FitSignalInterface>|null  $signals
     */
    public function withSignals(?array $signals): self
    {
        $clone = clone $this;
        $clone->signals = $signals;

        return $clone;
    }

    public function calculate(CandidateProfile $candidateProfile, Job $job): FitScoreResult
    {
        $weights = $this->fitScoreWeightResolver->resolveForJob($job)['weights'];
        $weightedSum = 0.0;
        $weightTotal = 0.0;
        $confidenceTotal = 0.0;
        $confidenceCount = 0;
        $breakdown = [];

        foreach ($this->resolveSignals() as $signal) {
            $key = $signal->key();
            $result = $signal->evaluate($candidateProfile, $job);
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
            $confidenceTotal += $result->confidence;
            $confidenceCount++;
        }

        $version = (string) config('fit_score.version');
        $overallConfidence = $confidenceCount > 0 ? $confidenceTotal / $confidenceCount : null;

        if ($weightTotal <= 0) {
            return new FitScoreResult(null, $breakdown, $version, $overallConfidence);
        }

        $min = (int) config('fit_score.score.min');
        $max = (int) config('fit_score.score.max');
        $score = (int) round($weightedSum / $weightTotal);
        $score = max($min, min($max, $score));

        return new FitScoreResult($score, $breakdown, $version, $overallConfidence);
    }

    /**
     * @return list<FitSignalInterface>
     */
    private function resolveSignals(): array
    {
        if ($this->signals !== null) {
            return $this->signals;
        }

        return [
            $this->requiredSkillsSignal,
            $this->preferredSkillsSignal,
            $this->experienceLevelSignal,
            $this->workTypeSignal,
            $this->locationSignal,
            $this->salarySignal,
        ];
    }
}

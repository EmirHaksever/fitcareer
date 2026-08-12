<?php

declare(strict_types=1);

namespace App\Services\FitScore\Signals;

use App\Enums\SkillImportance;
use App\Models\CandidateProfile;
use App\Models\Job;
use App\Services\FitScore\Concerns\EvaluatesSkillCoverage;
use App\Services\FitScore\Contracts\FitSignalInterface;
use App\Services\TrustScore\SignalResult;

final class RequiredSkillsSignal implements FitSignalInterface
{
    use EvaluatesSkillCoverage;

    public function key(): string
    {
        return 'required_skills';
    }

    public function evaluate(CandidateProfile $candidate, Job $job): SignalResult
    {
        $requiredSkills = $this->jobSkillsByImportance($job, SkillImportance::Required);

        return $this->coverageResult($requiredSkills, $candidate, 'required_count');
    }
}

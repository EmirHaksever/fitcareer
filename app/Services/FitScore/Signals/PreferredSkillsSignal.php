<?php

declare(strict_types=1);

namespace App\Services\FitScore\Signals;

use App\Enums\SkillImportance;
use App\Models\CandidateProfile;
use App\Models\Job;
use App\Services\FitScore\Concerns\EvaluatesSkillCoverage;
use App\Services\FitScore\Contracts\FitSignalInterface;
use App\Services\TrustScore\SignalResult;

final class PreferredSkillsSignal implements FitSignalInterface
{
    use EvaluatesSkillCoverage;

    public function key(): string
    {
        return 'preferred_skills';
    }

    public function evaluate(CandidateProfile $candidate, Job $job): SignalResult
    {
        $preferredSkills = $this->jobSkillsByImportance($job, SkillImportance::Preferred);

        return $this->coverageResult($preferredSkills, $candidate, 'preferred_count');
    }
}

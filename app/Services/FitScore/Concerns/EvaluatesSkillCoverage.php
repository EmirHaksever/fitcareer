<?php

declare(strict_types=1);

namespace App\Services\FitScore\Concerns;

use App\Enums\SkillImportance;
use App\Models\CandidateProfile;
use App\Models\Job;
use App\Models\Skill;
use App\Services\TrustScore\SignalResult;
use Illuminate\Support\Collection;

trait EvaluatesSkillCoverage
{
    /**
     * @return Collection<int, Skill>
     */
    protected function jobSkillsByImportance(Job $job, SkillImportance $importance): Collection
    {
        if ($job->relationLoaded('skills')) {
            return $job->skills->filter(function (Skill $skill) use ($importance): bool {
                $value = $skill->pivot->importance;

                return ($value instanceof SkillImportance ? $value : SkillImportance::from($value)) === $importance;
            })->values();
        }

        $job->loadMissing('skills');

        return $this->jobSkillsByImportance($job, $importance);
    }

    /**
     * @return list<int>
     */
    protected function candidateSkillIds(CandidateProfile $candidate): array
    {
        if ($candidate->relationLoaded('skills')) {
            return $candidate->skills->pluck('id')->all();
        }

        if ($candidate->relationLoaded('candidateSkills')) {
            return $candidate->candidateSkills->pluck('skill_id')->all();
        }

        $candidate->loadMissing('candidateSkills');

        return $candidate->candidateSkills->pluck('skill_id')->all();
    }

    /**
     * @param  Collection<int, Skill>  $requiredSkills
     */
    protected function coverageResult(Collection $requiredSkills, CandidateProfile $candidate, string $countKey): SignalResult
    {
        if ($requiredSkills->isEmpty()) {
            return new SignalResult(null, 0.0, [
                $countKey => 0,
                'reason' => 'no_skills_defined',
            ]);
        }

        $candidateSkillIds = $this->candidateSkillIds($candidate);
        $matched = $requiredSkills->filter(fn (Skill $skill): bool => in_array($skill->id, $candidateSkillIds, true));
        $missing = $requiredSkills->reject(fn (Skill $skill): bool => in_array($skill->id, $candidateSkillIds, true));

        $requiredCount = $requiredSkills->count();
        $matchedCount = $matched->count();
        $score = (int) round(($matchedCount / $requiredCount) * 100);

        return new SignalResult($score, (float) config('fit_score.fallback_confidence'), [
            $countKey => $requiredCount,
            'matched_count' => $matchedCount,
            'matched_skills' => $matched->pluck('name')->values()->all(),
            'missing_skills' => $missing->pluck('name')->values()->all(),
        ]);
    }
}

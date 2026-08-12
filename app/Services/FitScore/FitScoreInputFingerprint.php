<?php

declare(strict_types=1);

namespace App\Services\FitScore;

use App\Enums\AiAnalysisStatus;
use App\Enums\SkillImportance;
use App\Models\AiAnalysis;
use App\Models\CandidateExperience;
use App\Models\CandidateProfile;
use App\Models\CandidateSkill;
use App\Models\Job;
use App\Models\Skill;

final class FitScoreInputFingerprint
{
    public static function generate(CandidateProfile $candidate, Job $job, bool $legacy = false): string
    {
        return hash('sha256', json_encode(self::payload($candidate, $job, $legacy), JSON_THROW_ON_ERROR));
    }

    public static function isReusable(AiAnalysis $analysis, CandidateProfile $candidate, Job $job): bool
    {
        if ($analysis->status !== AiAnalysisStatus::Completed || ! $analysis->is_latest) {
            return false;
        }

        if ($analysis->analysis_version !== config('fit_score.version')) {
            return false;
        }

        $storedFingerprint = $analysis->details['input_fingerprint'] ?? null;

        if (! is_string($storedFingerprint) || $storedFingerprint === '') {
            return false;
        }

        if (hash_equals($storedFingerprint, self::generate($candidate, $job))) {
            return true;
        }

        if ($job->fit_score_weights === null) {
            return hash_equals($storedFingerprint, self::generate($candidate, $job, legacy: true));
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public static function metadata(CandidateProfile $candidate, Job $job): array
    {
        $resolution = app(FitScoreWeightResolver::class)->resolveForJob($job);

        return [
            'input_fingerprint' => self::generate($candidate, $job),
            'fit_version' => config('fit_score.version'),
            'candidate_updated_at' => $candidate->updated_at?->toIso8601String(),
            'job_updated_at' => $job->updated_at?->toIso8601String(),
            'weights' => $resolution['weights'],
            'weight_source' => $resolution['source'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function payload(CandidateProfile $candidate, Job $job, bool $legacy = false): array
    {
        return [
            'fit_version' => config('fit_score.version'),
            'candidate' => self::candidatePayload($candidate),
            'job' => self::jobPayload($job, $legacy),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function candidatePayload(CandidateProfile $candidate): array
    {
        return [
            'updated_at' => $candidate->updated_at?->toIso8601String(),
            'work_preference' => $candidate->work_preference?->value,
            'years_of_experience' => $candidate->years_of_experience,
            'city' => self::normalizeString($candidate->city),
            'country' => self::normalizeString($candidate->country),
            'desired_salary_min' => self::normalizeDecimal($candidate->desired_salary_min),
            'desired_salary_max' => self::normalizeDecimal($candidate->desired_salary_max),
            'skills' => self::candidateSkillsPayload($candidate),
            'experiences' => self::candidateExperiencesPayload($candidate),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function jobPayload(Job $job, bool $legacy = false): array
    {
        $payload = [
            'updated_at' => $job->updated_at?->toIso8601String(),
            'experience_level' => $job->experience_level?->value,
            'work_type' => $job->work_type?->value,
            'city' => self::normalizeString($job->city),
            'country' => self::normalizeString($job->country),
            'salary_min' => self::normalizeDecimal($job->salary_min),
            'salary_max' => self::normalizeDecimal($job->salary_max),
            'salary_currency' => self::normalizeString($job->salary_currency),
            'is_salary_visible' => $job->is_salary_visible,
            'skills' => self::jobSkillsPayload($job),
        ];

        if (! $legacy && $job->fit_score_weights !== null) {
            $payload['weights'] = app(FitScoreWeightResolver::class)->normalizeWeights($job->fit_score_weights);
        }

        return $payload;
    }

    /**
     * @return list<array{skill_id: int, updated_at: ?string}>
     */
    private static function candidateSkillsPayload(CandidateProfile $candidate): array
    {
        $skills = $candidate->relationLoaded('candidateSkills')
            ? $candidate->candidateSkills
            : $candidate->candidateSkills()->get();

        return $skills
            ->map(static fn (CandidateSkill $skill): array => [
                'skill_id' => $skill->skill_id,
                'proficiency_level' => $skill->proficiency_level?->value,
                'years_of_experience' => $skill->years_of_experience,
                'updated_at' => $skill->updated_at?->toIso8601String(),
            ])
            ->sortBy('skill_id')
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, updated_at: ?string}>
     */
    private static function candidateExperiencesPayload(CandidateProfile $candidate): array
    {
        $experiences = $candidate->relationLoaded('experiences')
            ? $candidate->experiences
            : $candidate->experiences()->get();

        return $experiences
            ->map(static fn (CandidateExperience $experience): array => [
                'id' => $experience->id,
                'company_name' => $experience->company_name,
                'position_title' => $experience->position_title,
                'employment_type' => $experience->employment_type?->value,
                'location' => self::normalizeString($experience->location),
                'is_current' => $experience->is_current,
                'start_date' => $experience->start_date?->toDateString(),
                'end_date' => $experience->end_date?->toDateString(),
                'updated_at' => $experience->updated_at?->toIso8601String(),
            ])
            ->sortBy('id')
            ->values()
            ->all();
    }

    /**
     * @return list<array{skill_id: int, importance: string, updated_at: ?string}>
     */
    private static function jobSkillsPayload(Job $job): array
    {
        if ($job->relationLoaded('skills')) {
            return $job->skills
                ->map(static function (Skill $skill): array {
                    $importance = $skill->pivot->importance;

                    return [
                        'skill_id' => $skill->id,
                        'importance' => $importance instanceof SkillImportance
                            ? $importance->value
                            : (string) $importance,
                        'updated_at' => $skill->pivot->updated_at?->toIso8601String(),
                    ];
                })
                ->sortBy('skill_id')
                ->values()
                ->all();
        }

        $job->loadMissing('skills');

        return self::jobSkillsPayload($job);
    }

    private static function normalizeString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim($value);

        return $normalized === '' ? null : mb_strtolower($normalized);
    }

    private static function normalizeDecimal(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return number_format((float) $value, 2, '.', '');
    }
}

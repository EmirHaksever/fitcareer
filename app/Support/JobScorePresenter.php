<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\AiAnalysisStatus;
use App\Enums\AiAnalysisType;
use App\Enums\TrustAnalysisStatus;
use App\Models\AiAnalysis;
use App\Models\Job;

class JobScorePresenter
{
    /**
     * @return array{trust_score: ?int, trust_label: string, trust_analysis_status: string}
     */
    public static function trustFields(Job $job): array
    {
        $status = $job->trust_analysis_status;

        return [
            'trust_score' => in_array($status, [
                TrustAnalysisStatus::Pending,
                TrustAnalysisStatus::Analyzing,
            ], true) ? null : $job->trust_score,
            'trust_label' => $job->trust_label->value,
            'trust_analysis_status' => $status->value,
        ];
    }

    /**
     * @return array{fit_score: ?int, fit_analysis_status: ?string}
     */
    public static function fitFields(Job $job, ?int $candidateProfileId): array
    {
        if ($candidateProfileId === null) {
            return [
                'fit_score' => null,
                'fit_analysis_status' => null,
            ];
        }

        $analysis = self::resolveFitAnalysis($job, $candidateProfileId);

        if ($analysis === null) {
            return [
                'fit_score' => null,
                'fit_analysis_status' => null,
            ];
        }

        return [
            'fit_score' => $analysis->status === AiAnalysisStatus::Completed ? $analysis->score : null,
            'fit_analysis_status' => $analysis->status->value,
        ];
    }

    /**
     * @return array{fit_details: ?array<string, mixed>}
     */
    public static function fitDetailsFields(Job $job, ?int $candidateProfileId): array
    {
        if ($candidateProfileId === null) {
            return ['fit_details' => null];
        }

        $analysis = self::resolveFitAnalysis($job, $candidateProfileId);

        if ($analysis === null || $analysis->status !== AiAnalysisStatus::Completed) {
            return ['fit_details' => null];
        }

        return [
            'fit_details' => self::formatFitDetails($analysis->details ?? []),
        ];
    }

    /**
     * Company application Match/Fit presentation from the existing snapshot + latest analysis.
     *
     * @return array{match_analysis_status: ?string, match_details: ?array<string, mixed>}
     */
    public static function companyMatchFields(
        Job $job,
        int $candidateProfileId,
        ?int $snapshotScore,
        bool $includeDetails = false,
    ): array {
        $analysis = self::resolveFitAnalysis($job, $candidateProfileId);
        $status = $analysis?->status?->value;

        if ($snapshotScore !== null && $status === null) {
            $status = AiAnalysisStatus::Completed->value;
        }

        $details = null;
        if ($includeDetails && $analysis !== null && $analysis->status === AiAnalysisStatus::Completed) {
            $details = self::formatFitDetails($analysis->details ?? []);
        }

        return [
            'match_analysis_status' => $status,
            'match_details' => $details,
        ];
    }

    /**
     * @param  array<string, mixed>  $details
     * @return array<string, mixed>|null
     */
    private static function formatFitDetails(array $details): ?array
    {
        $signals = $details['signals'] ?? null;

        if (! is_array($signals) || $signals === []) {
            return null;
        }

        $formatted = [
            'signals' => $signals,
            'confidence' => $details['confidence'] ?? null,
        ];

        foreach (['input_fingerprint', 'fit_version', 'candidate_updated_at', 'job_updated_at', 'weights', 'weight_source'] as $key) {
            if (array_key_exists($key, $details)) {
                $formatted[$key] = $details[$key];
            }
        }

        return $formatted;
    }

    public static function resolveFitAnalysis(Job $job, int $candidateProfileId): ?AiAnalysis
    {
        if ($job->relationLoaded('analyses')) {
            return $job->analyses
                ->first(fn (AiAnalysis $analysis): bool => $analysis->type === AiAnalysisType::CvJobFit
                    && $analysis->candidate_profile_id === $candidateProfileId
                    && $analysis->is_latest);
        }

        return $job->analyses()
            ->where('type', AiAnalysisType::CvJobFit)
            ->where('candidate_profile_id', $candidateProfileId)
            ->where('is_latest', true)
            ->first();
    }
}

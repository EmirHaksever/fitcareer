<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Enums\AiAnalysisStatus;
use App\Enums\AiAnalysisType;
use App\Events\CvJobFitAnalysisCompleted;
use App\Events\CvJobFitAnalysisFailed;
use App\Models\AiAnalysis;
use App\Models\CandidateProfile;
use App\Models\Job;
use App\Services\FitScore\FitScoreCalculator;
use App\Services\FitScore\FitScoreInputFingerprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CvJobFitAnalysisService
{
    public function __construct(
        private readonly FitScoreCalculator $fitScoreCalculator,
    ) {}

    public function analyze(CandidateProfile $candidateProfile, Job $job): AiAnalysis
    {
        $this->loadScoringRelations($candidateProfile, $job);

        $existing = AiAnalysis::query()
            ->where('job_id', $job->id)
            ->where('candidate_profile_id', $candidateProfile->id)
            ->where('type', AiAnalysisType::CvJobFit)
            ->where('is_latest', true)
            ->first();

        if ($existing !== null && FitScoreInputFingerprint::isReusable($existing, $candidateProfile, $job)) {
            return $existing;
        }

        try {
            $result = $this->fitScoreCalculator->calculate($candidateProfile, $job);
            $metadata = FitScoreInputFingerprint::metadata($candidateProfile, $job);
            $aiMetadata = $this->resolveCvExtractionMetadata($candidateProfile);

            return DB::transaction(function () use ($candidateProfile, $job, $result, $metadata, $aiMetadata): AiAnalysis {
                AiAnalysis::query()
                    ->where('job_id', $job->id)
                    ->where('candidate_profile_id', $candidateProfile->id)
                    ->where('type', AiAnalysisType::CvJobFit)
                    ->where('is_latest', true)
                    ->lockForUpdate()
                    ->update(['is_latest' => false]);

                $analysis = AiAnalysis::query()->create([
                    'type' => AiAnalysisType::CvJobFit,
                    'job_id' => $job->id,
                    'candidate_profile_id' => $candidateProfile->id,
                    'score' => $result->score,
                    'label' => null,
                    'summary' => null,
                    'details' => [
                        'signals' => $result->signals,
                        'confidence' => $result->confidence,
                        ...$metadata,
                    ],
                    'ai_model' => $aiMetadata['ai_model'],
                    'analysis_version' => $result->version,
                    'prompt_version' => $aiMetadata['prompt_version'],
                    'raw_response' => $aiMetadata['raw_response'],
                    'status' => AiAnalysisStatus::Completed,
                    'is_latest' => true,
                    'analyzed_at' => now(),
                ]);

                CvJobFitAnalysisCompleted::dispatch($candidateProfile, $job, $analysis);

                return $analysis;
            });
        } catch (\Throwable $exception) {
            Log::error('CV job fit analysis failed.', [
                'candidate_profile_id' => $candidateProfile->id,
                'job_id' => $job->id,
                'message' => $exception->getMessage(),
            ]);

            CvJobFitAnalysisFailed::dispatch($candidateProfile, $job);

            throw $exception;
        }
    }

    private function loadScoringRelations(CandidateProfile $candidateProfile, Job $job): void
    {
        $candidateProfile->loadMissing(['candidateSkills', 'skills', 'experiences']);
        $job->loadMissing(['skills']);
    }

    /**
     * @return array{ai_model: ?string, prompt_version: ?string, raw_response: ?array<string, mixed>}
     */
    private function resolveCvExtractionMetadata(CandidateProfile $candidateProfile): array
    {
        $extraction = $candidateProfile->cv_parsed_data['ai_extraction'] ?? null;

        if (! is_array($extraction) || ($extraction['status'] ?? null) !== 'completed') {
            return [
                'ai_model' => null,
                'prompt_version' => null,
                'raw_response' => null,
            ];
        }

        return [
            'ai_model' => is_string($extraction['model'] ?? null) ? $extraction['model'] : null,
            'prompt_version' => is_string($extraction['prompt_version'] ?? null) ? $extraction['prompt_version'] : null,
            'raw_response' => is_array($extraction['raw_response'] ?? null) ? $extraction['raw_response'] : null,
        ];
    }
}

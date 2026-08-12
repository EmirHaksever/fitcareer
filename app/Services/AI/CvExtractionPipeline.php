<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Models\CandidateProfile;
use App\Services\AI\DTO\CvExtractionResult;
use Illuminate\Support\Facades\Log;

class CvExtractionPipeline
{
    public function __construct(
        private readonly GeminiClient $geminiClient,
        private readonly CvExtractionService $cvExtractionService,
        private readonly CvExtractionApplicator $cvExtractionApplicator,
    ) {}

    public function isEnabled(): bool
    {
        return (bool) config('ai.cv_extraction.enabled')
            && config('ai.provider') === 'gemini'
            && $this->geminiClient->isConfigured();
    }

    /**
     * @return array<string, mixed>
     */
    public function run(CandidateProfile $profile, string $cvText): array
    {
        $extraction = $this->cvExtractionService->extractFromText($cvText);
        $applySummary = $this->cvExtractionApplicator->apply($profile, $extraction);

        return $this->buildMetadata($extraction, $applySummary, 'completed');
    }

    /**
     * @return array<string, mixed>
     */
    public function buildMetadata(
        CvExtractionResult $extraction,
        array $applySummary,
        string $status,
    ): array {
        return [
            'status' => $status,
            'prompt_version' => $extraction->promptVersion,
            'model' => $extraction->model,
            'extracted_at' => now()->toIso8601String(),
            'structured' => $extraction->toArray(),
            'apply_summary' => $applySummary,
            'raw_response' => [
                'structured' => $extraction->toArray(),
                'provider' => $this->sanitizeRawResponse($extraction->rawResponse),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildFailureMetadata(\Throwable $exception): array
    {
        Log::warning('CV AI extraction failed.', [
            'provider' => config('ai.provider'),
            'model' => config('ai.gemini.model'),
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
        ]);

        return [
            'status' => 'failed',
            'prompt_version' => config('ai.cv_extraction.prompt_version'),
            'model' => config('ai.gemini.model'),
            'extracted_at' => now()->toIso8601String(),
            'error' => $exception::class,
            'message' => $exception->getMessage(),
        ];
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private function sanitizeRawResponse(array $raw): array
    {
        $usage = $raw['usageMetadata'] ?? null;

        return [
            'response_id' => $raw['responseId'] ?? null,
            'model_version' => $raw['modelVersion'] ?? null,
            'usage_metadata' => is_array($usage) ? $usage : null,
            'candidate_count' => is_array($raw['candidates'] ?? null) ? count($raw['candidates']) : 0,
        ];
    }
}

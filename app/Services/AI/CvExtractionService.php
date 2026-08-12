<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Services\AI\DTO\CvExtractionResult;

class CvExtractionService
{
    public function __construct(
        private readonly GeminiClient $geminiClient,
    ) {}

    public function extractFromText(string $cvText): CvExtractionResult
    {
        $maxChars = (int) config('ai.cv_extraction.max_cv_chars');
        $normalizedText = trim($cvText);

        if ($normalizedText === '') {
            throw new \InvalidArgumentException('CV text is empty.');
        }

        if (mb_strlen($normalizedText) > $maxChars) {
            $normalizedText = mb_substr($normalizedText, 0, $maxChars);
        }

        $prompt = $this->buildPrompt($normalizedText);
        $promptVersion = (string) config('ai.cv_extraction.prompt_version');
        $model = (string) config('ai.gemini.model');

        $response = $this->geminiClient->generateStructured($prompt, [
            'responseMimeType' => 'application/json',
            'responseSchema' => GeminiClient::cvExtractionResponseSchema(),
        ]);

        return CvExtractionResult::fromPayload(
            payload: $response['parsed'],
            model: $model,
            promptVersion: $promptVersion,
            rawResponse: $response['raw'],
        );
    }

    private function buildPrompt(string $cvText): string
    {
        return <<<PROMPT
Extract structured candidate profile data from the CV text below.

Rules:
- Return only factual information present in the CV.
- skills: technical and professional skills explicitly mentioned.
- experience: work history entries with title, company, and years when available.
- total_experience_years: best estimate of total professional experience in full years.
- location: primary location if stated (city/country).
- work_preferences: remote/hybrid/onsite preferences if stated.
- education: degrees or institutions if stated.
- Use empty arrays when a section is not present.

CV TEXT:
{$cvText}
PROMPT;
    }
}

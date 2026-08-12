<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Exceptions\AiConfigurationMissingException;
use App\Exceptions\AiProviderUnavailableException;
use App\Exceptions\AiStructuredOutputInvalidException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiClient
{
    /**
     * @param  array<string, mixed>  $generationConfig
     * @return array{parsed: array<string, mixed>, raw: array<string, mixed>, latency_ms: int}
     */
    public function generateStructured(string $prompt, array $generationConfig = []): array
    {
        $this->assertConfigured();

        $model = (string) config('ai.gemini.model');
        $baseUrl = rtrim((string) config('ai.gemini.base_url'), '/');
        $timeout = (int) config('ai.gemini.timeout');
        $apiKey = (string) config('ai.gemini.api_key');

        $url = $baseUrl.'/models/'.$model.':generateContent';

        $payload = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $prompt],
                    ],
                ],
            ],
            'generationConfig' => array_merge([
                'temperature' => 0.1,
            ], $generationConfig),
        ];

        $startedAt = microtime(true);

        try {
            $response = Http::timeout($timeout)
                ->withQueryParameters(['key' => $apiKey])
                ->retry(2, 500, function (\Throwable $exception): bool {
                    return $exception instanceof ConnectionException;
                }, throw: false)
                ->acceptJson()
                ->post($url, $payload);
        } catch (ConnectionException $exception) {
            Log::warning('Gemini request transport failure.', [
                'provider' => 'gemini',
                'model' => $model,
                'message' => $exception->getMessage(),
            ]);

            throw AiProviderUnavailableException::transport($exception->getMessage());
        }

        $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);
        $raw = $response->json();

        if (! is_array($raw)) {
            $raw = ['body' => $response->body()];
        }

        if ($response->failed()) {
            Log::warning('Gemini request failed.', [
                'provider' => 'gemini',
                'model' => $model,
                'status' => $response->status(),
                'latency_ms' => $latencyMs,
            ]);

            if (in_array($response->status(), [401, 403], true)) {
                throw AiConfigurationMissingException::missingApiKey();
            }

            $detail = is_array($raw) ? (string) ($raw['error']['message'] ?? '') : '';

            throw AiProviderUnavailableException::fromHttpStatus($response->status(), $detail);
        }

        $text = $this->extractResponseText($raw);

        if ($text === null || trim($text) === '') {
            throw AiStructuredOutputInvalidException::missingContent();
        }

        $parsed = json_decode($text, true);

        if (! is_array($parsed)) {
            throw AiStructuredOutputInvalidException::invalidJson(json_last_error_msg());
        }

        Log::info('Gemini structured request succeeded.', [
            'provider' => 'gemini',
            'model' => $model,
            'latency_ms' => $latencyMs,
            'parse_status' => 'success',
        ]);

        return [
            'parsed' => $parsed,
            'raw' => $raw,
            'latency_ms' => $latencyMs,
        ];
    }

    public function isConfigured(): bool
    {
        $apiKey = config('ai.gemini.api_key');
        $model = config('ai.gemini.model');

        return is_string($apiKey) && $apiKey !== '' && is_string($model) && $model !== '';
    }

    /**
     * @return array<string, mixed>
     */
    public static function cvExtractionResponseSchema(): array
    {
        return [
            'type' => 'OBJECT',
            'properties' => [
                'skills' => [
                    'type' => 'ARRAY',
                    'items' => [
                        'type' => 'OBJECT',
                        'properties' => [
                            'name' => ['type' => 'STRING'],
                            'confidence' => ['type' => 'NUMBER'],
                        ],
                        'required' => ['name'],
                    ],
                ],
                'experience' => [
                    'type' => 'ARRAY',
                    'items' => [
                        'type' => 'OBJECT',
                        'properties' => [
                            'title' => ['type' => 'STRING'],
                            'company' => ['type' => 'STRING'],
                            'years' => ['type' => 'INTEGER'],
                            'confidence' => ['type' => 'NUMBER'],
                        ],
                        'required' => ['title'],
                    ],
                ],
                'total_experience_years' => ['type' => 'INTEGER'],
                'location' => ['type' => 'STRING'],
                'work_preferences' => [
                    'type' => 'ARRAY',
                    'items' => ['type' => 'STRING'],
                ],
                'education' => [
                    'type' => 'ARRAY',
                    'items' => ['type' => 'STRING'],
                ],
            ],
            'required' => ['skills', 'experience'],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function generate(array $payload): array
    {
        throw new \LogicException('Use generateStructured() for Gemini requests.');
    }

    private function assertConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw AiConfigurationMissingException::missingApiKey();
        }
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function extractResponseText(array $raw): ?string
    {
        $candidates = $raw['candidates'] ?? null;

        if (! is_array($candidates) || $candidates === []) {
            return null;
        }

        $parts = $candidates[0]['content']['parts'] ?? null;

        if (! is_array($parts)) {
            return null;
        }

        foreach ($parts as $part) {
            if (is_array($part) && isset($part['text']) && is_string($part['text'])) {
                return $part['text'];
            }
        }

        return null;
    }
}

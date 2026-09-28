<?php

declare(strict_types=1);

namespace Tests\Unit\AI;

use App\Exceptions\AiConfigurationMissingException;
use App\Exceptions\AiStructuredOutputInvalidException;
use App\Services\AI\GeminiClient;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GeminiClientTest extends TestCase
{
    #[Test]
    public function it_throws_when_api_key_is_missing(): void
    {
        config([
            'ai.gemini.api_key' => '',
            'ai.gemini.model' => 'gemini-2.0-flash',
        ]);

        $this->expectException(AiConfigurationMissingException::class);

        app(GeminiClient::class)->generateStructured('test prompt', [
            'responseMimeType' => 'application/json',
        ]);
    }

    #[Test]
    public function it_parses_structured_json_from_gemini_response(): void
    {
        config([
            'ai.gemini.api_key' => 'test-key',
            'ai.gemini.model' => 'gemini-2.0-flash',
            'ai.gemini.base_url' => 'https://generativelanguage.googleapis.com/v1beta',
            'ai.gemini.timeout' => 5,
        ]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => json_encode([
                                    'skills' => [['name' => 'Flutter', 'confidence' => 0.95]],
                                    'experience' => [['title' => 'Developer', 'company' => 'Acme', 'years' => 2]],
                                    'total_experience_years' => 2,
                                    'location' => 'Istanbul, Turkey',
                                    'work_preferences' => ['remote'],
                                    'education' => [],
                                ], JSON_THROW_ON_ERROR)],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $result = app(GeminiClient::class)->generateStructured('extract cv', [
            'responseMimeType' => 'application/json',
            'responseSchema' => GeminiClient::cvExtractionResponseSchema(),
        ]);

        $this->assertSame('Flutter', $result['parsed']['skills'][0]['name']);
        $this->assertSame(2, $result['parsed']['total_experience_years']);
    }

    #[Test]
    public function it_fails_when_response_content_is_not_json(): void
    {
        config([
            'ai.gemini.api_key' => 'test-key',
            'ai.gemini.model' => 'gemini-2.0-flash',
        ]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => 'not-json'],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $this->expectException(AiStructuredOutputInvalidException::class);

        app(GeminiClient::class)->generateStructured('extract cv');
    }
}

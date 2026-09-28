<?php

declare(strict_types=1);

namespace Tests\Unit\AI;

use App\Exceptions\AiStructuredOutputInvalidException;
use App\Services\AI\DTO\CvExtractionResult;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CvExtractionResultTest extends TestCase
{
    #[Test]
    public function it_builds_a_typed_result_from_payload(): void
    {
        $result = CvExtractionResult::fromPayload(
            payload: [
                'skills' => [
                    ['name' => 'Dart', 'confidence' => 0.9],
                    ['name' => ''],
                ],
                'experience' => [
                    ['title' => 'Flutter Developer', 'company' => 'Mobile Labs', 'years' => 4],
                ],
                'total_experience_years' => 8,
                'location' => 'Istanbul, Turkey',
                'work_preferences' => ['remote'],
                'education' => ['BSc Computer Engineering'],
            ],
            model: 'gemini-2.0-flash',
            promptVersion: 'cv-extract-v1',
            rawResponse: ['candidates' => []],
        );

        $this->assertSame(['Dart'], $result->skillNames());
        $this->assertSame(8, $result->totalExperienceYears);
        $this->assertSame('Istanbul, Turkey', $result->location);
    }

    #[Test]
    public function it_rejects_invalid_payload_shape(): void
    {
        $this->expectException(AiStructuredOutputInvalidException::class);

        CvExtractionResult::fromPayload(
            payload: ['experience' => []],
            model: 'gemini-2.0-flash',
            promptVersion: 'cv-extract-v1',
            rawResponse: [],
        );
    }
}

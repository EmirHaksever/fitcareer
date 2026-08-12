<?php

declare(strict_types=1);

namespace App\Services\AI\DTO;

use App\Exceptions\AiStructuredOutputInvalidException;

final readonly class CvExtractionResult
{
    /**
     * @param  list<CvExtractedSkill>  $skills
     * @param  list<CvExtractedExperience>  $experience
     * @param  list<string>  $workPreferences
     * @param  list<string>  $education
     * @param  array<string, mixed>  $rawResponse
     */
    public function __construct(
        public array $skills,
        public array $experience,
        public ?int $totalExperienceYears,
        public ?string $location,
        public array $workPreferences,
        public array $education,
        public string $model,
        public string $promptVersion,
        public array $rawResponse,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $rawResponse
     */
    public static function fromPayload(
        array $payload,
        string $model,
        string $promptVersion,
        array $rawResponse,
    ): self {
        if (! array_key_exists('skills', $payload) || ! is_array($payload['skills'])) {
            throw AiStructuredOutputInvalidException::schemaViolation('skills must be an array.');
        }

        if (! array_key_exists('experience', $payload) || ! is_array($payload['experience'])) {
            throw AiStructuredOutputInvalidException::schemaViolation('experience must be an array.');
        }

        $skills = [];
        foreach ($payload['skills'] as $index => $item) {
            if (! is_array($item)) {
                throw AiStructuredOutputInvalidException::schemaViolation('skills['.$index.'] must be an object.');
            }

            try {
                $skills[] = CvExtractedSkill::fromArray($item);
            } catch (\InvalidArgumentException $exception) {
                continue;
            }
        }

        $experience = [];
        foreach ($payload['experience'] as $index => $item) {
            if (! is_array($item)) {
                throw AiStructuredOutputInvalidException::schemaViolation('experience['.$index.'] must be an object.');
            }

            try {
                $experience[] = CvExtractedExperience::fromArray($item);
            } catch (\InvalidArgumentException $exception) {
                continue;
            }
        }

        $totalYears = isset($payload['total_experience_years'])
            ? max(0, (int) $payload['total_experience_years'])
            : null;

        $location = isset($payload['location']) ? trim((string) $payload['location']) : null;
        if ($location === '') {
            $location = null;
        }

        return new self(
            skills: $skills,
            experience: $experience,
            totalExperienceYears: $totalYears,
            location: $location,
            workPreferences: self::normalizeStringList($payload['work_preferences'] ?? []),
            education: self::normalizeStringList($payload['education'] ?? []),
            model: $model,
            promptVersion: $promptVersion,
            rawResponse: $rawResponse,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'skills' => array_map(static fn (CvExtractedSkill $skill): array => $skill->toArray(), $this->skills),
            'experience' => array_map(static fn (CvExtractedExperience $item): array => $item->toArray(), $this->experience),
            'total_experience_years' => $this->totalExperienceYears,
            'location' => $this->location,
            'work_preferences' => $this->workPreferences,
            'education' => $this->education,
            'model' => $this->model,
            'prompt_version' => $this->promptVersion,
        ];
    }

    /**
     * @return list<string>
     */
    public function skillNames(): array
    {
        return array_values(array_unique(array_map(
            static fn (CvExtractedSkill $skill): string => $skill->name,
            $this->skills,
        )));
    }

    /**
     * @return list<string>
     */
    private static function normalizeStringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $items = [];

        foreach ($value as $item) {
            if (! is_string($item)) {
                continue;
            }

            $normalized = trim($item);

            if ($normalized !== '') {
                $items[] = $normalized;
            }
        }

        return array_values(array_unique($items));
    }
}

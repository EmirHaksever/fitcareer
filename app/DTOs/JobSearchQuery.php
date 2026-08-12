<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\EmploymentType;
use App\Enums\ExperienceLevel;
use App\Enums\WorkType;

/**
 * Engine-agnostic job search parameters.
 *
 * candidateProfileId is never accepted from client input; it must be derived
 * server-side from the authenticated candidate context.
 */
readonly class JobSearchQuery
{
    public function __construct(
        public ?string $keyword = null,
        public ?string $location = null,
        public ?string $category = null,
        public ?EmploymentType $employmentType = null,
        public ?WorkType $workType = null,
        public ?ExperienceLevel $experienceLevel = null,
        public ?float $minSalary = null,
        public ?float $maxSalary = null,
        public ?int $minTrustScore = null,
        public ?int $minFitScore = null,
        public ?int $candidateProfileId = null,
        public ?string $sort = null,
        public int $page = 1,
        public int $perPage = 15,
    ) {}

    /**
     * @param  array<string, mixed>  $input  Validated client input (snake_case keys).
     */
    public static function fromValidatedInput(array $input): self
    {
        return new self(
            keyword: self::nullableString($input, 'keyword'),
            location: self::nullableString($input, 'location'),
            category: self::nullableString($input, 'category'),
            employmentType: self::nullableEnum($input, 'employment_type', EmploymentType::class),
            workType: self::nullableEnum($input, 'work_type', WorkType::class),
            experienceLevel: self::nullableEnum($input, 'experience_level', ExperienceLevel::class),
            minSalary: self::nullableFloat($input, 'min_salary'),
            maxSalary: self::nullableFloat($input, 'max_salary'),
            minTrustScore: self::nullableInt($input, 'min_trust_score'),
            minFitScore: self::nullableInt($input, 'min_fit_score'),
            sort: self::nullableString($input, 'sort'),
            page: max(1, (int) ($input['page'] ?? 1)),
            perPage: max(1, min(100, (int) ($input['per_page'] ?? 15))),
        );
    }

    public function withCandidateProfileId(?int $candidateProfileId): self
    {
        return new self(
            keyword: $this->keyword,
            location: $this->location,
            category: $this->category,
            employmentType: $this->employmentType,
            workType: $this->workType,
            experienceLevel: $this->experienceLevel,
            minSalary: $this->minSalary,
            maxSalary: $this->maxSalary,
            minTrustScore: $this->minTrustScore,
            minFitScore: $this->minFitScore,
            candidateProfileId: $candidateProfileId,
            sort: $this->sort,
            page: $this->page,
            perPage: $this->perPage,
        );
    }

    /**
     * @param  class-string<\BackedEnum>  $enumClass
     */
    private static function nullableEnum(array $input, string $key, string $enumClass): ?\BackedEnum
    {
        if (! array_key_exists($key, $input) || $input[$key] === null || $input[$key] === '') {
            return null;
        }

        return $enumClass::from($input[$key]);
    }

    private static function nullableString(array $input, string $key): ?string
    {
        if (! array_key_exists($key, $input) || $input[$key] === null) {
            return null;
        }

        $value = trim((string) $input[$key]);

        return $value === '' ? null : $value;
    }

    private static function nullableInt(array $input, string $key): ?int
    {
        if (! array_key_exists($key, $input) || $input[$key] === null || $input[$key] === '') {
            return null;
        }

        return (int) $input[$key];
    }

    private static function nullableFloat(array $input, string $key): ?float
    {
        if (! array_key_exists($key, $input) || $input[$key] === null || $input[$key] === '') {
            return null;
        }

        return (float) $input[$key];
    }
}

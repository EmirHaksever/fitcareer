<?php

declare(strict_types=1);

namespace App\Services\Scraper\DTO;

use App\Enums\WorkType;

final readonly class LocationInput
{
    /**
     * @param  list<string>  $rawLocationStrings
     */
    public function __construct(
        public ?string $city = null,
        public ?string $country = null,
        public ?WorkType $workType = null,
        public array $rawLocationStrings = [],
    ) {}

    /**
     * @param  list<string>  $rawLocationStrings
     */
    public static function fromSignals(
        ?string $city,
        ?string $country,
        ?WorkType $workType,
        array $rawLocationStrings = [],
    ): self {
        return new self(
            city: self::trimOrNull($city),
            country: self::trimOrNull($country),
            workType: $workType,
            rawLocationStrings: array_values(array_filter(array_map(
                static fn (mixed $value): ?string => is_string($value) ? self::trimOrNull($value) : null,
                $rawLocationStrings,
            ))),
        );
    }

    private static function trimOrNull(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim($value);

        return $normalized === '' ? null : $normalized;
    }
}

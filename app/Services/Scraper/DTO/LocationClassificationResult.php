<?php

declare(strict_types=1);

namespace App\Services\Scraper\DTO;

use App\Enums\TurkeyLocationCategory;

final readonly class LocationClassificationResult
{
    public function __construct(
        public TurkeyLocationCategory $category,
        public bool $isTurkeyRelevant,
        public ?string $city,
        public ?string $country,
    ) {}
}

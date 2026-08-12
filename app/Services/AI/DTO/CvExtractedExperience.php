<?php

declare(strict_types=1);

namespace App\Services\AI\DTO;

final readonly class CvExtractedExperience
{
    public function __construct(
        public string $title,
        public ?string $company = null,
        public ?int $years = null,
        public ?float $confidence = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $title = trim((string) ($data['title'] ?? ''));

        if ($title === '') {
            throw new \InvalidArgumentException('Extracted experience title is required.');
        }

        $company = isset($data['company']) ? trim((string) $data['company']) : null;
        $years = isset($data['years']) ? max(0, (int) $data['years']) : null;
        $confidence = isset($data['confidence']) ? (float) $data['confidence'] : null;

        if ($confidence !== null) {
            $confidence = max(0.0, min(1.0, $confidence));
        }

        return new self(
            title: $title,
            company: $company !== '' ? $company : null,
            years: $years,
            confidence: $confidence,
        );
    }

    /**
     * @return array{title: string, company: ?string, years: ?int, confidence: ?float}
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'company' => $this->company,
            'years' => $this->years,
            'confidence' => $this->confidence,
        ];
    }
}

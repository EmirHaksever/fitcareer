<?php

declare(strict_types=1);

namespace App\Services\AI\DTO;

final readonly class CvExtractedSkill
{
    public function __construct(
        public string $name,
        public ?float $confidence = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $name = trim((string) ($data['name'] ?? ''));

        if ($name === '') {
            throw new \InvalidArgumentException('Extracted skill name is required.');
        }

        $confidence = isset($data['confidence']) ? (float) $data['confidence'] : null;

        if ($confidence !== null) {
            $confidence = max(0.0, min(1.0, $confidence));
        }

        return new self($name, $confidence);
    }

    /**
     * @return array{name: string, confidence: ?float}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'confidence' => $this->confidence,
        ];
    }
}

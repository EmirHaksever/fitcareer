<?php

declare(strict_types=1);

namespace App\Services\TrustScore;

final class SignalResult
{
    /**
     * @param  array<string, mixed>  $evidence
     */
    public function __construct(
        public readonly ?int $score,
        public readonly float $confidence = 1.0,
        public readonly array $evidence = [],
    ) {}

    public function isUsable(): bool
    {
        return $this->score !== null && $this->confidence > 0;
    }

    /**
     * @return array{score: ?int, confidence: float, evidence: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'score' => $this->score,
            'confidence' => $this->confidence,
            'evidence' => $this->evidence,
        ];
    }
}

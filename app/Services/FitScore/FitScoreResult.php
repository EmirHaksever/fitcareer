<?php

declare(strict_types=1);

namespace App\Services\FitScore;

final class FitScoreResult
{
    /**
     * @param  array<string, array{score: ?int, confidence: float, evidence: array<string, mixed>}>  $signals
     */
    public function __construct(
        public readonly ?int $score,
        public readonly array $signals,
        public readonly string $version,
        public readonly ?float $confidence,
    ) {}
}

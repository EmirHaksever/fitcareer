<?php

declare(strict_types=1);

namespace App\Services\TrustScore;

use App\Enums\TrustLabel;

final class TrustScoreResult
{
    /**
     * @param  array<string, array{score: ?int, confidence: float, evidence: array<string, mixed>}>  $signals
     */
    public function __construct(
        public readonly ?int $score,
        public readonly TrustLabel $label,
        public readonly array $signals,
    ) {}
}

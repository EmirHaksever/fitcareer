<?php

declare(strict_types=1);

namespace App\Services\Job\DTO;

readonly class GhostJobScoreResult
{
    /**
     * @param  list<array{signal: string, impact: int, reason: string}>  $signals
     */
    public function __construct(
        public string $version,
        public int $score,
        public string $riskLevel,
        public array $signals,
    ) {}
}

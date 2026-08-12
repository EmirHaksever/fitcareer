<?php

declare(strict_types=1);

namespace App\Services\FitScore;

use App\Models\Job;

final class FitScoreWeightResolver
{
    /**
     * @return list<string>
     */
    public static function signalKeys(): array
    {
        return array_keys(config('fit_score.weights'));
    }

    /**
     * @return array{weights: array<string, int>, source: 'custom'|'default'}
     */
    public function resolveForJob(Job $job): array
    {
        $defaults = $this->defaultWeights();

        if ($job->fit_score_weights === null) {
            return [
                'weights' => $defaults,
                'source' => 'default',
            ];
        }

        return [
            'weights' => $this->normalizeWeights($job->fit_score_weights),
            'source' => 'custom',
        ];
    }

    /**
     * @return array<string, int>
     */
    public function defaultWeights(): array
    {
        /** @var array<string, int> $weights */
        $weights = config('fit_score.weights');

        return $this->normalizeWeights($weights);
    }

    /**
     * @param  array<string, mixed>  $weights
     * @return array<string, int>
     */
    public function normalizeWeights(array $weights): array
    {
        $normalized = [];

        foreach (self::signalKeys() as $key) {
            $normalized[$key] = (int) $weights[$key];
        }

        return $normalized;
    }
}

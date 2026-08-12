<?php

declare(strict_types=1);

namespace App\Support;

use App\Services\FitScore\FitScoreWeightResolver;
use Illuminate\Validation\ValidationException;

final class FitScoreWeightsValidator
{
    /**
     * @param  array<string, mixed>  $weights
     * @return array<string, int>
     */
    public static function validate(array $weights): array
    {
        $expectedKeys = FitScoreWeightResolver::signalKeys();
        $providedKeys = array_keys($weights);

        sort($expectedKeys);
        sort($providedKeys);

        if ($providedKeys !== $expectedKeys) {
            throw ValidationException::withMessages([
                'weights' => ['All fit score signal weights must be provided.'],
            ]);
        }

        $normalized = [];
        $sum = 0;

        foreach (FitScoreWeightResolver::signalKeys() as $key) {
            $value = $weights[$key];

            if (! is_int($value) && ! (is_string($value) && ctype_digit($value))) {
                throw ValidationException::withMessages([
                    "weights.{$key}" => ['Weight must be an integer.'],
                ]);
            }

            $intValue = (int) $value;

            if ($intValue < 0) {
                throw ValidationException::withMessages([
                    "weights.{$key}" => ['Weight cannot be negative.'],
                ]);
            }

            $normalized[$key] = $intValue;
            $sum += $intValue;
        }

        if ($sum !== 100) {
            throw ValidationException::withMessages([
                'weights' => ['Fit score weights must sum to exactly 100.'],
            ]);
        }

        return $normalized;
    }
}

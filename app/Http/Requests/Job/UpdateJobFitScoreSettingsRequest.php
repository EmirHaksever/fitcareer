<?php

declare(strict_types=1);

namespace App\Http\Requests\Job;

use App\Http\Requests\ApiFormRequest;
use App\Services\FitScore\FitScoreWeightResolver;

class UpdateJobFitScoreSettingsRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $weightRules = [];

        foreach (FitScoreWeightResolver::signalKeys() as $key) {
            $weightRules["weights.{$key}"] = ['required', 'integer', 'min:0', 'max:100'];
        }

        return [
            'weights' => ['required', 'array', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! is_array($value)) {
                    return;
                }

                $unknownKeys = array_diff(array_keys($value), FitScoreWeightResolver::signalKeys());

                if ($unknownKeys !== []) {
                    $fail('Unknown fit score signals are not allowed.');
                }
            }],
            ...$weightRules,
        ];
    }
}

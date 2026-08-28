<?php

declare(strict_types=1);

namespace App\Services\Job;

use App\Enums\WorkType;
use App\Models\Job;
use Illuminate\Validation\ValidationException;

class InternalJobQualityGate
{
    public static function minDescriptionLength(): int
    {
        return max(1, (int) config('trust_score.thresholds.content.min_description_length', 100));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $current
     *
     * @return array<string, list<string>>
     */
    public static function validatePayload(array $payload, array $current = []): array
    {
        $errors = [];
        $description = array_key_exists('description', $payload)
            ? trim((string) $payload['description'])
            : trim((string) ($current['description'] ?? ''));

        if (mb_strlen($description) < self::minDescriptionLength()) {
            $errors['description'] = [
                'The job description must be at least '.self::minDescriptionLength().' characters.',
            ];
        }

        $workType = array_key_exists('work_type', $payload)
            ? (string) $payload['work_type']
            : (string) ($current['work_type'] ?? '');

        $city = array_key_exists('city', $payload)
            ? trim((string) ($payload['city'] ?? ''))
            : trim((string) ($current['city'] ?? ''));

        $country = array_key_exists('country', $payload)
            ? trim((string) ($payload['country'] ?? ''))
            : trim((string) ($current['country'] ?? ''));

        if ($country === '') {
            $errors['country'] = ['The country field is required.'];
        }

        if ($workType !== WorkType::Remote->value && $city === '') {
            $errors['city'] = ['The city field is required unless the job is remote.'];
        }

        return $errors;
    }

    public static function assertJobPublishable(Job $job): void
    {
        $errors = self::validatePayload([], [
            'description' => $job->description,
            'work_type' => $job->work_type?->value,
            'city' => $job->city,
            'country' => $job->country,
        ]);

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}

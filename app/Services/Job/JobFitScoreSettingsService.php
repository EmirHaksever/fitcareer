<?php

declare(strict_types=1);

namespace App\Services\Job;

use App\Enums\JobOrigin;
use App\Enums\JobStatus;
use App\Models\Job;
use App\Services\FitScore\FitScoreWeightResolver;
use App\Support\FitScoreWeightsValidator;
use Illuminate\Validation\ValidationException;

class JobFitScoreSettingsService
{
    public function __construct(
        private readonly FitScoreWeightResolver $fitScoreWeightResolver,
    ) {}

    /**
     * @return array{weights: array<string, int>, source: 'custom'|'default'}
     */
    public function get(Job $job): array
    {
        return $this->fitScoreWeightResolver->resolveForJob($job);
    }

    /**
     * @param  array<string, mixed>  $weights
     * @return array{weights: array<string, int>, source: 'custom'|'default'}
     */
    public function update(Job $job, array $weights): array
    {
        $this->assertManageable($job);

        $validated = FitScoreWeightsValidator::validate($weights);

        $job->forceFill([
            'fit_score_weights' => $validated,
        ])->save();

        return $this->fitScoreWeightResolver->resolveForJob($job->fresh());
    }

    private function assertManageable(Job $job): void
    {
        if ($job->source !== JobOrigin::Internal) {
            throw ValidationException::withMessages([
                'job' => ['Only internal jobs can be modified.'],
            ]);
        }

        if (! in_array($job->status, [JobStatus::Draft, JobStatus::PendingReview], true)) {
            throw ValidationException::withMessages([
                'status' => ['Published or closed jobs cannot be modified.'],
            ]);
        }
    }
}

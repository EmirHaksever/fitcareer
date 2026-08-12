<?php

declare(strict_types=1);

namespace App\Services\TrustScore\Signals;

use App\Enums\JobOrigin;
use App\Enums\JobSourceType;
use App\Models\Job;
use App\Services\TrustScore\Contracts\TrustSignalInterface;
use App\Services\TrustScore\SignalResult;

final class SourceReliabilitySignal implements TrustSignalInterface
{
    public function key(): string
    {
        return 'source_reliability';
    }

    public function evaluate(Job $job): SignalResult
    {
        $thresholds = config('trust_score.thresholds.source');

        if ($job->source === JobOrigin::Internal) {
            return new SignalResult((int) $thresholds['internal'], 1.0, [
                'source' => $job->source->value,
            ]);
        }

        $sourceProvider = $job->sourceProvider;

        if ($sourceProvider === null) {
            return new SignalResult((int) $thresholds['scraped_without_source'], 0.9, [
                'source' => $job->source->value,
                'job_source_id' => null,
            ]);
        }

        $score = match ($sourceProvider->type) {
            JobSourceType::ApiIntegration => (int) $thresholds['api_integration'],
            JobSourceType::Scraper => (int) $thresholds['scraper'],
        };

        return new SignalResult($score, 1.0, [
            'source' => $job->source->value,
            'job_source_id' => $sourceProvider->id,
            'job_source_type' => $sourceProvider->type->value,
        ]);
    }
}

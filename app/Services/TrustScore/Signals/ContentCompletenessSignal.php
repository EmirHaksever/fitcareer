<?php

declare(strict_types=1);

namespace App\Services\TrustScore\Signals;

use App\Models\Job;
use App\Services\TrustScore\Contracts\TrustSignalInterface;
use App\Services\TrustScore\SignalResult;

final class ContentCompletenessSignal implements TrustSignalInterface
{
    public function key(): string
    {
        return 'content_completeness';
    }

    public function evaluate(Job $job): SignalResult
    {
        $thresholds = config('trust_score.thresholds.content');
        $description = trim((string) $job->description);
        $title = trim((string) $job->title);

        if ($description === '') {
            return new SignalResult(null, 0.0, [
                'reason' => 'missing_description',
            ]);
        }

        $points = 0;
        $maxPoints = 4;

        if (strlen($description) >= (int) $thresholds['min_description_length']) {
            $points++;
        }

        if (strlen($title) >= (int) $thresholds['min_title_length']) {
            $points++;
        }

        if (filled($job->requirements)) {
            $points++;
        }

        if (filled($job->responsibilities)) {
            $points++;
        }

        $score = (int) round(($points / $maxPoints) * 100);
        $confidence = strlen($description) < (int) $thresholds['min_description_length'] ? 0.6 : 1.0;

        return new SignalResult($score, $confidence, [
            'points' => $points,
            'max_points' => $maxPoints,
            'description_length' => strlen($description),
            'has_requirements' => filled($job->requirements),
            'has_responsibilities' => filled($job->responsibilities),
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Services\TrustScore\Signals;

use App\Enums\JobReportStatus;
use App\Models\Job;
use App\Services\TrustScore\Contracts\TrustSignalInterface;
use App\Services\TrustScore\SignalResult;

final class ReportPenaltySignal implements TrustSignalInterface
{
    public function key(): string
    {
        return 'report_penalty';
    }

    public function evaluate(Job $job): SignalResult
    {
        $thresholds = config('trust_score.thresholds.reports');
        $openStatuses = $thresholds['open_statuses'];
        $seriousReasons = $thresholds['serious_reasons'];

        $openReports = $job->reports()
            ->whereIn('status', array_map(
                static fn (string $status): JobReportStatus => JobReportStatus::from($status),
                $openStatuses,
            ))
            ->get(['reason']);

        if ($openReports->isEmpty()) {
            return new SignalResult(100, 1.0, [
                'open_report_count' => 0,
                'serious_report_count' => 0,
            ]);
        }

        $openCount = $openReports->count();
        $seriousCount = $openReports
            ->filter(fn ($report): bool => in_array($report->reason->value, $seriousReasons, true))
            ->count();

        $penalty = ($openCount * (int) $thresholds['penalty_per_open_report'])
            + ($seriousCount * (int) $thresholds['penalty_per_serious_report']);

        $score = max(0, 100 - $penalty);

        return new SignalResult($score, 1.0, [
            'open_report_count' => $openCount,
            'serious_report_count' => $seriousCount,
            'penalty' => $penalty,
        ]);
    }
}

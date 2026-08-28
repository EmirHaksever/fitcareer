<?php

declare(strict_types=1);

namespace App\Services\Job;

use App\Models\Job;
use App\Services\Job\DTO\GhostJobScoreResult;
use Illuminate\Support\Carbon;

class GhostJobScoreService
{
    public const VERSION = 'GHOST_JOB_SCORE_V1';

    public function score(Job $job, ?Carbon $referenceDate = null): GhostJobScoreResult
    {
        $referenceDate ??= Carbon::now();
        $signals = [];
        $totalImpact = 0;

        if ($job->published_at !== null) {
            $days = (int) $job->published_at->diffInDays($referenceDate);
            $impact = $this->postingAgeImpact($days);

            if ($impact > 0) {
                $signals[] = [
                    'signal' => 'posting_age',
                    'impact' => $impact,
                    'reason' => "Posting has existed for {$days} days",
                ];
                $totalImpact += $impact;
            }
        }

        if ($job->first_seen_at !== null) {
            $days = (int) $job->first_seen_at->diffInDays($referenceDate);
            $impact = $this->persistenceImpact($days);

            if ($impact > 0) {
                $signals[] = [
                    'signal' => 'persistence_age',
                    'impact' => $impact,
                    'reason' => "Job has been tracked for {$days} days since first seen",
                ];
                $totalImpact += $impact;
            }
        }

        if ($job->last_seen_at !== null) {
            $days = (int) $job->last_seen_at->diffInDays($referenceDate);
            $impact = $this->lastSeenImpact($days);

            if ($impact > 0) {
                $signals[] = [
                    'signal' => 'last_seen_freshness',
                    'impact' => $impact,
                    'reason' => "Job was last seen {$days} days ago",
                ];
                $totalImpact += $impact;
            }
        }

        $maintenanceReduction = $this->providerMaintenanceReduction($job, $referenceDate);

        if ($maintenanceReduction > 0) {
            $signals[] = [
                'signal' => 'provider_maintenance',
                'impact' => -$maintenanceReduction,
                'reason' => 'Provider update timestamp indicates active maintenance',
            ];
        }

        $score = max(0, min(100, $totalImpact - $maintenanceReduction));

        return new GhostJobScoreResult(
            version: self::VERSION,
            score: $score,
            riskLevel: $this->resolveRiskLevel($score),
            signals: $signals,
        );
    }

    private function postingAgeImpact(int $days): int
    {
        return match (true) {
            $days <= 30 => 0,
            $days <= 60 => 10,
            $days <= 90 => 20,
            $days <= 180 => 35,
            $days <= 365 => 50,
            default => 50,
        };
    }

    private function persistenceImpact(int $days): int
    {
        return match (true) {
            $days <= 30 => 0,
            $days <= 60 => 10,
            $days <= 90 => 20,
            $days <= 180 => 30,
            $days <= 365 => 40,
            default => 40,
        };
    }

    private function lastSeenImpact(int $days): int
    {
        return match (true) {
            $days <= 7 => 0,
            $days <= 14 => 10,
            $days <= 30 => 25,
            default => 40,
        };
    }

    private function providerMaintenanceReduction(Job $job, Carbon $referenceDate): int
    {
        if ($job->provider_updated_at === null || $job->published_at === null) {
            return 0;
        }

        if ($job->provider_updated_at->lte($job->published_at->copy()->addDays(7))) {
            return 0;
        }

        $reduction = 15;

        if ($job->provider_updated_at->diffInDays($referenceDate) <= 30) {
            $reduction += 10;
        }

        return $reduction;
    }

    private function resolveRiskLevel(int $score): string
    {
        return match (true) {
            $score <= 33 => 'low',
            $score <= 66 => 'medium',
            default => 'high',
        };
    }
}

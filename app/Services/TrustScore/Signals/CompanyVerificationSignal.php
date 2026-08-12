<?php

declare(strict_types=1);

namespace App\Services\TrustScore\Signals;

use App\Enums\CompanyVerificationStatus;
use App\Models\Job;
use App\Services\TrustScore\Contracts\TrustSignalInterface;
use App\Services\TrustScore\SignalResult;

final class CompanyVerificationSignal implements TrustSignalInterface
{
    public function key(): string
    {
        return 'company_verification';
    }

    public function evaluate(Job $job): SignalResult
    {
        $company = $job->company;

        if ($company === null) {
            return new SignalResult(null, 0.0, [
                'reason' => 'no_company',
            ]);
        }

        $thresholds = config('trust_score.thresholds.company_verification');
        $status = $company->verification_status;

        $score = match ($status) {
            CompanyVerificationStatus::Verified => $company->is_verified
                ? (int) $thresholds['verified']
                : (int) $thresholds['verified_without_flag'],
            CompanyVerificationStatus::Pending => (int) $thresholds['pending'],
            CompanyVerificationStatus::Rejected => (int) $thresholds['rejected'],
            CompanyVerificationStatus::Unverified => (int) $thresholds['unverified'],
        };

        return new SignalResult($score, 1.0, [
            'verification_status' => $status->value,
            'is_verified' => $company->is_verified,
        ]);
    }
}

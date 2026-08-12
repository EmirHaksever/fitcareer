<?php

declare(strict_types=1);

namespace App\Services\TrustScore\Signals;

use App\Models\Job;
use App\Services\TrustScore\Contracts\TrustSignalInterface;
use App\Services\TrustScore\SignalResult;

final class ContactInformationSignal implements TrustSignalInterface
{
    public function key(): string
    {
        return 'contact_information';
    }

    public function evaluate(Job $job): SignalResult
    {
        $checks = [
            'job_contact_email' => filled($job->contact_email),
            'job_contact_phone' => filled($job->contact_phone),
            'company_contact_email' => filled($job->company?->contact_email),
            'company_contact_phone' => filled($job->company?->contact_phone),
            'company_website' => filled($job->company?->website),
        ];

        $present = count(array_filter($checks));

        if ($present === 0) {
            return new SignalResult(null, 0.0, [
                'reason' => 'no_contact_channels',
                'checks' => $checks,
            ]);
        }

        $score = (int) round(($present / count($checks)) * 100);

        return new SignalResult($score, 1.0, [
            'present_count' => $present,
            'checks' => $checks,
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Scraper;

use App\Enums\JobSourceIngestPolicy;
use App\Enums\TurkeyLocationCategory;
use App\Enums\WorkType;
use App\Exceptions\ScraperFetchException;
use App\Models\JobSource;
use App\Services\Scraper\DTO\LocationInput;

class JobSourceIngestPolicyService
{
    public function __construct(
        private readonly LocationClassificationService $locationClassifier,
    ) {}

    public function resolvePolicy(JobSource $source): JobSourceIngestPolicy
    {
        $config = $source->config ?? [];
        $policy = $config['ingest_policy'] ?? null;

        return JobSourceIngestPolicy::fromConfig(is_string($policy) ? $policy : null);
    }

    /**
     * @param  array<string, mixed>  $normalizedAttributes
     */
    public function assertAcceptable(JobSource $source, array $normalizedAttributes): void
    {
        $policy = $this->resolvePolicy($source);

        if ($policy === JobSourceIngestPolicy::Global) {
            return;
        }

        $city = is_string($normalizedAttributes['city'] ?? null) ? $normalizedAttributes['city'] : null;
        $country = is_string($normalizedAttributes['country'] ?? null) ? $normalizedAttributes['country'] : null;
        $workType = $normalizedAttributes['work_type'] ?? null;
        $workType = $workType instanceof WorkType ? $workType : null;

        $classification = $this->locationClassifier->classify(
            LocationInput::fromSignals($city, $country, $workType),
        );

        if ($policy === JobSourceIngestPolicy::TurkeyFirst) {
            if (! $classification->isTurkeyRelevant) {
                throw ScraperFetchException::invalidPayload(
                    'Listing rejected by source ingest_policy=turkey_first (not Turkey-relevant).'
                );
            }

            return;
        }

        if ($policy === JobSourceIngestPolicy::RemoteOpen) {
            if ($classification->isTurkeyRelevant) {
                return;
            }

            if ($workType !== WorkType::Remote) {
                throw ScraperFetchException::invalidPayload(
                    'Listing rejected by source ingest_policy=remote_open (not remote and not Turkey-relevant).'
                );
            }

            if ($classification->category === TurkeyLocationCategory::Foreign) {
                throw ScraperFetchException::invalidPayload(
                    'Listing rejected by source ingest_policy=remote_open (explicitly global/foreign remote).'
                );
            }

            return;
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Scraper;

use App\Models\Job;
use App\Models\JobSource;

class JobIngestionService
{
    public function __construct(
        private readonly JobNormalizerService $jobNormalizerService,
        private readonly JobSourceIngestPolicyService $jobSourceIngestPolicyService,
        private readonly DuplicateDetectionService $duplicateDetectionService,
        private readonly ScrapedJobEnrichmentService $scrapedJobEnrichmentService,
    ) {}

    /**
     * @param  array<string, mixed>  $rawListing
     * @return array{job: Job, created: bool}
     */
    public function ingest(JobSource $source, array $rawListing): array
    {
        $attributes = $this->jobNormalizerService->normalize($source, $rawListing);
        $this->jobSourceIngestPolicyService->assertAcceptable($source, $attributes);
        $externalId = (string) $attributes['external_id'];
        $contentHash = (string) $attributes['content_hash'];

        $existing = $this->duplicateDetectionService->findExisting($source, $externalId, $contentHash);

        if ($existing !== null) {
            $incomingProviderUpdatedAt = $attributes['provider_updated_at'] ?? null;

            unset($attributes['first_seen_at'], $attributes['provider_updated_at']);

            $existing->fill($attributes);

            if ($incomingProviderUpdatedAt !== null) {
                $existing->provider_updated_at = $incomingProviderUpdatedAt;
            }

            $existing->save();

            $job = $this->scrapedJobEnrichmentService->enrich($existing);

            return [
                'job' => $job->fresh(['sourceProvider', 'skills']),
                'created' => false,
            ];
        }

        $attributes['first_seen_at'] = now();

        $job = Job::query()->create($attributes);
        $job = $this->scrapedJobEnrichmentService->enrich($job);

        return [
            'job' => $job->fresh(['sourceProvider', 'skills']),
            'created' => true,
        ];
    }
}

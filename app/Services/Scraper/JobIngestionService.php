<?php

declare(strict_types=1);

namespace App\Services\Scraper;

use App\Models\Job;
use App\Models\JobSource;

class JobIngestionService
{
    public function __construct(
        private readonly JobNormalizerService $jobNormalizerService,
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
        $externalId = (string) $attributes['external_id'];
        $contentHash = (string) $attributes['content_hash'];

        $existing = $this->duplicateDetectionService->findExisting($source, $externalId, $contentHash);

        if ($existing !== null) {
            $existing->fill($attributes);
            $existing->save();

            $job = $this->scrapedJobEnrichmentService->enrich($existing);

            return [
                'job' => $job->fresh(['sourceProvider', 'skills']),
                'created' => false,
            ];
        }

        $job = Job::query()->create($attributes);
        $job = $this->scrapedJobEnrichmentService->enrich($job);

        return [
            'job' => $job->fresh(['sourceProvider', 'skills']),
            'created' => true,
        ];
    }
}

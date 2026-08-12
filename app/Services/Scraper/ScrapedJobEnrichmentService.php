<?php

declare(strict_types=1);

namespace App\Services\Scraper;

use App\Enums\JobOrigin;
use App\Enums\SkillImportance;
use App\Models\Job;
use App\Services\AI\JobTrustAnalysisService;
use App\Services\Job\JobDescriptionSkillExtractor;
use Illuminate\Support\Facades\DB;

class ScrapedJobEnrichmentService
{
    private const MAX_REQUIRED_SKILLS = 8;

    private const MAX_TOTAL_SKILLS = 15;

    public function __construct(
        private readonly JobDescriptionSkillExtractor $skillExtractor,
        private readonly JobTrustAnalysisService $jobTrustAnalysisService,
    ) {}

    public function enrich(Job $job): Job
    {
        if ($job->source !== JobOrigin::Scraped) {
            return $job;
        }

        $this->syncSkillsFromDescription($job);

        $this->jobTrustAnalysisService->analyze(
            $job->fresh(['company', 'sourceProvider', 'skills']),
        );

        return $job->fresh(['company', 'sourceProvider', 'skills']);
    }

    private function syncSkillsFromDescription(Job $job): void
    {
        $description = trim((string) ($job->description ?? ''));

        if ($description === '') {
            return;
        }

        $skills = $this->skillExtractor->extract($description)->take(self::MAX_TOTAL_SKILLS);

        if ($skills->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($job, $skills): void {
            $job->jobSkills()->delete();

            foreach ($skills->values() as $index => $skill) {
                $job->jobSkills()->create([
                    'skill_id' => $skill->id,
                    'importance' => $index < self::MAX_REQUIRED_SKILLS
                        ? SkillImportance::Required
                        : SkillImportance::Preferred,
                ]);
            }
        });
    }
}

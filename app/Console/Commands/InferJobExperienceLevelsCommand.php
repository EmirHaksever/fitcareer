<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\ExperienceLevel;
use App\Models\Job;
use App\Models\JobSource;
use App\Services\Job\ExperienceLevelInferenceService;
use Illuminate\Console\Command;

class InferJobExperienceLevelsCommand extends Command
{
    protected $signature = 'jobs:infer-experience-levels
        {--dry-run : Report inferred changes without writing}
        {--limit=500 : Maximum jobs to inspect}
        {--overwrite : Replace existing non-null experience_level values}
        {--source= : Filter by JobSource id or name}';

    protected $description = 'Fill missing job experience_level values from high-confidence title inference.';

    public function handle(ExperienceLevelInferenceService $inference): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $dryRun = (bool) $this->option('dry-run');
        $overwrite = (bool) $this->option('overwrite');

        $query = Job::query()->orderBy('id');

        if (! $overwrite) {
            $query->whereNull('experience_level');
        }

        $sourceKey = trim((string) $this->option('source'));
        if ($sourceKey !== '') {
            $source = JobSource::query()
                ->where('id', $sourceKey)
                ->orWhereRaw('LOWER(name) = ?', [mb_strtolower($sourceKey)])
                ->first();

            if ($source === null) {
                $this->error('Job source not found.');

                return self::FAILURE;
            }

            $query->where('job_source_id', $source->id);
        }

        $inspected = 0;
        $inferred = 0;
        $unknown = 0;
        $written = 0;
        $byLevel = [];

        $query->limit($limit)->each(function (Job $job) use ($inference, $dryRun, &$inspected, &$inferred, &$unknown, &$written, &$byLevel): void {
            $inspected++;
            $level = $inference->inferFromTitle((string) $job->title);

            if ($level === null) {
                $unknown++;

                return;
            }

            $inferred++;
            $byLevel[$level->value] = ($byLevel[$level->value] ?? 0) + 1;

            if ($dryRun) {
                return;
            }

            $job->experience_level = $level;
            $job->save();
            $written++;
        });

        $this->line('Inspected: '.$inspected);
        $this->line('Inferred: '.$inferred);
        $this->line('Unknown retained: '.$unknown);
        $this->line('Written: '.$written);
        $this->line('Dry run: '.($dryRun ? 'yes' : 'no'));
        $this->line('By level: '.json_encode($byLevel, JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }
}

<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Exceptions\AiConfigurationMissingException;
use App\Exceptions\AiProviderUnavailableException;
use App\Exceptions\AiStructuredOutputInvalidException;
use App\Models\CandidateProfile;
use App\Models\Job;
use App\Services\AI\CvExtractionApplicator;
use App\Services\AI\CvExtractionPipeline;
use App\Services\AI\CvExtractionService;
use App\Services\AI\CvJobFitAnalysisService;
use App\Services\AI\CvSkillCatalogMatcher;
use App\Services\AI\GeminiClient;
use App\Services\Candidate\CvParserService;
use Illuminate\Console\Command;

class TestAiCommand extends Command
{
    protected $signature = 'fitcareer:test-ai
        {--cv-path= : Optional PDF/DOCX path for parser → Gemini test}
        {--profile-id= : Optional candidate profile id for fit score verification}
        {--job-id= : Optional job id for fit score verification}';

    protected $description = 'Run a real Gemini CV extraction diagnostic against the configured API key.';

    public function handle(
        GeminiClient $geminiClient,
        CvExtractionService $cvExtractionService,
        CvExtractionApplicator $cvExtractionApplicator,
        CvExtractionPipeline $cvExtractionPipeline,
        CvParserService $cvParserService,
        CvJobFitAnalysisService $cvJobFitAnalysisService,
    ): int {
        if (! $geminiClient->isConfigured()) {
            $this->error('CONFIGURATION_MISSING');

            return self::FAILURE;
        }

        $this->line('Provider: '.config('ai.provider'));
        $this->line('Model: '.config('ai.gemini.model'));

        try {
            [$cvText, $sourceLabel] = $this->resolveCvText($cvParserService);
            $this->line('CV source: '.$sourceLabel);

            $extraction = $cvExtractionService->extractFromText($cvText);

            $this->info('Extraction: SUCCESS');
            $this->line('');
            $this->line('Skills:');

            foreach ($extraction->skillNames() as $skill) {
                $this->line('- '.$skill);
            }

            $this->line('');
            $this->line('Experience:');

            if ($extraction->totalExperienceYears !== null) {
                $this->line('- '.$extraction->totalExperienceYears.' years total');
            }

            foreach ($extraction->experience as $item) {
                $company = $item->company ?? 'Unknown company';
                $years = $item->years !== null ? ' ('.$item->years.'y)' : '';
                $this->line('- '.$item->title.' @ '.$company.$years);
            }

            $this->line('');
            $this->line('Location: '.($extraction->location ?? 'n/a'));

            $skillMatches = app(CvSkillCatalogMatcher::class)->matchMany($extraction->skillNames());
            $expectedCatalogSkills = ['Flutter', 'Dart', 'Firebase', 'Supabase', 'REST API', 'Git'];
            $falsePositiveInputs = ['Flutter' => 'Ut', 'Supabase' => 'Ab', 'REST API' => 'Est'];

            $this->line('');
            $this->line('Catalog mapping:');

            foreach ($skillMatches['matched'] as $match) {
                $expectedFalse = $falsePositiveInputs[$match['input']] ?? null;
                $status = $expectedFalse !== null && $match['skill']->name === $expectedFalse
                    ? 'FALSE-POSITIVE'
                    : 'OK';

                $this->line(sprintf(
                    '- %s → %s (id:%d) [%s]',
                    $match['input'],
                    $match['skill']->name,
                    $match['skill']->id,
                    $status,
                ));
            }

            foreach ($skillMatches['unmatched'] as $unmatched) {
                $this->line('- '.$unmatched.' → unmatched');
            }

            $falsePositiveCount = 0;

            foreach ($skillMatches['matched'] as $match) {
                $expectedFalse = $falsePositiveInputs[$match['input']] ?? null;

                if ($expectedFalse !== null && $match['skill']->name === $expectedFalse) {
                    $falsePositiveCount++;
                }
            }

            $matchedExpectedCount = 0;

            foreach ($expectedCatalogSkills as $expectedSkill) {
                foreach ($skillMatches['matched'] as $match) {
                    if ($match['skill']->name === $expectedSkill) {
                        $matchedExpectedCount++;

                        break;
                    }
                }
            }

            $this->line('');
            $this->line('Catalog matches: '.count($skillMatches['matched']));
            $this->line('Catalog unmatched: '.count($skillMatches['unmatched']));
            $this->line('Expected catalog hits: '.$matchedExpectedCount.'/'.count($expectedCatalogSkills));
            $this->line('False positives: '.$falsePositiveCount);

            $profileId = $this->option('profile-id');
            $jobId = $this->option('job-id');

            if ($profileId !== null && $jobId !== null) {
                $profile = CandidateProfile::query()->find((int) $profileId);
                $job = Job::query()->with('skills')->find((int) $jobId);

                if ($profile === null || $job === null) {
                    $this->warn('Profile or job not found; skipping fit score step.');
                } else {
                    $applySummary = $cvExtractionApplicator->apply($profile, $extraction);
                    $profile->forceFill([
                        'cv_parsed_data' => array_merge($profile->cv_parsed_data ?? [], [
                            'ai_extraction' => $cvExtractionPipeline->buildMetadata($extraction, $applySummary, 'completed'),
                        ]),
                    ])->save();

                    $analysis = $cvJobFitAnalysisService->analyze($profile->fresh(['candidateSkills', 'skills', 'experiences']), $job);

                    $this->line('');
                    $this->line('Fit Score: '.($analysis->score ?? 'null'));
                    $this->line('AI Model: '.($analysis->ai_model ?? 'null'));
                    $this->line('Raw AI response persisted: '.($analysis->raw_response !== null ? 'YES' : 'NO'));

                    $requiredSignal = $analysis->details['signals']['required_skills'] ?? null;

                    if (is_array($requiredSignal)) {
                        $this->line('Required skills signal score: '.($requiredSignal['score'] ?? 'n/a'));
                    }
                }
            }

            return self::SUCCESS;
        } catch (AiConfigurationMissingException $exception) {
            $this->error('CONFIGURATION_MISSING');
            $this->line($exception->getMessage());

            return self::FAILURE;
        } catch (AiProviderUnavailableException $exception) {
            $this->error('AI_PROVIDER_UNAVAILABLE');
            $this->line($exception->getMessage());

            return self::FAILURE;
        } catch (AiStructuredOutputInvalidException $exception) {
            $this->error('AI_STRUCTURED_OUTPUT_INVALID');
            $this->line($exception->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveCvText(CvParserService $cvParserService): array
    {
        $cvPath = $this->option('cv-path');

        if (is_string($cvPath) && $cvPath !== '') {
            if (! is_file($cvPath)) {
                throw new \InvalidArgumentException('CV file not found: '.$cvPath);
            }

            $parsed = $cvParserService->parse($cvPath, basename($cvPath));

            return [$parsed['text'], basename($cvPath)];
        }

        return [$this->syntheticCvText(), 'synthetic-cv-text'];
    }

    private function syntheticCvText(): string
    {
        return <<<'CV'
John Doe
Senior Flutter Developer
Istanbul, Turkey
Remote work preferred

Summary
Software developer with 8 years of experience building mobile applications.

Skills
Flutter, Dart, Firebase, Supabase, REST API, Git

Experience
Senior Flutter Developer at Mobile Labs — 4 years
Flutter Developer at App Studio — 4 years

Education
BSc Computer Engineering
CV;
    }
}

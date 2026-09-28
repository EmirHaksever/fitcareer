<?php

declare(strict_types=1);

namespace Tests\Unit\AI;

use App\Enums\AiAnalysisType;
use App\Enums\SkillImportance;
use App\Enums\WorkType;
use App\Models\CandidateSkill;
use App\Models\Job;
use App\Models\Skill;
use App\Services\AI\CvJobFitAnalysisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Job\CreatesJobActors;
use Tests\TestCase;

class CvJobFitAnalysisServiceAiMetadataTest extends TestCase
{
    use CreatesJobActors;
    use RefreshDatabase;

    #[Test]
    public function it_persists_cv_extraction_ai_metadata_on_fit_analysis(): void
    {
        [, $profile] = $this->createCandidateActor();

        $flutter = Skill::factory()->create(['name' => 'Flutter']);
        $job = Job::factory()->published()->create(['work_type' => WorkType::Remote]);
        $job->skills()->attach($flutter->id, ['importance' => SkillImportance::Required]);

        CandidateSkill::factory()->create([
            'candidate_profile_id' => $profile->id,
            'skill_id' => $flutter->id,
        ]);

        $profile->update([
            'cv_parsed_data' => [
                'text' => 'CV',
                'ai_extraction' => [
                    'status' => 'completed',
                    'model' => 'gemini-flash-latest',
                    'prompt_version' => 'cv-extract-v1',
                    'raw_response' => [
                        'structured' => ['skills' => [['name' => 'Flutter']]],
                        'provider' => ['candidate_count' => 1],
                    ],
                ],
            ],
        ]);

        $analysis = app(CvJobFitAnalysisService::class)->analyze($profile->fresh(['candidateSkills', 'skills', 'experiences']), $job);

        $this->assertSame(AiAnalysisType::CvJobFit, $analysis->type);
        $this->assertSame('gemini-flash-latest', $analysis->ai_model);
        $this->assertSame('cv-extract-v1', $analysis->prompt_version);
        $this->assertIsArray($analysis->raw_response);
        $this->assertNotNull($analysis->score);
    }
}

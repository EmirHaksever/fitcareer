<?php

declare(strict_types=1);

namespace Tests\Feature\Candidate;

use App\Enums\AiAnalysisStatus;
use App\Enums\AiAnalysisType;
use App\Enums\JobStatus;
use App\Models\AiAnalysis;
use App\Models\Job;
use App\Models\SavedJob;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CandidateSavedJobTest extends TestCase
{
    use CreatesCandidateUsers;

    #[Test]
    public function guest_cannot_list_saved_jobs(): void
    {
        $this->getJson('/api/v1/candidate/saved-jobs')
            ->assertUnauthorized();
    }

    #[Test]
    public function candidate_can_save_and_unsave_published_job(): void
    {
        [, , $token] = $this->createCandidateActor();
        $job = Job::factory()->published()->create(['status' => JobStatus::Published]);

        $this->withToken($token)->postJson("/api/v1/candidate/saved-jobs/{$job->id}")
            ->assertCreated()
            ->assertJsonPath('data.job_id', $job->id);

        $this->withToken($token)->getJson('/api/v1/candidate/saved-jobs/ids')
            ->assertOk()
            ->assertJsonPath('data.job_ids', [$job->id]);

        $this->withToken($token)->deleteJson("/api/v1/candidate/saved-jobs/{$job->id}")
            ->assertOk();

        $this->withToken($token)->getJson('/api/v1/candidate/saved-jobs/ids')
            ->assertOk()
            ->assertJsonPath('data.job_ids', []);
    }

    #[Test]
    public function candidate_cannot_save_unpublished_job(): void
    {
        [, , $token] = $this->createCandidateActor();
        $job = Job::factory()->published()->create(['status' => JobStatus::Draft]);

        $this->withToken($token)->postJson("/api/v1/candidate/saved-jobs/{$job->id}")
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    #[Test]
    public function saved_jobs_are_isolated_between_candidates(): void
    {
        [$userA, $profileA] = array_slice($this->createCandidateActor(), 0, 2);
        [$userB, $profileB] = array_slice($this->createCandidateActor(), 0, 2);
        $job = Job::factory()->published()->create(['status' => JobStatus::Published]);

        $this->assertNotSame($profileA->id, $profileB->id);

        SavedJob::query()->create([
            'candidate_profile_id' => $profileA->id,
            'job_id' => $job->id,
            'saved_at' => now(),
        ]);

        $this->actingAs($userA, 'sanctum')->getJson('/api/v1/candidate/saved-jobs/ids')
            ->assertJsonPath('data.job_ids', [$job->id]);

        $this->actingAs($userB, 'sanctum')->getJson('/api/v1/candidate/saved-jobs/ids')
            ->assertJsonPath('data.job_ids', []);

        $this->actingAs($userB, 'sanctum')->deleteJson("/api/v1/candidate/saved-jobs/{$job->id}")
            ->assertNotFound();
    }
}

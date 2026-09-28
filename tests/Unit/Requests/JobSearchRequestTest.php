<?php

namespace Tests\Unit\Requests;

use App\Enums\UserRole;
use App\Http\Requests\Job\JobSearchRequest;
use App\Models\CandidateProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Exceptions\HttpResponseException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class JobSearchRequestTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function to_query_derives_candidate_profile_id_server_side(): void
    {
        $user = User::factory()->create(['role' => UserRole::Candidate]);
        $profile = CandidateProfile::factory()->create(['user_id' => $user->id]);
        $user->load('candidateProfile');

        $request = JobSearchRequest::create('/api/v1/jobs', 'GET', [
            'keyword' => 'laravel',
            'min_fit_score' => 60,
        ]);
        $request->setUserResolver(fn () => $user);
        $request->setContainer(app());
        $request->validateResolved();

        $query = $request->toQuery();

        $this->assertSame($profile->id, $query->candidateProfileId);
        $this->assertSame(60, $query->minFitScore);
    }

    #[Test]
    public function min_fit_score_requires_candidate_context(): void
    {
        $request = JobSearchRequest::create('/api/v1/jobs', 'GET', ['min_fit_score' => 50]);
        $request->setUserResolver(fn () => null);
        $request->setContainer(app());

        try {
            $request->validateResolved();
            $this->fail('Expected validation exception.');
        } catch (HttpResponseException $exception) {
            $this->assertSame(422, $exception->getResponse()->getStatusCode());
            $payload = $exception->getResponse()->getData(true);
            $this->assertArrayHasKey('min_fit_score', $payload['errors']);
        }
    }

    #[Test]
    public function candidate_profile_id_is_prohibited(): void
    {
        $this->getJson('/api/v1/jobs?candidate_profile_id=999')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['candidate_profile_id']);
    }
}

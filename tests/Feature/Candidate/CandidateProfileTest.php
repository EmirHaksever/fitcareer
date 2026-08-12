<?php

namespace Tests\Feature\Candidate;

use App\Enums\WorkPreference;
use App\Models\CandidateExperience;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CandidateProfileTest extends TestCase
{
    use CreatesCandidateUsers;

    protected function setUp(): void
    {
        parent::setUp();

        Model::preventLazyLoading();
    }

    #[Test]
    public function candidate_can_view_profile_with_eager_loaded_relations(): void
    {
        [, $profile, $token] = $this->createCandidateActor();

        CandidateExperience::factory()->create([
            'candidate_profile_id' => $profile->id,
        ]);

        DB::enableQueryLog();

        $response = $this->withToken($token)
            ->getJson('/api/v1/candidate/profile')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'headline',
                    'profile_strength_score',
                    'experiences',
                    'educations',
                    'certifications',
                    'projects',
                    'skills',
                ],
            ]);

        $queryCount = count(DB::getQueryLog());
        $this->assertLessThanOrEqual(12, $queryCount);
        $this->assertNotEmpty($response->json('data.experiences'));
    }

    #[Test]
    public function candidate_can_update_profile_and_recalculate_strength(): void
    {
        [, , $token] = $this->createCandidateActor();

        $this->withToken($token)
            ->putJson('/api/v1/candidate/profile', [
                'headline' => 'Senior PHP Developer',
                'summary' => 'Building APIs',
                'city' => 'Istanbul',
                'country' => 'Turkey',
                'desired_position' => 'Backend Engineer',
                'work_preference' => WorkPreference::Remote->value,
                'years_of_experience' => 6,
                'linkedin_url' => 'https://linkedin.com/in/dev',
            ])
            ->assertOk()
            ->assertJsonPath('data.headline', 'Senior PHP Developer')
            ->assertJsonPath('data.profile_strength_score', 60);
    }

    #[Test]
    public function protected_fields_cannot_be_mass_assigned_on_profile_update(): void
    {
        [, $profile, $token] = $this->createCandidateActor();

        $this->withToken($token)
            ->putJson('/api/v1/candidate/profile', [
                'headline' => 'Updated',
                'user_id' => 999,
                'profile_strength_score' => 100,
                'cv_parsed_data' => ['hacked' => true],
                'cv_file_path' => 'hacked/path.pdf',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'user_id',
                'profile_strength_score',
                'cv_parsed_data',
                'cv_file_path',
            ]);

        $profile->refresh();
        $this->assertSame(0, $profile->profile_strength_score);
        $this->assertNull($profile->cv_file_path);
    }

    #[Test]
    public function candidate_can_download_uploaded_profile_photo(): void
    {
        Storage::fake('local');

        [, , $token] = $this->createCandidateActor();
        $file = UploadedFile::fake()->image('avatar.jpg');

        $this->withToken($token)
            ->postJson('/api/v1/candidate/profile/photo', ['photo' => $file])
            ->assertOk();

        $this->withToken($token)
            ->get('/api/v1/candidate/profile/photo')
            ->assertOk();
    }

    #[Test]
    public function validation_errors_use_standard_api_format(): void
    {
        [, , $token] = $this->createCandidateActor();

        $this->withToken($token)
            ->putJson('/api/v1/candidate/profile', [
                'work_preference' => 'invalid-role',
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed.')
            ->assertJsonStructure(['errors' => ['work_preference']]);
    }
}

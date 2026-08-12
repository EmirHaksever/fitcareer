<?php

namespace Tests\Feature;

use App\Enums\AiAnalysisType;
use App\Enums\ApplicationStatus;
use App\Enums\JobStatus;
use App\Enums\TrustAnalysisStatus;
use App\Enums\TrustLabel;
use App\Enums\UserRole;
use App\Models\AiAnalysis;
use App\Models\Application;
use App\Models\ApplicationStatusHistory;
use App\Models\CandidateExperience;
use App\Models\CandidateProfile;
use App\Models\Company;
use App\Models\Job;
use App\Models\JobReport;
use App\Models\JobSource;
use App\Models\Skill;
use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelRelationshipsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_exposes_role_specific_profile_and_settings_relationships(): void
    {
        $candidate = User::query()->create([
            'name' => 'Aday',
            'email' => 'candidate@example.com',
            'password' => 'secret',
            'role' => UserRole::Candidate,
        ]);
        $profile = CandidateProfile::query()->create([
            'user_id' => $candidate->id,
            'headline' => 'Backend Developer',
        ]);
        $settings = UserSetting::query()->create(['user_id' => $candidate->id]);

        $companyUser = User::query()->create([
            'name' => 'Şirket',
            'email' => 'company@example.com',
            'password' => 'secret',
            'role' => UserRole::Company,
        ]);
        $company = Company::query()->create([
            'user_id' => $companyUser->id,
            'name' => 'Fit Company',
            'slug' => 'fit-company',
        ]);

        $this->assertTrue($candidate->candidateProfile->is($profile));
        $this->assertTrue($candidate->settings->is($settings));
        $this->assertTrue($companyUser->company->is($company));
        $this->assertSame(UserRole::Candidate, $candidate->role);
    }

    public function test_candidate_profile_exposes_skills_and_profile_sections(): void
    {
        $user = User::query()->create([
            'name' => 'Aday',
            'email' => 'profile@example.com',
            'password' => 'secret',
            'role' => UserRole::Candidate,
        ]);
        $profile = CandidateProfile::query()->create([
            'user_id' => $user->id,
            'open_to_work' => true,
        ]);
        $skill = Skill::query()->create(['name' => 'PHP', 'slug' => 'php']);
        $profile->skills()->attach($skill->id, [
            'proficiency_level' => 'advanced',
            'years_of_experience' => 5,
        ]);
        $experience = CandidateExperience::query()->create([
            'candidate_profile_id' => $profile->id,
            'company_name' => 'Example',
            'position_title' => 'Developer',
            'start_date' => '2020-01-01',
        ]);

        $this->assertTrue($profile->skills->first()->is($skill));
        $this->assertSame('advanced', $profile->skills->first()->pivot->proficiency_level);
        $this->assertTrue($profile->experiences->first()->is($experience));
        $this->assertTrue($profile->open_to_work);
    }

    public function test_job_analysis_application_and_report_relationships_preserve_domain_casts(): void
    {
        $companyUser = User::query()->create([
            'name' => 'Şirket',
            'email' => 'job-company@example.com',
            'password' => 'secret',
            'role' => UserRole::Company,
        ]);
        $company = Company::query()->create([
            'user_id' => $companyUser->id,
            'name' => 'Example Company',
            'slug' => 'example-company',
        ]);
        $source = JobSource::query()->create(['name' => 'Internal']);
        $job = Job::query()->create([
            'company_id' => $company->id,
            'job_source_id' => $source->id,
            'posted_by' => $companyUser->id,
            'title' => 'Senior Developer',
            'slug' => 'senior-developer',
            'description' => 'Build reliable products.',
            'employment_type' => 'full_time',
            'work_type' => 'remote',
            'status' => JobStatus::Published,
            'trust_label' => TrustLabel::Verified,
            'trust_analysis_status' => TrustAnalysisStatus::Completed,
        ]);
        $analysis = AiAnalysis::query()->create([
            'type' => AiAnalysisType::JobTrust,
            'job_id' => $job->id,
            'details' => ['signals' => ['source' => 90]],
            'status' => 'completed',
            'is_latest' => true,
        ]);

        $candidate = User::query()->create([
            'name' => 'Aday',
            'email' => 'job-candidate@example.com',
            'password' => 'secret',
            'role' => UserRole::Candidate,
        ]);
        $profile = CandidateProfile::query()->create(['user_id' => $candidate->id]);
        $application = Application::query()->create([
            'candidate_profile_id' => $profile->id,
            'job_id' => $job->id,
            'status' => ApplicationStatus::Submitted,
            'applied_at' => now(),
        ]);
        $history = ApplicationStatusHistory::query()->create([
            'application_id' => $application->id,
            'to_status' => ApplicationStatus::Submitted->value,
        ]);
        $report = JobReport::query()->create([
            'job_id' => $job->id,
            'user_id' => $candidate->id,
            'reason' => 'suspicious_job',
        ]);

        $this->assertTrue($job->company->is($company));
        $this->assertTrue($job->sourceProvider->is($source));
        $this->assertTrue($job->analyses->first()->is($analysis));
        $this->assertTrue($job->applications->first()->is($application));
        $this->assertTrue($job->reports->first()->is($report));
        $this->assertSame(JobStatus::Published, $job->status);
        $this->assertSame(TrustLabel::Verified, $job->trust_label);
        $this->assertSame(['signals' => ['source' => 90]], $analysis->details);
        $this->assertSame(ApplicationStatus::Submitted, $application->status);
        $this->assertTrue($application->statusHistory->first()->is($history));
    }
}

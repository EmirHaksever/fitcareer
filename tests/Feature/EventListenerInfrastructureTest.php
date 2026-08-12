<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Enums\EmploymentType;
use App\Enums\JobStatus;
use App\Enums\UserRole;
use App\Enums\WorkType;
use App\Events\ApplicationStatusChanged;
use App\Events\JobImportCompleted;
use App\Events\JobTrustAnalysisCompleted;
use App\Events\JobTrustAnalysisFailed;
use App\Listeners\DispatchApplicationNotificationListener;
use App\Listeners\RecordApplicationStatusHistoryListener;
use App\Listeners\UpdateJobSourceLastRunListener;
use App\Listeners\UpdateJobTrustFieldsListener;
use App\Models\Application;
use App\Models\CandidateProfile;
use App\Models\Job;
use App\Models\User;
use App\Providers\EventServiceProvider;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\AfterCommitProbeListener;
use Tests\TestCase;

class EventListenerInfrastructureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        AfterCommitProbeListener::reset();
        Event::listen(ApplicationStatusChanged::class, AfterCommitProbeListener::class);
    }

    #[Test]
    public function domain_events_implement_should_dispatch_after_commit(): void
    {
        $this->assertContains(
            ShouldDispatchAfterCommit::class,
            class_implements(ApplicationStatusChanged::class),
        );
        $this->assertContains(
            ShouldDispatchAfterCommit::class,
            class_implements(JobTrustAnalysisCompleted::class),
        );
    }

    #[Test]
    public function event_service_provider_registers_planned_listener_mappings(): void
    {
        $events = $this->app->getProvider(EventServiceProvider::class)->getEvents();

        $this->assertSame(
            [UpdateJobSourceLastRunListener::class],
            $events[JobImportCompleted::class],
        );
        $this->assertSame(
            [UpdateJobTrustFieldsListener::class.'@handleJobTrustAnalysisCompleted'],
            $events[JobTrustAnalysisCompleted::class],
        );
        $this->assertSame(
            [UpdateJobTrustFieldsListener::class.'@handleJobTrustAnalysisFailed'],
            $events[JobTrustAnalysisFailed::class],
        );
        $this->assertSame(
            [
                RecordApplicationStatusHistoryListener::class,
                DispatchApplicationNotificationListener::class,
            ],
            $events[ApplicationStatusChanged::class],
        );
    }

    #[Test]
    public function application_status_changed_is_not_handled_before_transaction_commit(): void
    {
        $application = $this->createApplicationRecord();

        DB::transaction(function () use ($application): void {
            event(new ApplicationStatusChanged(
                $application,
                ApplicationStatus::Submitted,
                ApplicationStatus::UnderReview,
            ));

            $this->assertSame(0, AfterCommitProbeListener::$calls);
        });

        $this->assertSame(1, AfterCommitProbeListener::$calls);
    }

    #[Test]
    public function application_status_changed_is_not_handled_when_transaction_rolls_back(): void
    {
        $application = $this->createApplicationRecord();

        try {
            DB::transaction(function () use ($application): void {
                event(new ApplicationStatusChanged(
                    $application,
                    ApplicationStatus::Submitted,
                    ApplicationStatus::UnderReview,
                ));

                throw new \RuntimeException('Force rollback');
            });
        } catch (\RuntimeException) {
            //
        }

        $this->assertSame(0, AfterCommitProbeListener::$calls);
    }

    #[Test]
    public function application_status_changed_listener_does_not_create_duplicate_history_rows(): void
    {
        $application = $this->createApplicationRecord();

        DB::transaction(function () use ($application): void {
            event(new ApplicationStatusChanged(
                $application,
                ApplicationStatus::Submitted,
                ApplicationStatus::UnderReview,
            ));
        });

        $this->assertDatabaseCount('application_status_history', 0);
    }

    private function createApplicationRecord(): Application
    {
        $user = User::query()->create([
            'name' => 'Aday',
            'email' => 'events-candidate@example.com',
            'password' => 'secret',
            'role' => UserRole::Candidate,
        ]);

        $profile = CandidateProfile::query()->create([
            'user_id' => $user->id,
        ]);

        $job = Job::query()->create([
            'title' => 'Backend Developer',
            'slug' => 'backend-developer',
            'description' => 'Build APIs.',
            'employment_type' => EmploymentType::FullTime,
            'work_type' => WorkType::Remote,
            'status' => JobStatus::Published,
        ]);

        return Application::query()->create([
            'candidate_profile_id' => $profile->id,
            'job_id' => $job->id,
            'status' => ApplicationStatus::Submitted,
            'applied_at' => now(),
        ]);
    }
}

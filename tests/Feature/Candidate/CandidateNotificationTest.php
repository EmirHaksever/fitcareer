<?php

declare(strict_types=1);

namespace Tests\Feature\Candidate;

use App\Enums\ApplicationStatus;
use App\Enums\NotificationCategory;
use App\Events\ApplicationStatusChanged;
use App\Models\Application;
use App\Models\ApplicationStatusHistory;
use App\Models\Job;
use App\Models\Notification;
use App\Services\Notification\ApplicationStatusNotificationFactory;
use App\Services\Notification\InAppNotificationPayload;
use App\Services\Notification\NotificationDispatcherService;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Job\CreatesJobActors;
use Tests\TestCase;

class CandidateNotificationTest extends TestCase
{
    use CreatesJobActors;

    /**
     * @return array{0: \App\Models\User, 1: \App\Models\CandidateProfile, 2: string, 3: Job, 4: Application}
     */
    private function createApplicationForCandidate(): array
    {
        [$companyUser, $company] = $this->createCompanyActor();
        [$user, $profile, $token] = $this->createCandidateActor();

        $job = Job::factory()->published()->create([
            'company_id' => $company->id,
            'posted_by' => $companyUser->id,
            'title' => 'Backend Developer',
        ]);

        $application = Application::factory()->create([
            'candidate_profile_id' => $profile->id,
            'job_id' => $job->id,
            'status' => ApplicationStatus::Submitted,
        ]);

        ApplicationStatusHistory::query()->create([
            'application_id' => $application->id,
            'from_status' => null,
            'to_status' => ApplicationStatus::Submitted,
        ]);

        return [$user, $profile, $token, $job, $application];
    }

    #[Test]
    public function guest_cannot_access_candidate_notifications(): void
    {
        $this->getJson('/api/v1/candidate/notifications')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');

        $this->getJson('/api/v1/candidate/notifications/unread-count')
            ->assertUnauthorized();
    }

    #[Test]
    public function company_user_cannot_access_candidate_notifications(): void
    {
        [, , $token] = $this->createCompanyActor();

        $this->withToken($token)
            ->getJson('/api/v1/candidate/notifications')
            ->assertForbidden()
            ->assertJsonPath('message', 'Forbidden.');
    }

    #[Test]
    public function candidate_lists_only_own_notifications(): void
    {
        [$userA, , $tokenA] = $this->createCandidateActor();
        [$userB] = $this->createCandidateActor();

        $dispatcher = app(NotificationDispatcherService::class);

        $dispatcher->dispatch($userA, new InAppNotificationPayload(
            type: 'test',
            category: NotificationCategory::ApplicationUpdate,
            title: 'A bildirimi',
            body: 'A içeriği',
            dedupeKey: 'test:a:1',
        ));

        $dispatcher->dispatch($userB, new InAppNotificationPayload(
            type: 'test',
            category: NotificationCategory::ApplicationUpdate,
            title: 'B bildirimi',
            body: 'B içeriği',
            dedupeKey: 'test:b:1',
        ));

        $this->withToken($tokenA)
            ->getJson('/api/v1/candidate/notifications')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.title', 'A bildirimi')
            ->assertJsonPath('data.items.0.body', 'A içeriği')
            ->assertJsonPath('data.items.0.category', NotificationCategory::ApplicationUpdate->value);
    }

    #[Test]
    public function unread_count_returns_only_unread_notifications(): void
    {
        [$user, , $token] = $this->createCandidateActor();
        $dispatcher = app(NotificationDispatcherService::class);

        $first = $dispatcher->dispatch($user, new InAppNotificationPayload(
            type: 'test',
            category: NotificationCategory::ApplicationUpdate,
            title: 'Okunmadı',
            body: '1',
            dedupeKey: 'unread:1',
        ));

        $dispatcher->dispatch($user, new InAppNotificationPayload(
            type: 'test',
            category: NotificationCategory::ApplicationUpdate,
            title: 'Okundu',
            body: '2',
            dedupeKey: 'unread:2',
        ));

        Notification::query()->whereKey($first?->id)->update(['read_at' => now()]);

        $this->withToken($token)
            ->getJson('/api/v1/candidate/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 1);
    }

    #[Test]
    public function candidate_can_mark_single_notification_as_read(): void
    {
        [$user, , $token] = $this->createCandidateActor();
        $notification = app(NotificationDispatcherService::class)->dispatch($user, new InAppNotificationPayload(
            type: 'test',
            category: NotificationCategory::ApplicationUpdate,
            title: 'Tekil okundu',
            body: 'İçerik',
            dedupeKey: 'single:read:1',
        ));

        $this->withToken($token)
            ->patchJson('/api/v1/candidate/notifications/'.$notification?->id.'/read')
            ->assertOk()
            ->assertJsonPath('data.is_read', true)
            ->assertJsonPath('data.title', 'Tekil okundu');

        $this->assertNotNull($notification?->fresh()?->read_at);
    }

    #[Test]
    public function candidate_cannot_mark_another_users_notification_as_read(): void
    {
        [$userA, , $tokenA] = $this->createCandidateActor();
        [$userB] = $this->createCandidateActor();

        $foreign = app(NotificationDispatcherService::class)->dispatch($userB, new InAppNotificationPayload(
            type: 'test',
            category: NotificationCategory::ApplicationUpdate,
            title: 'Yabancı',
            body: 'İçerik',
            dedupeKey: 'foreign:1',
        ));

        $this->withToken($tokenA)
            ->patchJson('/api/v1/candidate/notifications/'.$foreign?->id.'/read')
            ->assertNotFound();
    }

    #[Test]
    public function candidate_can_mark_all_notifications_as_read(): void
    {
        [$user, , $token] = $this->createCandidateActor();
        $dispatcher = app(NotificationDispatcherService::class);

        $dispatcher->dispatch($user, new InAppNotificationPayload(
            type: 'test',
            category: NotificationCategory::ApplicationUpdate,
            title: '1',
            body: '1',
            dedupeKey: 'all:1',
        ));

        $dispatcher->dispatch($user, new InAppNotificationPayload(
            type: 'test',
            category: NotificationCategory::ApplicationUpdate,
            title: '2',
            body: '2',
            dedupeKey: 'all:2',
        ));

        $this->withToken($token)
            ->postJson('/api/v1/candidate/notifications/mark-all-read')
            ->assertOk()
            ->assertJsonPath('data.updated_count', 2);

        $this->withToken($token)
            ->getJson('/api/v1/candidate/notifications/unread-count')
            ->assertJsonPath('data.unread_count', 0);
    }

    #[Test]
    public function duplicate_application_status_notifications_are_not_created(): void
    {
        [$user, $profile, , , $application] = $this->createApplicationForCandidate();

        $application->setRelation('candidateProfile', $profile);

        $factory = app(ApplicationStatusNotificationFactory::class);
        $dispatcher = app(NotificationDispatcherService::class);

        $event = new ApplicationStatusChanged(
            $application,
            ApplicationStatus::Submitted,
            ApplicationStatus::UnderReview,
        );

        $payload = $factory->fromStatusChange($event);
        $this->assertNotNull($payload);

        $first = $dispatcher->dispatch($user, $payload);
        $second = $dispatcher->dispatch($user, $payload);

        $this->assertNotNull($first);
        $this->assertNull($second);
        $this->assertSame(1, $user->notifications()->count());
    }

    #[Test]
    public function application_status_change_generates_turkish_notification_for_candidate(): void
    {
        [$companyUser, $company, $companyToken] = $this->createCompanyActor();
        [$candidateUser, $profile, $candidateToken] = $this->createCandidateActor();

        $job = Job::factory()->published()->create([
            'company_id' => $company->id,
            'posted_by' => $companyUser->id,
            'title' => 'Senior PHP Developer',
        ]);

        $application = Application::factory()->create([
            'candidate_profile_id' => $profile->id,
            'job_id' => $job->id,
            'status' => ApplicationStatus::Submitted,
        ]);

        ApplicationStatusHistory::query()->create([
            'application_id' => $application->id,
            'from_status' => null,
            'to_status' => ApplicationStatus::Submitted,
        ]);

        $this->withToken($companyToken)
            ->patchJson('/api/v1/company/applications/'.$application->id.'/status', [
                'status' => ApplicationStatus::UnderReview->value,
            ])
            ->assertOk();

        $this->actingAs($candidateUser, 'sanctum')
            ->getJson('/api/v1/candidate/notifications')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.category', NotificationCategory::ApplicationUpdate->value)
            ->assertJsonPath('data.items.0.title', 'Başvuru durumu güncellendi')
            ->assertJsonPath('data.items.0.body', 'Senior PHP Developer ilanı için: Başvurunuz inceleniyor.')
            ->assertJsonPath('data.items.0.action_path', '/applications/'.$application->id)
            ->assertJsonPath('data.items.0.is_read', false);
    }

    #[Test]
    public function factory_does_not_build_notification_when_status_is_unchanged(): void
    {
        [, , , , $application] = $this->createApplicationForCandidate();

        $payload = app(ApplicationStatusNotificationFactory::class)->fromStatusChange(
            new ApplicationStatusChanged(
                $application,
                ApplicationStatus::Submitted,
                ApplicationStatus::Submitted,
            ),
        );

        $this->assertNull($payload);
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Notification;

use App\Enums\ApplicationStatus;
use App\Enums\NotificationCategory;
use App\Events\ApplicationStatusChanged;

final class ApplicationStatusNotificationFactory
{
    private const TYPE = 'App\\Notifications\\Candidate\\ApplicationStatusUpdated';

    /** @var array<string, string> */
    private const STATUS_MESSAGES = [
        'under_review' => 'Başvurunuz inceleniyor.',
        'shortlisted' => 'Başvurunuz ön elemeye alındı.',
        'interview' => 'Mülakat aşamasına geçtiniz.',
        'offered' => 'Size teklif yapıldı.',
        'rejected' => 'Başvurunuz değerlendirme sonucu olumsuz.',
        'withdrawn' => 'Başvurunuz geri çekildi.',
        'submitted' => 'Başvurunuz alındı.',
    ];

    public function fromStatusChange(ApplicationStatusChanged $event): ?InAppNotificationPayload
    {
        if ($event->fromStatus === $event->toStatus) {
            return null;
        }

        $application = $event->application->loadMissing('job.company');

        $jobTitle = $application->job?->title ?? 'İlan';
        $statusKey = $event->toStatus->value;
        $statusMessage = self::STATUS_MESSAGES[$statusKey] ?? 'Başvuru durumunuz güncellendi.';

        return new InAppNotificationPayload(
            type: self::TYPE,
            category: NotificationCategory::ApplicationUpdate,
            title: 'Başvuru durumu güncellendi',
            body: sprintf('%s ilanı için: %s', $jobTitle, $statusMessage),
            dedupeKey: sprintf('application_update:%d:%s', $application->id, $statusKey),
            actionPath: sprintf('/applications/%d', $application->id),
        );
    }

    public static function statusMessage(ApplicationStatus $status): string
    {
        return self::STATUS_MESSAGES[$status->value] ?? 'Başvuru durumunuz güncellendi.';
    }
}

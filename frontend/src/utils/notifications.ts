import type { NotificationCategory } from '@/types/api';

const CATEGORY_LABELS: Record<NotificationCategory, string> = {
  application_update: 'Başvuru',
  job_match: 'İlan eşleşmesi',
  system: 'Sistem',
  promotion: 'Duyuru',
};

export function getNotificationCategoryLabel(category: NotificationCategory): string {
  return CATEGORY_LABELS[category] ?? 'Bildirim';
}

export function formatNotificationDate(value: string | null): string {
  if (!value) return '—';

  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;

  return date.toLocaleString('tr-TR', {
    day: 'numeric',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
  });
}

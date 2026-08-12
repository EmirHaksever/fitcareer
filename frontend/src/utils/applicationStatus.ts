import type { ApplicationStatus } from '@/types/application';

type BadgeTone = 'default' | 'success' | 'warning' | 'danger' | 'info';

export const APPLICATION_STATUS_LABELS: Record<ApplicationStatus, string> = {
  submitted: 'Başvuruldu',
  under_review: 'İnceleniyor',
  shortlisted: 'Ön Eleme',
  interview: 'Mülakat',
  offered: 'Teklif',
  rejected: 'Reddedildi',
  withdrawn: 'Geri Çekildi',
};

export const APPLICATION_STATUS_TONES: Record<ApplicationStatus, BadgeTone> = {
  submitted: 'info',
  under_review: 'warning',
  shortlisted: 'success',
  interview: 'info',
  offered: 'success',
  rejected: 'danger',
  withdrawn: 'default',
};

const KNOWN_ERROR_MESSAGES: Record<string, string> = {
  'You have already applied to this job.': 'Bu ilana zaten başvurdun.',
  'This job is not accepting applications.': 'Bu ilan artık başvuru kabul etmiyor.',
  'The selected job is invalid.': 'Seçilen ilan geçersiz.',
};

export function getApplicationStatusLabel(status: ApplicationStatus): string {
  return APPLICATION_STATUS_LABELS[status];
}

export function getApplicationStatusTone(status: ApplicationStatus): BadgeTone {
  return APPLICATION_STATUS_TONES[status];
}

export function translateApplicationError(message: string): string {
  return KNOWN_ERROR_MESSAGES[message] ?? message;
}

export function formatApplicationScore(score: number | null): string {
  if (score === null) {
    return 'Analiz ediliyor';
  }

  return `%${score}`;
}

export function formatApplicationDate(value: string | null): string {
  if (!value) return '—';

  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;

  return date.toLocaleDateString('tr-TR', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  });
}

export function formatApplicationDateTime(value: string | null): string {
  if (!value) return '—';

  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;

  return date.toLocaleString('tr-TR', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

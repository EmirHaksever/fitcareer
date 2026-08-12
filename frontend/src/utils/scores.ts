import type { TrustAnalysisStatus } from '@/types/api';

export type ScoreBand = 'danger' | 'warning' | 'good' | 'excellent' | 'neutral';

export function getTrustBand(score: number | null): ScoreBand {
  if (score === null) return 'neutral';
  if (score <= 39) return 'danger';
  if (score <= 69) return 'warning';
  if (score <= 84) return 'good';
  return 'excellent';
}

export function getFitBand(score: number | null): ScoreBand {
  if (score === null) return 'neutral';
  if (score <= 49) return 'danger';
  if (score <= 69) return 'warning';
  if (score <= 84) return 'good';
  return 'excellent';
}

export function isTrustPending(status: TrustAnalysisStatus): boolean {
  return status === 'pending' || status === 'analyzing';
}

export function isFitPending(status: string | null): boolean {
  return status === null || status === 'pending' || status === 'analyzing';
}

export const bandClasses: Record<ScoreBand, string> = {
  danger: 'text-danger',
  warning: 'text-warning',
  good: 'text-primary',
  excellent: 'text-secondary',
  neutral: 'text-ink-muted',
};

export const bandRingClasses: Record<ScoreBand, string> = {
  danger: 'stroke-danger',
  warning: 'stroke-warning',
  good: 'stroke-primary',
  excellent: 'stroke-secondary',
  neutral: 'stroke-surface',
};

const fitBandLabels: Record<ScoreBand, string> = {
  danger: 'Düşük',
  warning: 'Orta',
  good: 'Yüksek',
  excellent: 'Çok Yüksek',
  neutral: '—',
};

const trustBandLabels: Record<ScoreBand, string> = {
  danger: 'Düşük Güven',
  warning: 'Orta Güven',
  good: 'Güvenilir',
  excellent: 'Çok Güvenilir',
  neutral: '—',
};

export function getFitBandLabel(band: ScoreBand): string {
  return fitBandLabels[band];
}

export function getTrustBandLabel(band: ScoreBand): string {
  return trustBandLabels[band];
}

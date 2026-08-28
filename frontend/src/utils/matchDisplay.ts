import type { MatchAnalysisStatus } from '@/types/companyApplication';

export type MatchDisplayState = 'completed' | 'pending' | 'unavailable';

export interface MatchDisplay {
  state: MatchDisplayState;
  score: number | null;
  label: string | null;
  primary: string;
  secondary: string;
}

export function matchClassificationLabel(score: number): string {
  if (score >= 80) return 'Güçlü Eşleşme';
  if (score >= 60) return 'Uygun';
  if (score >= 40) return 'Kısmen Uygun';
  return 'Düşük Uyum';
}

export function resolveMatchDisplay(
  score: number | null | undefined,
  status: MatchAnalysisStatus | string | null | undefined,
): MatchDisplay {
  if (score !== null && score !== undefined) {
    const label = matchClassificationLabel(score);

    return {
      state: 'completed',
      score,
      label,
      primary: `${score}%`,
      secondary: label,
    };
  }

  if (status === 'pending') {
    return {
      state: 'pending',
      score: null,
      label: null,
      primary: 'Uyum Analizi',
      secondary: 'Analiz hazırlanıyor',
    };
  }

  return {
    state: 'unavailable',
    score: null,
    label: null,
    primary: 'Henüz hesaplanmadı',
    secondary: 'Uyum skoru henüz mevcut değil',
  };
}

export function formatMatchListPrimary(display: MatchDisplay): string {
  if (display.state === 'pending') {
    return 'Analiz ediliyor';
  }

  if (display.state === 'unavailable') {
    return 'Henüz hesaplanmadı';
  }

  return display.primary;
}

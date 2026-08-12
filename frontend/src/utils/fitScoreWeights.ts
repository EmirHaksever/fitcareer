import type { CompanyJobStatus } from '@/types/companyJob';
import type {
  FitScoreWeightKey,
  FitScoreWeights,
  FitScoreWeightSource,
} from '@/types/companyJobFitScoreSettings';
import { FIT_SCORE_WEIGHT_KEYS } from '@/types/companyJobFitScoreSettings';

export function sumFitScoreWeights(weights: FitScoreWeights): number {
  return FIT_SCORE_WEIGHT_KEYS.reduce((total, key) => total + weights[key], 0);
}

export function areFitScoreWeightsEqual(a: FitScoreWeights, b: FitScoreWeights): boolean {
  return FIT_SCORE_WEIGHT_KEYS.every((key) => a[key] === b[key]);
}

export function parseWeightInput(raw: string): number | null {
  const trimmed = raw.trim();

  if (trimmed === '') {
    return 0;
  }

  if (!/^\d+$/.test(trimmed)) {
    return null;
  }

  const value = Number(trimmed);

  if (value < 0 || value > 100) {
    return null;
  }

  return value;
}

export function isWeightTotalValid(total: number): boolean {
  return total === 100;
}

export function canSaveFitScoreWeights(
  weights: FitScoreWeights,
  isDirty: boolean,
  readOnly: boolean,
): boolean {
  if (readOnly || !isDirty) {
    return false;
  }

  return isWeightTotalValid(sumFitScoreWeights(weights));
}

export function isFitScoreSettingsEditable(status: CompanyJobStatus): boolean {
  return status === 'draft' || status === 'pending_review';
}

export function getSourceBadge(source: FitScoreWeightSource): {
  label: string;
  tone: 'default' | 'info';
} {
  if (source === 'custom') {
    return { label: 'Özel ayarlar', tone: 'info' };
  }

  return { label: 'Varsayılan ayarlar', tone: 'default' };
}

export function mapFitScoreWeightValidationErrors(errors: Record<string, string[]>): string {
  const messages = Object.values(errors)
    .flat()
    .filter((message) => message.trim() !== '');

  if (messages.length === 0) {
    return 'Fit Score ayarları kaydedilemedi.';
  }

  return messages.join(' ');
}

export function updateWeightValue(
  weights: FitScoreWeights,
  key: FitScoreWeightKey,
  value: number,
): FitScoreWeights {
  return {
    ...weights,
    [key]: value,
  };
}

export function getTotalWeightLabel(total: number): string {
  return `Toplam: ${total} / 100`;
}

export function getTotalWeightTone(total: number): 'success' | 'danger' {
  return total === 100 ? 'success' : 'danger';
}

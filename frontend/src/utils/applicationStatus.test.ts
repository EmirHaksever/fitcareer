import { describe, expect, it } from 'vitest';
import {
  APPLICATION_STATUS_LABELS,
  APPLICATION_STATUS_TONES,
  formatApplicationScore,
  getApplicationStatusLabel,
  getApplicationStatusTone,
  translateApplicationError,
} from '@/utils/applicationStatus';

describe('applicationStatus utils', () => {
  it('maps all application statuses to Turkish labels', () => {
    expect(getApplicationStatusLabel('submitted')).toBe('Başvuruldu');
    expect(getApplicationStatusLabel('under_review')).toBe('İnceleniyor');
    expect(getApplicationStatusLabel('shortlisted')).toBe('Ön Eleme');
    expect(getApplicationStatusLabel('interview')).toBe('Mülakat');
    expect(getApplicationStatusLabel('offered')).toBe('Teklif');
    expect(getApplicationStatusLabel('rejected')).toBe('Reddedildi');
    expect(getApplicationStatusLabel('withdrawn')).toBe('Geri Çekildi');

    expect(Object.keys(APPLICATION_STATUS_LABELS)).toHaveLength(7);
  });

  it('maps statuses to badge tones', () => {
    expect(getApplicationStatusTone('submitted')).toBe('info');
    expect(getApplicationStatusTone('under_review')).toBe('warning');
    expect(getApplicationStatusTone('shortlisted')).toBe('success');
    expect(getApplicationStatusTone('rejected')).toBe('danger');
    expect(getApplicationStatusTone('withdrawn')).toBe('default');

    expect(Object.keys(APPLICATION_STATUS_TONES)).toHaveLength(7);
  });

  it('formats snapshot scores', () => {
    expect(formatApplicationScore(76)).toBe('%76');
    expect(formatApplicationScore(null)).toBe('Analiz ediliyor');
  });

  it('translates known backend application errors', () => {
    expect(translateApplicationError('You have already applied to this job.')).toBe(
      'Bu ilana zaten başvurdun.',
    );
    expect(translateApplicationError('Unknown error')).toBe('Unknown error');
  });
});

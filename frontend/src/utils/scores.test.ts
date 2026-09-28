import { describe, expect, it } from 'vitest';
import { getFitBand, getTrustBand, getTrustBandLabel, isFitPending, isTrustPending } from '@/utils/scores';

describe('score utils', () => {
  it('maps trust score bands with the backend label thresholds (75/50/30)', () => {
    expect(getTrustBand(90)).toBe('excellent');
    expect(getTrustBand(75)).toBe('excellent');
    expect(getTrustBand(74)).toBe('good');
    expect(getTrustBand(50)).toBe('good');
    expect(getTrustBand(49)).toBe('warning');
    expect(getTrustBand(30)).toBe('warning');
    expect(getTrustBand(29)).toBe('danger');
    expect(getTrustBand(null)).toBe('neutral');
  });

  it('names trust bands like the backend labels', () => {
    expect(getTrustBandLabel('excellent')).toBe('Güvenilir');
    expect(getTrustBandLabel('good')).toBe('Orta Güven');
    expect(getTrustBandLabel('warning')).toBe('Şüpheli');
    expect(getTrustBandLabel('danger')).toBe('Düşük Güven');
  });

  it('maps fit score bands', () => {
    expect(getFitBand(88)).toBe('excellent');
    expect(getFitBand(45)).toBe('danger');
  });

  it('detects pending trust analysis', () => {
    expect(isTrustPending('pending')).toBe(true);
    expect(isTrustPending('completed')).toBe(false);
  });

  it('treats missing fit analysis as pending', () => {
    expect(isFitPending(null)).toBe(true);
    expect(isFitPending('pending')).toBe(true);
    expect(isFitPending('completed')).toBe(false);
  });
});

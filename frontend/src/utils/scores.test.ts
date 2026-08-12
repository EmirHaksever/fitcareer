import { describe, expect, it } from 'vitest';
import { getFitBand, getTrustBand, isFitPending, isTrustPending } from '@/utils/scores';

describe('score utils', () => {
  it('maps trust score bands', () => {
    expect(getTrustBand(90)).toBe('excellent');
    expect(getTrustBand(55)).toBe('warning');
    expect(getTrustBand(null)).toBe('neutral');
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

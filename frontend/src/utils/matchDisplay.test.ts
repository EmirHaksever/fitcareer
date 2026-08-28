import { describe, expect, it } from 'vitest';
import {
  formatMatchListPrimary,
  matchClassificationLabel,
  resolveMatchDisplay,
} from '@/utils/matchDisplay';

describe('matchDisplay', () => {
  it('classifies completed scores with visible labels', () => {
    expect(matchClassificationLabel(88)).toBe('Güçlü Eşleşme');
    expect(matchClassificationLabel(80)).toBe('Güçlü Eşleşme');
    expect(matchClassificationLabel(79)).toBe('Uygun');
    expect(matchClassificationLabel(60)).toBe('Uygun');
    expect(matchClassificationLabel(40)).toBe('Kısmen Uygun');
    expect(matchClassificationLabel(0)).toBe('Düşük Uyum');
  });

  it('shows a completed percentage and never invents 0% for null', () => {
    const completed = resolveMatchDisplay(88, 'completed');
    expect(completed.state).toBe('completed');
    expect(completed.primary).toBe('88%');
    expect(completed.secondary).toBe('Güçlü Eşleşme');

    const pending = resolveMatchDisplay(null, 'pending');
    expect(pending.state).toBe('pending');
    expect(pending.score).toBeNull();
    expect(pending.primary).toBe('Uyum Analizi');
    expect(formatMatchListPrimary(pending)).toBe('Analiz ediliyor');
    expect(formatMatchListPrimary(pending)).not.toContain('%');

    const unavailable = resolveMatchDisplay(null, null);
    expect(unavailable.state).toBe('unavailable');
    expect(unavailable.score).toBeNull();
    expect(formatMatchListPrimary(unavailable)).toBe('Henüz hesaplanmadı');
    expect(formatMatchListPrimary(unavailable)).not.toBe('0%');
  });

  it('treats a snapshot score as completed even if analysis status is missing', () => {
    const display = resolveMatchDisplay(72, null);
    expect(display.state).toBe('completed');
    expect(display.primary).toBe('72%');
  });
});

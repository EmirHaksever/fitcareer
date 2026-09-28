import { describe, expect, it } from 'vitest';
import { colorClassForTrustBucket } from '@/utils/dashboardStats';
import { formatTrustLabel } from '@/utils/format';

describe('trust labels', () => {
  it('names the middle score band instead of calling it unrated', () => {
    expect(formatTrustLabel('moderate')).toBe('Orta Güven');
    expect(formatTrustLabel('unrated')).toBe('Değerlendirilmedi');
  });

  it('gives every trust bucket its own chart color', () => {
    const ids = ['verified', 'moderate', 'suspicious', 'low_trust', 'unrated', 'pending_analysis'];
    const colors = ids.map(colorClassForTrustBucket);

    expect(new Set(colors).size).toBe(ids.length);
  });
});

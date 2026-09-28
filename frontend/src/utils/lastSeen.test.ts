import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { formatLastSeen } from '@/utils/format';

describe('formatLastSeen', () => {
  beforeEach(() => {
    vi.useFakeTimers();
    vi.setSystemTime(new Date('2026-09-28T12:00:00Z'));
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  it('says when the listing was last confirmed open at its source', () => {
    expect(formatLastSeen('2026-09-28T11:55:00Z')).toBe('Son kontrol: az önce · kaynağında hâlâ açık');
    expect(formatLastSeen('2026-09-28T09:00:00Z')).toBe('Son kontrol: 3 saat önce · kaynağında hâlâ açık');
    expect(formatLastSeen('2026-09-26T12:00:00Z')).toBe('Son kontrol: 2 gün önce · kaynağında hâlâ açık');
  });

  it('returns nothing for jobs that are not tracked at an external source', () => {
    expect(formatLastSeen(null)).toBeNull();
    expect(formatLastSeen('not-a-date')).toBeNull();
  });
});

import { describe, expect, it } from 'vitest';
import { formatNotificationDate, getNotificationCategoryLabel } from '@/utils/notifications';

describe('notifications utils', () => {
  it('returns Turkish category labels', () => {
    expect(getNotificationCategoryLabel('application_update')).toBe('Başvuru');
    expect(getNotificationCategoryLabel('system')).toBe('Sistem');
  });

  it('formats notification dates for Turkish locale', () => {
    const formatted = formatNotificationDate('2026-08-13T10:30:00+03:00');
    expect(formatted).toMatch(/13/);
    expect(formatted).not.toBe('—');
  });

  it('returns dash for invalid dates', () => {
    expect(formatNotificationDate(null)).toBe('—');
  });
});

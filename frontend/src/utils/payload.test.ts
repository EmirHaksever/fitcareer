import { describe, expect, it } from 'vitest';
import { sanitizePayload } from '@/utils/payload';

describe('sanitizePayload', () => {
  it('converts empty strings to null', () => {
    expect(sanitizePayload({ name: 'Test', url: '' })).toEqual({ name: 'Test', url: null });
  });

  it('preserves non-empty values', () => {
    expect(sanitizePayload({ url: 'https://example.com' })).toEqual({ url: 'https://example.com' });
  });
});

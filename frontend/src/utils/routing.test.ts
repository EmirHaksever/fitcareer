import { describe, expect, it } from 'vitest';
import { getDefaultRouteForRole } from '@/utils/routing';

describe('getDefaultRouteForRole', () => {
  it('routes company users to company dashboard', () => {
    expect(getDefaultRouteForRole('company')).toBe('/company/dashboard');
  });

  it('routes candidate users to candidate dashboard', () => {
    expect(getDefaultRouteForRole('candidate')).toBe('/dashboard');
  });

  it('routes admin users to candidate dashboard fallback', () => {
    expect(getDefaultRouteForRole('admin')).toBe('/dashboard');
  });
});

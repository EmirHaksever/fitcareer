import { describe, expect, it } from 'vitest';
import {
  COMPANY_POST_LOGOUT_PATH,
  COMPANY_SETTINGS_PATH,
  companyPublicJobPath,
} from '@/utils/companyPortal';

describe('company portal routes', () => {
  it('sends company users to settings and login after logout', () => {
    expect(COMPANY_SETTINGS_PATH).toBe('/company/settings');
    expect(COMPANY_POST_LOGOUT_PATH).toBe('/login');
  });

  it('opens only published jobs on the public listing route', () => {
    expect(companyPublicJobPath('published', 'junior-backend-developer')).toBe(
      '/jobs/junior-backend-developer',
    );
    expect(companyPublicJobPath('draft', 'junior-backend-developer')).toBeNull();
    expect(companyPublicJobPath('closed', 'junior-backend-developer')).toBeNull();
  });
});

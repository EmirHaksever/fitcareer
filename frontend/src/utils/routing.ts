import type { UserRole } from '@/types/api';

export function getDefaultRouteForRole(role: UserRole): string {
  if (role === 'company') {
    return '/company/dashboard';
  }

  return '/dashboard';
}

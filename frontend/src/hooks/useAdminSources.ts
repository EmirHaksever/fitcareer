import { useQuery } from '@tanstack/react-query';
import { adminSourcesApi } from '@/api/adminSources';

export const ADMIN_SOURCE_HEALTH_KEY = ['admin', 'job-sources', 'health'] as const;

export function useAdminSourceHealth() {
  return useQuery({
    queryKey: ADMIN_SOURCE_HEALTH_KEY,
    queryFn: () => adminSourcesApi.listHealth(),
    staleTime: 60_000,
  });
}

import { useQuery } from '@tanstack/react-query';
import { dashboardApi } from '@/api/dashboard';
import { CANDIDATE_DASHBOARD_KEY } from '@/hooks/invalidateFitQueries';

export function useDashboardStats() {
  return useQuery({
    queryKey: CANDIDATE_DASHBOARD_KEY,
    queryFn: () => dashboardApi.getCandidateDashboard(),
    staleTime: 60_000,
  });
}

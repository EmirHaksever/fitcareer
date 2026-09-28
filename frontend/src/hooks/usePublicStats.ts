import { useQuery } from '@tanstack/react-query';
import { jobsApi } from '@/api/jobs';

export function usePublicStats() {
  return useQuery({
    queryKey: ['public-stats'],
    queryFn: () => jobsApi.stats(),
    staleTime: 5 * 60 * 1000,
  });
}

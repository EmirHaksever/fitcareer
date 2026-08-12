import { useQuery } from '@tanstack/react-query';
import { jobsApi } from '@/api/jobs';

export function useJob(slug: string | undefined) {
  return useQuery({
    queryKey: ['jobs', 'detail', slug],
    queryFn: () => jobsApi.show(slug!),
    enabled: Boolean(slug),
  });
}

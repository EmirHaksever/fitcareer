import { useQuery } from '@tanstack/react-query';
import { jobsApi } from '@/api/jobs';
import type { JobListItem, JobSearchParams } from '@/types/api';

function hasPendingFitScore(jobs: JobListItem[] | undefined): boolean {
  return (jobs ?? []).some(
    (job) => job.fit_analysis_status === 'pending' || job.fit_analysis_status === 'analyzing',
  );
}

export function useJobs(params: JobSearchParams = {}) {
  return useQuery({
    queryKey: ['jobs', params],
    queryFn: () => jobsApi.search(params),
    refetchInterval: (query) => (hasPendingFitScore(query.state.data?.items) ? 5000 : false),
  });
}

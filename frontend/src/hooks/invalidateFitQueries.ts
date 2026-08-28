import type { QueryClient } from '@tanstack/react-query';

export const CANDIDATE_DASHBOARD_KEY = ['candidate-dashboard'] as const;
export const JOBS_QUERY_PREFIX = ['jobs'] as const;
export const SAVED_JOBS_QUERY_PREFIX = ['saved-jobs'] as const;

/** Invalidate React Query caches that embed candidate fit scores. */
export function invalidateFitRelatedQueries(queryClient: QueryClient): void {
  void queryClient.invalidateQueries({ queryKey: CANDIDATE_DASHBOARD_KEY });
  void queryClient.invalidateQueries({ queryKey: JOBS_QUERY_PREFIX });
  void queryClient.invalidateQueries({ queryKey: SAVED_JOBS_QUERY_PREFIX });
}

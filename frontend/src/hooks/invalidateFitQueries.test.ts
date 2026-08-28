import { describe, expect, it, vi } from 'vitest';
import {
  CANDIDATE_DASHBOARD_KEY,
  invalidateFitRelatedQueries,
  JOBS_QUERY_PREFIX,
  SAVED_JOBS_QUERY_PREFIX,
} from '@/hooks/invalidateFitQueries';

describe('invalidateFitRelatedQueries', () => {
  it('invalidates dashboard, jobs, and saved-jobs query prefixes', () => {
    const queryClient = {
      invalidateQueries: vi.fn(),
    };

    invalidateFitRelatedQueries(queryClient as never);

    expect(queryClient.invalidateQueries).toHaveBeenCalledTimes(3);
    expect(queryClient.invalidateQueries).toHaveBeenCalledWith({ queryKey: CANDIDATE_DASHBOARD_KEY });
    expect(queryClient.invalidateQueries).toHaveBeenCalledWith({ queryKey: JOBS_QUERY_PREFIX });
    expect(queryClient.invalidateQueries).toHaveBeenCalledWith({ queryKey: SAVED_JOBS_QUERY_PREFIX });
  });
});

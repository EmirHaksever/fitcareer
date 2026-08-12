import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { savedJobsApi } from '@/api/savedJobs';
import { useAuth } from '@/hooks/useAuth';

export function useSavedJobIds() {
  const { user } = useAuth();
  const enabled = user?.role === 'candidate';

  return useQuery({
    queryKey: ['saved-jobs', 'ids'],
    queryFn: () => savedJobsApi.listIds(),
    enabled,
  });
}

export function useToggleSavedJob() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async ({ jobId, saved }: { jobId: number; saved: boolean }) => {
      if (saved) {
        await savedJobsApi.remove(jobId);
        return;
      }

      await savedJobsApi.save(jobId);
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['saved-jobs'] });
    },
  });
}

export function useSavedJobs(page = 1) {
  const { user } = useAuth();

  return useQuery({
    queryKey: ['saved-jobs', 'list', page],
    queryFn: () => savedJobsApi.list(page),
    enabled: user?.role === 'candidate',
  });
}

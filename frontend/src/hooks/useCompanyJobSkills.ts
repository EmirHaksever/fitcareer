import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { companyJobSkillsApi } from '@/api/companyJobSkills';
import { COMPANY_JOBS_KEY } from '@/hooks/useCompanyJobs';
import type { SyncJobSkillsPayload } from '@/types/companyJob';

export const companyJobSkillsKey = (jobId: number) =>
  [...COMPANY_JOBS_KEY, 'skills', jobId] as const;

export function useCompanyJobSkills(jobId: number | undefined) {
  return useQuery({
    queryKey: companyJobSkillsKey(jobId!),
    queryFn: () => companyJobSkillsApi.list(jobId!),
    enabled: Boolean(jobId),
  });
}

export function useSyncCompanyJobSkills() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ jobId, payload }: { jobId: number; payload: SyncJobSkillsPayload }) =>
      companyJobSkillsApi.sync(jobId, payload),
    onSuccess: (_data, variables) => {
      void queryClient.invalidateQueries({ queryKey: companyJobSkillsKey(variables.jobId) });
      void queryClient.invalidateQueries({
        queryKey: [...COMPANY_JOBS_KEY, 'detail', variables.jobId],
      });
    },
  });
}

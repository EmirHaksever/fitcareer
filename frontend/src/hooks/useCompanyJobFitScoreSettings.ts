import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { companyJobFitScoreSettingsApi } from '@/api/companyJobFitScoreSettings';
import { COMPANY_JOBS_KEY } from '@/hooks/useCompanyJobs';
import type { UpdateCompanyJobFitScoreSettingsPayload } from '@/types/companyJobFitScoreSettings';

export const companyJobFitScoreSettingsKey = (jobId: number) =>
  [...COMPANY_JOBS_KEY, 'fit-score-settings', jobId] as const;

export function useCompanyJobFitScoreSettings(jobId: number | undefined) {
  return useQuery({
    queryKey: companyJobFitScoreSettingsKey(jobId!),
    queryFn: () => companyJobFitScoreSettingsApi.get(jobId!),
    enabled: Boolean(jobId),
  });
}

export function useUpdateCompanyJobFitScoreSettings() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({
      jobId,
      payload,
    }: {
      jobId: number;
      payload: UpdateCompanyJobFitScoreSettingsPayload;
    }) => companyJobFitScoreSettingsApi.update(jobId, payload),
    onSuccess: (_data, variables) => {
      void queryClient.invalidateQueries({
        queryKey: companyJobFitScoreSettingsKey(variables.jobId),
      });
      void queryClient.invalidateQueries({
        queryKey: [...COMPANY_JOBS_KEY, 'detail', variables.jobId],
      });
    },
  });
}

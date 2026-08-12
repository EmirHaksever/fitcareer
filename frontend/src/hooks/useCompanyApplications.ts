import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { companyApplicationsApi } from '@/api/companyApplications';
import { companyJobsApi } from '@/api/companyJobs';
import { COMPANY_JOBS_KEY } from '@/hooks/useCompanyJobs';
import type {
  CompanyApplicationListParams,
  UpdateCompanyApplicationStatusPayload,
} from '@/types/companyApplication';

export const COMPANY_APPLICATIONS_KEY = ['company', 'applications'] as const;

export function useCompanyApplications(params: CompanyApplicationListParams = {}) {
  return useQuery({
    queryKey: [...COMPANY_APPLICATIONS_KEY, params],
    queryFn: () => companyApplicationsApi.listCompanyApplications(params),
  });
}

export function useCompanyApplication(id: number | undefined) {
  return useQuery({
    queryKey: [...COMPANY_APPLICATIONS_KEY, 'detail', id],
    queryFn: () => companyApplicationsApi.getCompanyApplication(id!),
    enabled: Boolean(id),
  });
}

export function useCompanyJobsForFilter() {
  return useQuery({
    queryKey: [...COMPANY_JOBS_KEY, 'filter'],
    queryFn: () => companyJobsApi.list({ page: 1, per_page: 50 }),
  });
}

export function useUpdateCompanyApplicationStatus() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({
      id,
      payload,
    }: {
      id: number;
      payload: UpdateCompanyApplicationStatusPayload;
    }) => companyApplicationsApi.updateCompanyApplicationStatus(id, payload),
    onSuccess: (_data, variables) => {
      void queryClient.invalidateQueries({ queryKey: COMPANY_APPLICATIONS_KEY });
      void queryClient.invalidateQueries({
        queryKey: [...COMPANY_APPLICATIONS_KEY, 'detail', variables.id],
      });
    },
  });
}

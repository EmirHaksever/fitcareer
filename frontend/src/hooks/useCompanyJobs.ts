import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { companyJobsApi } from '@/api/companyJobs';
import type {
  CompanyJobListParams,
  CreateCompanyJobPayload,
  UpdateCompanyJobPayload,
} from '@/types/companyJob';

export const COMPANY_JOBS_KEY = ['company', 'jobs'] as const;

export function useCompanyJobs(params: CompanyJobListParams = {}) {
  return useQuery({
    queryKey: [...COMPANY_JOBS_KEY, params],
    queryFn: () => companyJobsApi.list(params),
  });
}

export function useCompanyJob(id: number | undefined) {
  return useQuery({
    queryKey: [...COMPANY_JOBS_KEY, 'detail', id],
    queryFn: () => companyJobsApi.get(id!),
    enabled: Boolean(id),
  });
}

export function useCreateCompanyJob() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload: CreateCompanyJobPayload) => companyJobsApi.create(payload),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: COMPANY_JOBS_KEY });
    },
  });
}

export function useUpdateCompanyJob() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: UpdateCompanyJobPayload }) =>
      companyJobsApi.update(id, payload),
    onSuccess: (_data, variables) => {
      void queryClient.invalidateQueries({ queryKey: COMPANY_JOBS_KEY });
      void queryClient.invalidateQueries({
        queryKey: [...COMPANY_JOBS_KEY, 'detail', variables.id],
      });
    },
  });
}

export function usePublishCompanyJob() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id: number) => companyJobsApi.publish(id),
    onSuccess: (_data, id) => {
      void queryClient.invalidateQueries({ queryKey: COMPANY_JOBS_KEY });
      void queryClient.invalidateQueries({ queryKey: [...COMPANY_JOBS_KEY, 'detail', id] });
      void queryClient.invalidateQueries({ queryKey: ['company', 'applications'] });
    },
  });
}

export function useUnpublishCompanyJob() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id: number) => companyJobsApi.unpublish(id),
    onSuccess: (_data, id) => {
      void queryClient.invalidateQueries({ queryKey: COMPANY_JOBS_KEY });
      void queryClient.invalidateQueries({ queryKey: [...COMPANY_JOBS_KEY, 'detail', id] });
    },
  });
}

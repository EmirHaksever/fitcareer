import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { applicationsApi } from '@/api/applications';
import type { ApplicationListParams, CreateApplicationPayload } from '@/types/application';

export const APPLICATIONS_KEY = ['applications'] as const;

export function useApplications(params: ApplicationListParams = {}) {
  return useQuery({
    queryKey: [...APPLICATIONS_KEY, params],
    queryFn: () => applicationsApi.list(params),
  });
}

export function useApplication(id: number | undefined) {
  return useQuery({
    queryKey: [...APPLICATIONS_KEY, 'detail', id],
    queryFn: () => applicationsApi.get(id!),
    enabled: Boolean(id),
  });
}

export function useCreateApplication() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload: CreateApplicationPayload) => applicationsApi.create(payload),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: APPLICATIONS_KEY });
    },
  });
}

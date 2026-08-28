import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { companyProfileApi, type UpdateCompanyProfilePayload } from '@/api/companyProfile';

export const COMPANY_PROFILE_KEY = ['company', 'profile'] as const;

export function useCompanyProfile() {
  return useQuery({
    queryKey: COMPANY_PROFILE_KEY,
    queryFn: () => companyProfileApi.get(),
  });
}

export function useUpdateCompanyProfile() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload: UpdateCompanyProfilePayload) => companyProfileApi.update(payload),
    onSuccess: (profile) => {
      queryClient.setQueryData(COMPANY_PROFILE_KEY, profile);
    },
  });
}

export function useRequestCompanyVerification() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: () => companyProfileApi.requestVerification(),
    onSuccess: (profile) => {
      queryClient.setQueryData(COMPANY_PROFILE_KEY, profile);
    },
  });
}

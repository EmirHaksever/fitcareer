import { useQuery } from '@tanstack/react-query';
import { companyProfileApi } from '@/api/companyProfile';

export const COMPANY_PROFILE_KEY = ['company', 'profile'] as const;

export function useCompanyProfile() {
  return useQuery({
    queryKey: COMPANY_PROFILE_KEY,
    queryFn: () => companyProfileApi.get(),
  });
}

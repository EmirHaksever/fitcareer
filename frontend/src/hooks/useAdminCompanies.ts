import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { adminCompaniesApi } from '@/api/adminCompanies';
import type { CompanyVerificationAction } from '@/types/adminCompany';

export const ADMIN_PENDING_COMPANIES_KEY = ['admin', 'companies', 'pending'] as const;

export function usePendingCompanies() {
  return useQuery({
    queryKey: ADMIN_PENDING_COMPANIES_KEY,
    queryFn: () => adminCompaniesApi.listPending(),
  });
}

export function useVerifyCompany() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ companyId, action }: { companyId: number; action: CompanyVerificationAction }) =>
      adminCompaniesApi.verify(companyId, action),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ADMIN_PENDING_COMPANIES_KEY });
    },
  });
}

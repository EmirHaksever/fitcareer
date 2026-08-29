import { apiClient } from '@/api/client';
import type { ApiResponse } from '@/types/api';
import type { CompanyProfile } from '@/types/company';
import type {
  CompanyVerificationAction,
  PaginatedAdminCompanyList,
} from '@/types/adminCompany';

export const adminCompaniesApi = {
  async listPending(): Promise<PaginatedAdminCompanyList> {
    const { data } = await apiClient.get<ApiResponse<PaginatedAdminCompanyList>>(
      '/admin/companies/pending',
      { params: { per_page: 50 } },
    );
    return data.data;
  },

  async verify(companyId: number, action: CompanyVerificationAction): Promise<CompanyProfile> {
    const { data } = await apiClient.post<ApiResponse<CompanyProfile>>(
      `/admin/companies/${companyId}/verify`,
      { action },
    );
    return data.data;
  },
};

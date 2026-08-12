import { apiClient } from '@/api/client';
import type { ApiResponse } from '@/types/api';
import type { CompanyProfile } from '@/types/company';

export const companyProfileApi = {
  async get(): Promise<CompanyProfile> {
    const { data } = await apiClient.get<ApiResponse<CompanyProfile>>('/company/profile');
    return data.data;
  },
};

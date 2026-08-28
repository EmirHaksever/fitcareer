import { apiClient } from '@/api/client';
import type { ApiResponse } from '@/types/api';
import type { CompanyProfile } from '@/types/company';

export type UpdateCompanyProfilePayload = Partial<
  Pick<
    CompanyProfile,
    | 'name'
    | 'website'
    | 'industry'
    | 'company_size'
    | 'description'
    | 'city'
    | 'country'
    | 'contact_email'
    | 'contact_phone'
  >
>;

export const companyProfileApi = {
  async get(): Promise<CompanyProfile> {
    const { data } = await apiClient.get<ApiResponse<CompanyProfile>>('/company/profile');
    return data.data;
  },

  async update(payload: UpdateCompanyProfilePayload): Promise<CompanyProfile> {
    const { data } = await apiClient.put<ApiResponse<CompanyProfile>>('/company/profile', payload);
    return data.data;
  },

  async requestVerification(): Promise<CompanyProfile> {
    const { data } = await apiClient.post<ApiResponse<CompanyProfile>>(
      '/company/verification/request',
    );
    return data.data;
  },
};

import { apiClient } from '@/api/client';
import type { ApiResponse } from '@/types/api';
import type {
  CompanyApplication,
  CompanyApplicationListParams,
  PaginatedCompanyApplications,
  PaginatedCompanyJobs,
  UpdateCompanyApplicationStatusPayload,
} from '@/types/companyApplication';

export const companyApplicationsApi = {
  async listCompanyApplications(
    params: CompanyApplicationListParams = {},
  ): Promise<PaginatedCompanyApplications> {
    const { data } = await apiClient.get<ApiResponse<PaginatedCompanyApplications>>(
      '/company/applications',
      { params },
    );
    return data.data;
  },

  async getCompanyApplication(id: number): Promise<CompanyApplication> {
    const { data } = await apiClient.get<ApiResponse<CompanyApplication>>(
      `/company/applications/${id}`,
    );
    return data.data;
  },

  async updateCompanyApplicationStatus(
    id: number,
    payload: UpdateCompanyApplicationStatusPayload,
  ): Promise<CompanyApplication> {
    const { data } = await apiClient.patch<ApiResponse<CompanyApplication>>(
      `/company/applications/${id}/status`,
      payload,
    );
    return data.data;
  },

  async listCompanyJobs(page = 1, perPage = 50): Promise<PaginatedCompanyJobs> {
    const { data } = await apiClient.get<ApiResponse<PaginatedCompanyJobs>>('/company/jobs', {
      params: { page, per_page: perPage },
    });
    return data.data;
  },
};

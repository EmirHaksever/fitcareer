import { apiClient } from '@/api/client';
import type { ApiResponse } from '@/types/api';
import type {
  CompanyJob,
  CompanyJobListParams,
  CreateCompanyJobPayload,
  PaginatedCompanyJobList,
  UpdateCompanyJobPayload,
} from '@/types/companyJob';

export const companyJobsApi = {
  async list(params: CompanyJobListParams = {}): Promise<PaginatedCompanyJobList> {
    const { data } = await apiClient.get<ApiResponse<PaginatedCompanyJobList>>('/company/jobs', {
      params,
    });
    return data.data;
  },

  async get(id: number): Promise<CompanyJob> {
    const { data } = await apiClient.get<ApiResponse<CompanyJob>>(`/company/jobs/${id}`);
    return data.data;
  },

  async create(payload: CreateCompanyJobPayload): Promise<CompanyJob> {
    const { data } = await apiClient.post<ApiResponse<CompanyJob>>('/company/jobs', payload);
    return data.data;
  },

  async update(id: number, payload: UpdateCompanyJobPayload): Promise<CompanyJob> {
    const { data } = await apiClient.put<ApiResponse<CompanyJob>>(`/company/jobs/${id}`, payload);
    return data.data;
  },

  async publish(id: number): Promise<CompanyJob> {
    const { data } = await apiClient.post<ApiResponse<CompanyJob>>(`/company/jobs/${id}/publish`);
    return data.data;
  },
};

import { apiClient } from '@/api/client';
import type { ApiResponse } from '@/types/api';
import type {
  Application,
  ApplicationListParams,
  CreateApplicationPayload,
  PaginatedApplications,
} from '@/types/application';

export const applicationsApi = {
  async list(params: ApplicationListParams = {}): Promise<PaginatedApplications> {
    const { data } = await apiClient.get<ApiResponse<PaginatedApplications>>('/candidate/applications', {
      params,
    });
    return data.data;
  },

  async create(payload: CreateApplicationPayload): Promise<Application> {
    const { data } = await apiClient.post<ApiResponse<Application>>('/candidate/applications', payload);
    return data.data;
  },

  async get(id: number): Promise<Application> {
    const { data } = await apiClient.get<ApiResponse<Application>>(`/candidate/applications/${id}`);
    return data.data;
  },
};

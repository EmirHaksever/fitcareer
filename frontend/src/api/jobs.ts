import { apiClient } from '@/api/client';
import type { ApiResponse, JobDetail, JobSearchParams, PaginatedJobs, PublicStats } from '@/types/api';

export const jobsApi = {
  async search(params: JobSearchParams = {}): Promise<PaginatedJobs> {
    const { data } = await apiClient.get<ApiResponse<PaginatedJobs>>('/jobs', { params });
    return data.data;
  },

  async show(slug: string): Promise<JobDetail> {
    const { data } = await apiClient.get<ApiResponse<JobDetail>>(`/jobs/${slug}`);
    return data.data;
  },

  async stats(): Promise<PublicStats> {
    const { data } = await apiClient.get<ApiResponse<PublicStats>>('/stats');
    return data.data;
  },
};

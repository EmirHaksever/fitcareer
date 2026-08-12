import { apiClient } from '@/api/client';
import type { ApiResponse, JobListItem, PaginatedJobs } from '@/types/api';

export const savedJobsApi = {
  async list(page = 1, perPage = 15): Promise<PaginatedJobs> {
    const { data } = await apiClient.get<ApiResponse<PaginatedJobs>>('/candidate/saved-jobs', {
      params: { page, per_page: perPage },
    });

    return data.data;
  },

  async listIds(): Promise<number[]> {
    const { data } = await apiClient.get<ApiResponse<{ job_ids: number[] }>>('/candidate/saved-jobs/ids');

    return data.data.job_ids;
  },

  async save(jobId: number): Promise<void> {
    await apiClient.post(`/candidate/saved-jobs/${jobId}`);
  },

  async remove(jobId: number): Promise<void> {
    await apiClient.delete(`/candidate/saved-jobs/${jobId}`);
  },
};

export type SavedJobListItem = JobListItem;

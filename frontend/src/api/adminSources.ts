import { apiClient } from '@/api/client';
import type { ApiResponse } from '@/types/api';
import type { AdminSourceHealthResponse } from '@/types/adminSource';

export const adminSourcesApi = {
  async listHealth(): Promise<AdminSourceHealthResponse> {
    const { data } = await apiClient.get<ApiResponse<AdminSourceHealthResponse>>(
      '/admin/job-sources/health',
    );

    return data.data;
  },
};

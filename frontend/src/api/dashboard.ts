import { apiClient } from '@/api/client';
import type { ApiResponse, DashboardData } from '@/types/api';

export const dashboardApi = {
  async getCandidateDashboard(): Promise<DashboardData> {
    const { data } = await apiClient.get<ApiResponse<DashboardData>>('/candidate/dashboard');
    return data.data;
  },
};

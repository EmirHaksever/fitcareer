import { apiClient } from '@/api/client';
import type { ApiResponse } from '@/types/api';
import type {
  CompanyJobFitScoreSettings,
  UpdateCompanyJobFitScoreSettingsPayload,
} from '@/types/companyJobFitScoreSettings';

export const companyJobFitScoreSettingsApi = {
  async get(jobId: number): Promise<CompanyJobFitScoreSettings> {
    const { data } = await apiClient.get<ApiResponse<CompanyJobFitScoreSettings>>(
      `/company/jobs/${jobId}/fit-score-settings`,
    );

    return data.data;
  },

  async update(
    jobId: number,
    payload: UpdateCompanyJobFitScoreSettingsPayload,
  ): Promise<CompanyJobFitScoreSettings> {
    const { data } = await apiClient.put<ApiResponse<CompanyJobFitScoreSettings>>(
      `/company/jobs/${jobId}/fit-score-settings`,
      payload,
    );

    return data.data;
  },
};

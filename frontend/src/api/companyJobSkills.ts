import { apiClient } from '@/api/client';
import type { ApiResponse } from '@/types/api';
import type {
  AttachJobSkillPayload,
  JobSkill,
  SyncJobSkillsPayload,
} from '@/types/companyJob';

export const companyJobSkillsApi = {
  async list(jobId: number): Promise<JobSkill[]> {
    const { data } = await apiClient.get<ApiResponse<JobSkill[]>>(`/company/jobs/${jobId}/skills`);
    return data.data;
  },

  async attach(jobId: number, payload: AttachJobSkillPayload): Promise<JobSkill> {
    const { data } = await apiClient.post<ApiResponse<JobSkill>>(
      `/company/jobs/${jobId}/skills`,
      payload,
    );
    return data.data;
  },

  async sync(jobId: number, payload: SyncJobSkillsPayload): Promise<JobSkill[]> {
    const { data } = await apiClient.put<ApiResponse<JobSkill[]>>(
      `/company/jobs/${jobId}/skills`,
      payload,
    );
    return data.data;
  },

  async remove(jobId: number, skillId: number): Promise<void> {
    await apiClient.delete(`/company/jobs/${jobId}/skills/${skillId}`);
  },
};

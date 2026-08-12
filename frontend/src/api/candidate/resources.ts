import { apiClient } from '@/api/client';
import type { ApiResponse } from '@/types/api';
import type {
  AttachSkillPayload,
  CandidateCertification,
  CandidateEducation,
  CandidateExperience,
  CandidateProject,
  CandidateSkill,
  CertificationPayload,
  EducationPayload,
  ExperiencePayload,
  ProjectPayload,
  SkillCatalogItem,
  UpdateSkillPayload,
} from '@/types/candidate';

export const candidateExperiencesApi = {
  async list(): Promise<CandidateExperience[]> {
    const { data } = await apiClient.get<ApiResponse<CandidateExperience[]>>('/candidate/experiences');
    return data.data;
  },
  async create(payload: ExperiencePayload): Promise<CandidateExperience> {
    const { data } = await apiClient.post<ApiResponse<CandidateExperience>>('/candidate/experiences', payload);
    return data.data;
  },
  async update(id: number, payload: Partial<ExperiencePayload>): Promise<CandidateExperience> {
    const { data } = await apiClient.put<ApiResponse<CandidateExperience>>(
      `/candidate/experiences/${id}`,
      payload,
    );
    return data.data;
  },
  async remove(id: number): Promise<void> {
    await apiClient.delete(`/candidate/experiences/${id}`);
  },
};

export const candidateEducationsApi = {
  async list(): Promise<CandidateEducation[]> {
    const { data } = await apiClient.get<ApiResponse<CandidateEducation[]>>('/candidate/educations');
    return data.data;
  },
  async create(payload: EducationPayload): Promise<CandidateEducation> {
    const { data } = await apiClient.post<ApiResponse<CandidateEducation>>('/candidate/educations', payload);
    return data.data;
  },
  async update(id: number, payload: Partial<EducationPayload>): Promise<CandidateEducation> {
    const { data } = await apiClient.put<ApiResponse<CandidateEducation>>(
      `/candidate/educations/${id}`,
      payload,
    );
    return data.data;
  },
  async remove(id: number): Promise<void> {
    await apiClient.delete(`/candidate/educations/${id}`);
  },
};

export const candidateCertificationsApi = {
  async list(): Promise<CandidateCertification[]> {
    const { data } = await apiClient.get<ApiResponse<CandidateCertification[]>>('/candidate/certifications');
    return data.data;
  },
  async create(payload: CertificationPayload): Promise<CandidateCertification> {
    const { data } = await apiClient.post<ApiResponse<CandidateCertification>>(
      '/candidate/certifications',
      payload,
    );
    return data.data;
  },
  async update(id: number, payload: Partial<CertificationPayload>): Promise<CandidateCertification> {
    const { data } = await apiClient.put<ApiResponse<CandidateCertification>>(
      `/candidate/certifications/${id}`,
      payload,
    );
    return data.data;
  },
  async remove(id: number): Promise<void> {
    await apiClient.delete(`/candidate/certifications/${id}`);
  },
};

export const candidateProjectsApi = {
  async list(): Promise<CandidateProject[]> {
    const { data } = await apiClient.get<ApiResponse<CandidateProject[]>>('/candidate/projects');
    return data.data;
  },
  async create(payload: ProjectPayload): Promise<CandidateProject> {
    const { data } = await apiClient.post<ApiResponse<CandidateProject>>('/candidate/projects', payload);
    return data.data;
  },
  async update(id: number, payload: Partial<ProjectPayload>): Promise<CandidateProject> {
    const { data } = await apiClient.put<ApiResponse<CandidateProject>>(`/candidate/projects/${id}`, payload);
    return data.data;
  },
  async remove(id: number): Promise<void> {
    await apiClient.delete(`/candidate/projects/${id}`);
  },
};

export const candidateSkillsApi = {
  async list(): Promise<CandidateSkill[]> {
    const { data } = await apiClient.get<ApiResponse<CandidateSkill[]>>('/candidate/skills');
    return data.data;
  },
  async attach(payload: AttachSkillPayload): Promise<CandidateSkill> {
    const { data } = await apiClient.post<ApiResponse<CandidateSkill>>('/candidate/skills', payload);
    return data.data;
  },
  async update(id: number, payload: UpdateSkillPayload): Promise<CandidateSkill> {
    const { data } = await apiClient.put<ApiResponse<CandidateSkill>>(`/candidate/skills/${id}`, payload);
    return data.data;
  },
  async remove(id: number): Promise<void> {
    await apiClient.delete(`/candidate/skills/${id}`);
  },
};

export const skillsCatalogApi = {
  async search(query = '', limit = 20): Promise<SkillCatalogItem[]> {
    const { data } = await apiClient.get<ApiResponse<SkillCatalogItem[]>>('/skills', {
      params: { q: query || undefined, limit },
    });
    return data.data;
  },
};

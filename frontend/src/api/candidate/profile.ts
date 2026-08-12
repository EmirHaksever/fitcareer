import { apiClient } from '@/api/client';
import type { ApiResponse } from '@/types/api';
import type {
  CandidateProfile,
  CvMetadata,
  UpdateCandidateProfilePayload,
} from '@/types/candidate';

export const candidateProfileApi = {
  async get(): Promise<CandidateProfile> {
    const { data } = await apiClient.get<ApiResponse<CandidateProfile>>('/candidate/profile');
    return data.data;
  },

  async update(payload: UpdateCandidateProfilePayload): Promise<CandidateProfile> {
    const { data } = await apiClient.put<ApiResponse<CandidateProfile>>('/candidate/profile', payload);
    return data.data;
  },

  async uploadPhoto(file: File): Promise<CandidateProfile> {
    const formData = new FormData();
    formData.append('photo', file);
    const { data } = await apiClient.post<ApiResponse<CandidateProfile>>(
      '/candidate/profile/photo',
      formData,
    );
    return data.data;
  },

  async deletePhoto(): Promise<CandidateProfile> {
    const { data } = await apiClient.delete<ApiResponse<CandidateProfile>>('/candidate/profile/photo');
    return data.data;
  },

  async getCv(): Promise<CvMetadata> {
    const { data } = await apiClient.get<ApiResponse<CvMetadata>>('/candidate/cv');
    return data.data;
  },

  async uploadCv(file: File): Promise<CandidateProfile> {
    const formData = new FormData();
    formData.append('cv', file);
    const { data } = await apiClient.post<ApiResponse<CandidateProfile>>('/candidate/cv', formData);
    return data.data;
  },

  async deleteCv(): Promise<CvMetadata> {
    const { data } = await apiClient.delete<ApiResponse<CvMetadata>>('/candidate/cv');
    return data.data;
  },

  async downloadPhoto(): Promise<Blob> {
    const { data } = await apiClient.get<Blob>('/candidate/profile/photo', {
      responseType: 'blob',
    });
    return data;
  },

  async downloadCv(): Promise<Blob> {
    const { data } = await apiClient.get<Blob>('/candidate/cv/download', {
      responseType: 'blob',
    });
    return data;
  },
};

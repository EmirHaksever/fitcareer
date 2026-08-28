import { apiClient } from '@/api/client';
import type { ApiResponse, AuthTokenPayload, User } from '@/types/api';

export interface LoginPayload {
  email: string;
  password: string;
}

export interface RegisterPayload {
  name: string;
  email: string;
  password: string;
  password_confirmation: string;
  role: 'candidate' | 'company';
  company_name?: string;
}

export interface ForgotPasswordPayload {
  email: string;
}

export interface ResetPasswordPayload {
  email: string;
  token: string;
  password: string;
  password_confirmation: string;
}

export const authApi = {
  async login(payload: LoginPayload): Promise<AuthTokenPayload> {
    const { data } = await apiClient.post<ApiResponse<AuthTokenPayload>>('/auth/login', payload);
    return data.data;
  },

  async register(payload: RegisterPayload): Promise<AuthTokenPayload> {
    const { data } = await apiClient.post<ApiResponse<AuthTokenPayload>>('/auth/register', payload);
    return data.data;
  },

  async logout(): Promise<void> {
    await apiClient.post('/auth/logout');
  },

  async me(): Promise<User> {
    const { data } = await apiClient.get<ApiResponse<User>>('/auth/me');
    return data.data;
  },

  async forgotPassword(payload: ForgotPasswordPayload): Promise<string> {
    const { data } = await apiClient.post<ApiResponse<null>>('/auth/forgot-password', payload);
    return data.message;
  },

  async resetPassword(payload: ResetPasswordPayload): Promise<string> {
    const { data } = await apiClient.post<ApiResponse<null>>('/auth/reset-password', payload);
    return data.message;
  },

  async updatePassword(payload: {
    current_password: string;
    password: string;
    password_confirmation: string;
  }): Promise<string> {
    const { data } = await apiClient.put<ApiResponse<null>>('/auth/password', payload);
    return data.message;
  },
};

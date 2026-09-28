import axios, { type AxiosError, type InternalAxiosRequestConfig } from 'axios';
import type { ApiResponse } from '@/types/api';
import {
  isNetworkError,
  translateUserFacingApiMessage,
} from '@/utils/authValidationMessages';

const TOKEN_KEY = 'fitcareer_token';
const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || '/api/v1';

export const getStoredToken = (): string | null => localStorage.getItem(TOKEN_KEY);

export const setStoredToken = (token: string): void => {
  localStorage.setItem(TOKEN_KEY, token);
};

export const clearStoredToken = (): void => {
  localStorage.removeItem(TOKEN_KEY);
};

export const apiClient = axios.create({
  baseURL: API_BASE_URL,
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  },
});

apiClient.interceptors.request.use((config: InternalAxiosRequestConfig) => {
  const token = getStoredToken();

  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }

  if (config.data instanceof FormData) {
    delete config.headers['Content-Type'];
  }

  return config;
});

apiClient.interceptors.response.use(
  (response) => response,
  (error: AxiosError<ApiResponse<null>>) => {
    if (error.response?.status === 401) {
      clearStoredToken();
      if (window.location.pathname !== '/login') {
        window.location.assign('/login');
      }
    }

    return Promise.reject(error);
  },
);

export function getApiErrorMessage(error: unknown, fallback = 'Bir hata oluştu.'): string {
  if (isNetworkError(error)) {
    return 'Bağlantı kurulamadı. Lütfen tekrar deneyin.';
  }

  if (axios.isAxiosError<ApiResponse<null>>(error)) {
    const message = error.response?.data?.message;
    if (message) {
      return translateUserFacingApiMessage(message, fallback);
    }
  }

  return fallback;
}

export function getValidationErrors(error: unknown): Record<string, string[]> {
  if (axios.isAxiosError<ApiResponse<null>>(error)) {
    return error.response?.data?.errors ?? {};
  }

  return {};
}

import axios from 'axios';
import type { ApiResponse } from '@/types/api';

export function formatValidationErrorMessage(
  error: unknown,
  fallback = 'Doğrulama hatası oluştu.',
): string {
  if (!axios.isAxiosError<ApiResponse<null>>(error)) {
    return fallback;
  }

  const errors = error.response?.data?.errors ?? {};
  const details = Object.entries(errors)
    .flatMap(([field, messages]) => messages.map((message) => `${field}: ${message}`))
    .join(' · ');

  if (details) {
    return `${error.response?.data?.message ?? 'Validation failed.'} (${details})`;
  }

  return error.response?.data?.message ?? fallback;
}

function isValidationError(error: unknown): boolean {
  return axios.isAxiosError(error) && error.response?.status === 422;
}

function isDuplicateSkillError(error: unknown): boolean {
  if (!axios.isAxiosError<ApiResponse<null>>(error)) {
    return false;
  }

  const messages = error.response?.data?.errors?.skill_id ?? [];
  return messages.some((message) => /already been added/i.test(message));
}

export async function runImportStep(
  label: string,
  action: () => Promise<void>,
  warnings: string[],
): Promise<void> {
  try {
    await action();
  } catch (error) {
    if (isValidationError(error)) {
      if (isDuplicateSkillError(error)) {
        return;
      }

      warnings.push(`${label}: ${formatValidationErrorMessage(error)}`);
      return;
    }

    throw error;
  }
}

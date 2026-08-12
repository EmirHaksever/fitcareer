import { beforeEach, describe, expect, it, vi } from 'vitest';
import { companyJobFitScoreSettingsApi } from '@/api/companyJobFitScoreSettings';
import { apiClient } from '@/api/client';
import { DEFAULT_FIT_SCORE_WEIGHTS } from '@/types/companyJobFitScoreSettings';

vi.mock('@/api/client', () => ({
  apiClient: {
    get: vi.fn(),
    put: vi.fn(),
  },
}));

const mockedGet = vi.mocked(apiClient.get);
const mockedPut = vi.mocked(apiClient.put);

const defaultSettings = {
  weights: DEFAULT_FIT_SCORE_WEIGHTS,
  source: 'default' as const,
};

const customSettings = {
  weights: {
    required_skills: 45,
    preferred_skills: 10,
    experience: 25,
    work_type: 10,
    location: 5,
    salary: 5,
  },
  source: 'custom' as const,
};

describe('companyJobFitScoreSettingsApi', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('gets fit score settings', async () => {
    mockedGet.mockResolvedValueOnce({
      data: {
        success: true,
        message: 'Job fit score settings retrieved.',
        data: defaultSettings,
        errors: null,
      },
    });

    const result = await companyJobFitScoreSettingsApi.get(7);

    expect(mockedGet).toHaveBeenCalledWith('/company/jobs/7/fit-score-settings');
    expect(result.source).toBe('default');
    expect(result.weights.required_skills).toBe(35);
  });

  it('updates fit score settings', async () => {
    mockedPut.mockResolvedValueOnce({
      data: {
        success: true,
        message: 'Job fit score settings updated.',
        data: customSettings,
        errors: null,
      },
    });

    const payload = { weights: customSettings.weights };
    const result = await companyJobFitScoreSettingsApi.update(7, payload);

    expect(mockedPut).toHaveBeenCalledWith('/company/jobs/7/fit-score-settings', payload);
    expect(result.source).toBe('custom');
    expect(result.weights.required_skills).toBe(45);
  });

  it('returns default source from GET response', async () => {
    mockedGet.mockResolvedValueOnce({
      data: {
        success: true,
        message: 'Job fit score settings retrieved.',
        data: defaultSettings,
        errors: null,
      },
    });

    const result = await companyJobFitScoreSettingsApi.get(3);
    expect(result.source).toBe('default');
  });

  it('returns custom source from GET response', async () => {
    mockedGet.mockResolvedValueOnce({
      data: {
        success: true,
        message: 'Job fit score settings retrieved.',
        data: customSettings,
        errors: null,
      },
    });

    const result = await companyJobFitScoreSettingsApi.get(3);
    expect(result.source).toBe('custom');
  });

  it('propagates API validation errors', async () => {
    mockedPut.mockRejectedValueOnce({
      isAxiosError: true,
      response: {
        data: {
          success: false,
          message: 'Validation failed.',
          data: null,
          errors: {
            weights: ['Fit score weights must sum to exactly 100.'],
          },
        },
      },
    });

    await expect(
      companyJobFitScoreSettingsApi.update(7, {
        weights: {
          ...DEFAULT_FIT_SCORE_WEIGHTS,
          salary: 10,
        },
      }),
    ).rejects.toBeTruthy();
  });
});

import { beforeEach, describe, expect, it, vi } from 'vitest';
import { jobsApi } from '@/api/jobs';
import { apiClient } from '@/api/client';

vi.mock('@/api/client', () => ({
  apiClient: {
    get: vi.fn(),
  },
}));

const mockedGet = vi.mocked(apiClient.get);

describe('jobsApi.stats', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('reads catalog-wide counts from the stats endpoint', async () => {
    mockedGet.mockResolvedValueOnce({
      data: {
        success: true,
        message: 'Stats retrieved.',
        data: { published_jobs: 275, trust_analyzed_jobs: 67, active_sources: 28 },
        errors: null,
      },
    });

    const stats = await jobsApi.stats();

    expect(mockedGet).toHaveBeenCalledWith('/stats');
    expect(stats).toEqual({ published_jobs: 275, trust_analyzed_jobs: 67, active_sources: 28 });
  });
});

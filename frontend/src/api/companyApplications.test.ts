import { beforeEach, describe, expect, it, vi } from 'vitest';
import { companyApplicationsApi } from '@/api/companyApplications';
import { apiClient } from '@/api/client';
import type { CompanyApplication } from '@/types/companyApplication';

vi.mock('@/api/client', () => ({
  apiClient: {
    get: vi.fn(),
    patch: vi.fn(),
  },
}));

const mockedGet = vi.mocked(apiClient.get);
const mockedPatch = vi.mocked(apiClient.patch);

const sampleApplication: CompanyApplication = {
  id: 1,
  candidate_profile_id: 3,
  job_id: 42,
  status: 'submitted',
  cover_letter: 'Merhaba',
  match_score: 76,
  trust_score: 88,
  resume_snapshot_path: null,
  applied_at: '2026-08-11T10:00:00+03:00',
  status_updated_at: '2026-08-11T10:00:00+03:00',
  candidate: {
    id: 3,
    headline: 'Backend Developer',
    city: 'Istanbul',
    country: 'Turkey',
    years_of_experience: 5,
    profile_strength_score: 72,
    user: { id: 7, name: 'Ada Lovelace', email: 'ada@example.com' },
  },
  job: {
    id: 42,
    title: 'Senior Backend Developer',
    slug: 'senior-backend-developer',
    city: 'Istanbul',
    country: 'Turkey',
    status: 'published',
  },
  status_history: [
    {
      id: 1,
      from_status: null,
      to_status: 'submitted',
      note: null,
      changed_by: 7,
      created_at: '2026-08-11T10:00:00+03:00',
    },
  ],
};

describe('companyApplicationsApi', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('lists company applications with filters', async () => {
    mockedGet.mockResolvedValueOnce({
      data: {
        success: true,
        message: 'Company applications retrieved.',
        data: {
          items: [sampleApplication],
          pagination: {
            current_page: 1,
            per_page: 10,
            total: 1,
            last_page: 1,
          },
        },
        errors: null,
      },
    });

    const result = await companyApplicationsApi.listCompanyApplications({
      page: 1,
      per_page: 10,
      job_id: 42,
      status: 'submitted',
    });

    expect(mockedGet).toHaveBeenCalledWith('/company/applications', {
      params: { page: 1, per_page: 10, job_id: 42, status: 'submitted' },
    });
    expect(result.items).toHaveLength(1);
  });

  it('gets company application detail', async () => {
    mockedGet.mockResolvedValueOnce({
      data: {
        success: true,
        message: 'Application retrieved.',
        data: sampleApplication,
        errors: null,
      },
    });

    const result = await companyApplicationsApi.getCompanyApplication(1);

    expect(mockedGet).toHaveBeenCalledWith('/company/applications/1');
    expect(result.candidate?.user?.name).toBe('Ada Lovelace');
  });

  it('updates company application status', async () => {
    mockedPatch.mockResolvedValueOnce({
      data: {
        success: true,
        message: 'Application status updated.',
        data: { ...sampleApplication, status: 'under_review' },
        errors: null,
      },
    });

    const result = await companyApplicationsApi.updateCompanyApplicationStatus(1, {
      status: 'under_review',
      note: 'İncelemeye alındı.',
    });

    expect(mockedPatch).toHaveBeenCalledWith('/company/applications/1/status', {
      status: 'under_review',
      note: 'İncelemeye alındı.',
    });
    expect(result.status).toBe('under_review');
  });

  it('propagates conflict errors on invalid transition', async () => {
    mockedPatch.mockRejectedValueOnce({
      isAxiosError: true,
      response: {
        status: 409,
        data: {
          success: false,
          message: 'Cannot transition application status from submitted to interview.',
          data: null,
          errors: {
            status: ['Cannot transition application status from submitted to interview.'],
          },
        },
      },
    });

    await expect(
      companyApplicationsApi.updateCompanyApplicationStatus(1, { status: 'interview' }),
    ).rejects.toMatchObject({
      response: { status: 409 },
    });
  });
});

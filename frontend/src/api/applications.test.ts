import { beforeEach, describe, expect, it, vi } from 'vitest';
import { applicationsApi } from '@/api/applications';
import { apiClient } from '@/api/client';
import type { Application, PaginatedApplications } from '@/types/application';

vi.mock('@/api/client', () => ({
  apiClient: {
    get: vi.fn(),
    post: vi.fn(),
  },
}));

const mockedGet = vi.mocked(apiClient.get);
const mockedPost = vi.mocked(apiClient.post);

const sampleApplication: Application = {
  id: 1,
  job_id: 42,
  status: 'submitted',
  cover_letter: null,
  match_score: 76,
  trust_score: 88,
  resume_snapshot_path: null,
  applied_at: '2026-08-11T10:00:00+03:00',
  status_updated_at: '2026-08-11T10:00:00+03:00',
  job: {
    id: 42,
    title: 'Backend Developer',
    slug: 'backend-developer',
    company: { id: 5, name: 'Acme', slug: 'acme' },
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

const emptyList: PaginatedApplications = {
  items: [],
  pagination: {
    current_page: 1,
    per_page: 10,
    total: 0,
    last_page: 1,
  },
};

describe('applicationsApi', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('lists applications with pagination envelope', async () => {
    mockedGet.mockResolvedValueOnce({
      data: {
        success: true,
        message: 'Applications retrieved.',
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

    const result = await applicationsApi.list({ page: 1, per_page: 10 });

    expect(mockedGet).toHaveBeenCalledWith('/candidate/applications', {
      params: { page: 1, per_page: 10 },
    });
    expect(result.items).toHaveLength(1);
    expect(result.pagination.total).toBe(1);
  });

  it('returns empty list data', async () => {
    mockedGet.mockResolvedValueOnce({
      data: {
        success: true,
        message: 'Applications retrieved.',
        data: emptyList,
        errors: null,
      },
    });

    const result = await applicationsApi.list();

    expect(result.items).toEqual([]);
    expect(result.pagination.total).toBe(0);
  });

  it('creates application with only job_id and cover_letter', async () => {
    mockedPost.mockResolvedValueOnce({
      data: {
        success: true,
        message: 'Application submitted.',
        data: sampleApplication,
        errors: null,
      },
    });

    const result = await applicationsApi.create({
      job_id: 42,
      cover_letter: 'Merhaba',
    });

    expect(mockedPost).toHaveBeenCalledWith('/candidate/applications', {
      job_id: 42,
      cover_letter: 'Merhaba',
    });
    expect(result.id).toBe(1);
    expect(result.status).toBe('submitted');
  });

  it('propagates duplicate application validation errors', async () => {
    mockedPost.mockRejectedValueOnce({
      isAxiosError: true,
      response: {
        status: 422,
        data: {
          success: false,
          message: 'Validation failed.',
          data: null,
          errors: {
            job_id: ['You have already applied to this job.'],
          },
        },
      },
    });

    await expect(
      applicationsApi.create({ job_id: 42 }),
    ).rejects.toMatchObject({
      response: {
        status: 422,
        data: {
          errors: {
            job_id: ['You have already applied to this job.'],
          },
        },
      },
    });
  });

  it('gets application detail by id', async () => {
    mockedGet.mockResolvedValueOnce({
      data: {
        success: true,
        message: 'Application retrieved.',
        data: sampleApplication,
        errors: null,
      },
    });

    const result = await applicationsApi.get(1);

    expect(mockedGet).toHaveBeenCalledWith('/candidate/applications/1');
    expect(result.status_history).toHaveLength(1);
  });
});

import { beforeEach, describe, expect, it, vi } from 'vitest';
import { companyJobsApi } from '@/api/companyJobs';
import { apiClient } from '@/api/client';

vi.mock('@/api/client', () => ({
  apiClient: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
  },
}));

const mockedGet = vi.mocked(apiClient.get);
const mockedPost = vi.mocked(apiClient.post);
const mockedPut = vi.mocked(apiClient.put);

const sampleJob = {
  id: 7,
  title: 'Backend Developer',
  slug: 'backend-developer',
  description: 'Build APIs',
  requirements: null,
  responsibilities: null,
  category: 'engineering',
  employment_type: 'full_time',
  work_type: 'remote',
  experience_level: 'mid',
  city: 'Istanbul',
  country: 'Turkey',
  salary_min: null,
  salary_max: null,
  salary_currency: 'TRY',
  is_salary_visible: false,
  application_deadline: null,
  contact_email: null,
  contact_phone: null,
  status: 'draft',
  source: 'internal',
  trust_score: null,
  trust_label: 'unrated',
  trust_analysis_status: 'pending',
  published_at: null,
  created_at: '2026-08-11T10:00:00+03:00',
  updated_at: '2026-08-11T10:00:00+03:00',
};

describe('companyJobsApi', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('creates a company job', async () => {
    mockedPost.mockResolvedValueOnce({
      data: {
        success: true,
        message: 'Job created.',
        data: sampleJob,
        errors: null,
      },
    });

    const result = await companyJobsApi.create({
      title: 'Backend Developer',
      description: 'Build APIs',
      employment_type: 'full_time',
      work_type: 'remote',
    });

    expect(mockedPost).toHaveBeenCalledWith('/company/jobs', {
      title: 'Backend Developer',
      description: 'Build APIs',
      employment_type: 'full_time',
      work_type: 'remote',
    });
    expect(result.status).toBe('draft');
  });

  it('publishes a company job', async () => {
    mockedPost.mockResolvedValueOnce({
      data: {
        success: true,
        message: 'Job published.',
        data: { ...sampleJob, status: 'published' },
        errors: null,
      },
    });

    const result = await companyJobsApi.publish(7);

    expect(mockedPost).toHaveBeenCalledWith('/company/jobs/7/publish');
    expect(result.status).toBe('published');
  });

  it('updates a company job', async () => {
    mockedPut.mockResolvedValueOnce({
      data: {
        success: true,
        message: 'Job updated.',
        data: { ...sampleJob, title: 'Senior Backend Developer' },
        errors: null,
      },
    });

    const result = await companyJobsApi.update(7, { title: 'Senior Backend Developer' });

    expect(mockedPut).toHaveBeenCalledWith('/company/jobs/7', { title: 'Senior Backend Developer' });
    expect(result.title).toBe('Senior Backend Developer');
  });

  it('lists company jobs', async () => {
    mockedGet.mockResolvedValueOnce({
      data: {
        success: true,
        message: 'Company jobs retrieved.',
        data: {
          items: [sampleJob],
          pagination: {
            current_page: 1,
            per_page: 50,
            total: 1,
            last_page: 1,
          },
        },
        errors: null,
      },
    });

    const result = await companyJobsApi.list({ page: 1, per_page: 50 });

    expect(mockedGet).toHaveBeenCalledWith('/company/jobs', { params: { page: 1, per_page: 50 } });
    expect(result.items).toHaveLength(1);
  });
});

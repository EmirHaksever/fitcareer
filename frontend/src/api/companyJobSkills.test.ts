import { beforeEach, describe, expect, it, vi } from 'vitest';
import { companyJobSkillsApi } from '@/api/companyJobSkills';
import { apiClient } from '@/api/client';

vi.mock('@/api/client', () => ({
  apiClient: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    delete: vi.fn(),
  },
}));

const mockedGet = vi.mocked(apiClient.get);
const mockedPost = vi.mocked(apiClient.post);
const mockedPut = vi.mocked(apiClient.put);
const mockedDelete = vi.mocked(apiClient.delete);

const sampleSkills = [
  { id: 1, name: 'Flutter', slug: 'flutter', importance: 'required' as const },
  { id: 2, name: 'Dart', slug: 'dart', importance: 'required' as const },
  { id: 3, name: 'Firebase', slug: 'firebase', importance: 'preferred' as const },
];

describe('companyJobSkillsApi', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('lists job skills', async () => {
    mockedGet.mockResolvedValueOnce({
      data: {
        success: true,
        message: 'Job skills retrieved.',
        data: sampleSkills,
        errors: null,
      },
    });

    const result = await companyJobSkillsApi.list(7);

    expect(mockedGet).toHaveBeenCalledWith('/company/jobs/7/skills');
    expect(result).toHaveLength(3);
    expect(result[0]?.importance).toBe('required');
  });

  it('attaches a job skill', async () => {
    mockedPost.mockResolvedValueOnce({
      data: {
        success: true,
        message: 'Skill attached.',
        data: sampleSkills[0],
        errors: null,
      },
    });

    const result = await companyJobSkillsApi.attach(7, {
      skill_id: 1,
      importance: 'required',
    });

    expect(mockedPost).toHaveBeenCalledWith('/company/jobs/7/skills', {
      skill_id: 1,
      importance: 'required',
    });
    expect(result.name).toBe('Flutter');
  });

  it('syncs job skills in bulk', async () => {
    mockedPut.mockResolvedValueOnce({
      data: {
        success: true,
        message: 'Job skills updated.',
        data: sampleSkills,
        errors: null,
      },
    });

    const payload = {
      skills: [
        { skill_id: 1, importance: 'required' as const },
        { skill_id: 2, importance: 'required' as const },
        { skill_id: 3, importance: 'preferred' as const },
      ],
    };

    const result = await companyJobSkillsApi.sync(7, payload);

    expect(mockedPut).toHaveBeenCalledWith('/company/jobs/7/skills', payload);
    expect(result).toHaveLength(3);
  });

  it('removes a job skill', async () => {
    mockedDelete.mockResolvedValueOnce({ data: { success: true } });

    await companyJobSkillsApi.remove(7, 3);

    expect(mockedDelete).toHaveBeenCalledWith('/company/jobs/7/skills/3');
  });
});

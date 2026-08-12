import { describe, expect, it, vi, beforeEach } from 'vitest';
import type { CandidateProfile, CvParsedData } from '@/types/candidate';
import { skillsCatalogApi } from '@/api/candidate/resources';
import { candidateProfileApi } from '@/api/candidate/profile';
import { applyCvImport } from '@/utils/cvImport';

vi.mock('@/api/candidate/resources', () => ({
  candidateExperiencesApi: { create: vi.fn() },
  candidateEducationsApi: { create: vi.fn() },
  candidateCertificationsApi: { create: vi.fn() },
  candidateProjectsApi: { create: vi.fn() },
  candidateSkillsApi: { attach: vi.fn() },
  skillsCatalogApi: { search: vi.fn() },
}));

vi.mock('@/api/candidate/profile', () => ({
  candidateProfileApi: { update: vi.fn() },
}));

const profile = {
  id: 1,
  headline: null,
  summary: null,
  city: null,
  country: null,
  profile_photo_path: null,
  has_cv: true,
  profile_strength_score: 0,
  open_to_work: false,
  desired_position: null,
  desired_salary_min: null,
  desired_salary_max: null,
  work_preference: null,
  years_of_experience: null,
  linkedin_url: null,
  github_url: null,
  portfolio_url: null,
  experiences: [],
  educations: [],
  certifications: [],
  projects: [],
  skills: [],
} satisfies CandidateProfile;

const parsed: CvParsedData = {
  text: 'Yetenekler\nJavaScript, PHP',
  sections: { skills: 'JavaScript, PHP' },
  source_filename: 'cv.pdf',
  parsed_at: '2026-08-11T00:00:00Z',
  parser_version: '1',
};

describe('applyCvImport', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(candidateProfileApi.update).mockResolvedValue(profile);
    vi.mocked(skillsCatalogApi.search).mockResolvedValue([
      { id: 1, name: 'JavaScript', slug: 'javascript', category: 'Technology' },
      { id: 2, name: 'PHP', slug: 'php', category: 'Technology' },
    ]);
  });

  it('requests skill catalog with API-compliant limit', async () => {
    await applyCvImport(profile, parsed);

    expect(skillsCatalogApi.search).toHaveBeenCalledWith('', 50);
  });
});

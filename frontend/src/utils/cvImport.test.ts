import { describe, expect, it } from 'vitest';
import { extractCvImportPlan } from '@/utils/cvImport';
import type { CvParsedData } from '@/types/candidate';

const parsed: CvParsedData = {
  text: 'full text',
  source_filename: 'cv.pdf',
  parsed_at: '2026-08-01T00:00:00Z',
  parser_version: '1',
  sections: {
    summary: 'Product Manager with 5 years of experience',
    contact: 'Istanbul, Turkey\nlinkedin.com/in/emir',
    experience: `Product Manager\nAcme Corp\n2020 - Present`,
    education: `Istanbul University\nComputer Engineering\n2016 - 2020`,
    skills: 'React, TypeScript, Laravel',
    certifications: `AWS Certified\nAmazon Web Services, 2023`,
    projects: `FitCareer\nJob platform\n2024 - Present`,
  },
};

describe('extractCvImportPlan', () => {
  it('builds import plan for all cv sections', () => {
    const plan = extractCvImportPlan(parsed);

    expect(plan.profile.summary).toContain('Product Manager');
    expect(plan.experiences).toHaveLength(1);
    expect(plan.educations).toHaveLength(1);
    expect(plan.skillNames).toEqual(['React', 'TypeScript', 'Laravel']);
    expect(plan.certifications).toHaveLength(1);
    expect(plan.projects).toHaveLength(1);
  });
});

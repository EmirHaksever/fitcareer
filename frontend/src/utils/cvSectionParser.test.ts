import { describe, expect, it } from 'vitest';
import {
  parseCertificationsSection,
  parseDateRange,
  parseEducationSection,
  parseExperienceSection,
  parseProjectsSection,
  parseSkillsSection,
} from '@/utils/cvSectionParser';

describe('parseDateRange', () => {
  it('parses month-year ranges and present', () => {
    expect(parseDateRange('Jan 2020 - Dec 2022')).toEqual({
      start_date: '2020-01-01',
      end_date: '2022-12-01',
      is_current: false,
    });

    expect(parseDateRange('2020 - Present')).toEqual({
      start_date: '2020-01-01',
      end_date: null,
      is_current: true,
    });
  });
});

describe('parseExperienceSection', () => {
  it('extracts multiple experience entries', () => {
    const result = parseExperienceSection(`
Product Manager
Acme Corp | Istanbul
Jan 2020 - Present
Led product strategy and roadmap

Senior Analyst
Beta Inc
2018 - 2019
Built reporting dashboards
    `);

    expect(result).toHaveLength(2);
    expect(result[0]).toMatchObject({
      position_title: 'Product Manager',
      company_name: 'Acme Corp',
      location: 'Istanbul',
      is_current: true,
      start_date: '2020-01-01',
    });
    expect(result[1]).toMatchObject({
      position_title: 'Senior Analyst',
      company_name: 'Beta Inc',
      start_date: '2018-01-01',
      end_date: '2019-01-01',
    });
  });
});

describe('parseEducationSection', () => {
  it('extracts school and degree', () => {
    const result = parseEducationSection(`
Istanbul University
Bachelor of Science, Computer Science
2016 - 2020
    `);

    expect(result).toHaveLength(1);
    expect(result[0]).toMatchObject({
      school_name: 'Istanbul University',
      degree: 'Bachelor of Science',
      field_of_study: 'Computer Science',
      start_date: '2016-01-01',
      end_date: '2020-01-01',
    });
  });
});

describe('parseSkillsSection', () => {
  it('splits comma and line separated skills', () => {
    const result = parseSkillsSection('React, TypeScript\nLaravel • SQL');
    expect(result).toEqual(['React', 'TypeScript', 'Laravel', 'SQL']);
  });
});

describe('parseCertificationsSection', () => {
  it('extracts certification name and issuer', () => {
    const result = parseCertificationsSection(`
AWS Certified Solutions Architect
Amazon Web Services, 2023
    `);

    expect(result[0]).toMatchObject({
      name: 'AWS Certified Solutions Architect',
      issuing_organization: 'Amazon Web Services',
      issue_date: '2023-01-01',
    });
  });
});

describe('parseProjectsSection', () => {
  it('extracts project title, description and technologies', () => {
    const result = parseProjectsSection(`
FitCareer Platform
Full-stack job platform built with Laravel and React
2024 - Present
Tech: React, Laravel, MySQL
    `);

    expect(result[0]).toMatchObject({
      title: 'FitCareer Platform',
      start_date: '2024-01-01',
      technologies: ['React', 'Laravel', 'MySQL'],
    });
    expect(result[0].description).toContain('Full-stack job platform');
  });
});

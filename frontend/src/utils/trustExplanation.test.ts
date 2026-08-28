import { describe, expect, it } from 'vitest';
import { buildTrustFactors } from '@/utils/trustExplanation';
import type { JobDetail } from '@/types/api';

function jobFixture(overrides: Partial<JobDetail> = {}): JobDetail {
  return {
    id: 1,
    title: 'Junior Backend Developer',
    slug: 'junior-backend-developer',
    description: 'x'.repeat(120),
    requirements: null,
    responsibilities: null,
    category: 'engineering',
    employment_type: 'full_time',
    work_type: 'onsite',
    experience_level: 'entry',
    city: 'Istanbul',
    country: 'Turkey',
    salary_min: null,
    salary_max: null,
    salary_currency: null,
    is_salary_visible: false,
    published_at: new Date().toISOString(),
    source: 'internal',
    source_company_name: null,
    external_url: null,
    expires_at: null,
    application_deadline: null,
    company: {
      id: 1,
      name: 'Acme',
      slug: 'acme',
      is_verified: false,
    },
    source_provider: null,
    trust_score: 70,
    trust_label: 'trusted',
    trust_analysis_status: 'completed',
    fit_score: null,
    fit_analysis_status: null,
    fit_details: null,
    skills: [],
    ...overrides,
  };
}

describe('trust explanation labels', () => {
  it('labels internal jobs as direct employer postings', () => {
    const factors = buildTrustFactors(jobFixture());

    expect(factors.some((factor) => factor.label === 'Doğrudan işveren ilanı.')).toBe(true);
    expect(factors.some((factor) => factor.id === 'verified_company')).toBe(false);
  });

  it('adds the verified company factor only when is_verified is true', () => {
    const unverified = buildTrustFactors(
      jobFixture({ company: { id: 1, name: 'Acme', slug: 'acme', is_verified: false } }),
    );
    const verified = buildTrustFactors(
      jobFixture({ company: { id: 1, name: 'Acme', slug: 'acme', is_verified: true } }),
    );

    expect(unverified.some((factor) => factor.id === 'verified_company')).toBe(false);
    expect(verified.some((factor) => factor.id === 'verified_company')).toBe(true);
  });
});

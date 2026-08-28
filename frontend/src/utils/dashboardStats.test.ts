import { describe, expect, it } from 'vitest';
import type { DashboardData } from '@/types/api';
import { mapDashboardStats } from '@/utils/dashboardStats';
import { buildTrustFactors } from '@/utils/trustExplanation';

describe('mapDashboardStats', () => {
  it('maps api dashboard stats to cards', () => {
    const data: DashboardData = {
      stats: {
        total_jobs: 100,
        trusted_jobs: 60,
        suspicious_jobs: 5,
        application_count: 2,
        average_fit_score: 72,
        analyzed_job_count: 8,
        has_cv: true,
        profile_strength_score: 80,
      },
      trust_distribution: [],
      recommended_jobs: [],
      analyzed_jobs: [],
      career_assistant: {
        has_cv: true,
        average_fit_score: 72,
        analyzed_job_count: 8,
      },
    };

    const stats = mapDashboardStats(data);

    expect(stats[0]?.value).toBe('60');
    expect(stats[1]?.value).toBe('5');
    expect(stats[2]?.value).toBe('2');
    expect(stats[3]?.value).toBe('%72');
  });
});

describe('buildTrustFactors', () => {
  it('marks external source jobs with known source factor', () => {
    const factors = buildTrustFactors({
      source: 'scraped',
      source_company_name: 'Acme',
      external_url: 'https://example.com/job',
      published_at: new Date().toISOString(),
      description: 'x'.repeat(120),
      company: null,
    } as never);

    expect(factors.some((factor) => factor.id === 'known_source' && factor.status === 'supported')).toBe(
      true,
    );
  });
});

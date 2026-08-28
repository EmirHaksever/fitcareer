import { describe, expect, it } from 'vitest';
import type { CompanyApplication } from '@/types/companyApplication';
import {
  averageCompletedMatchScore,
  selectPriorityApplications,
} from '@/utils/companyDashboardMatch';

function application(
  overrides: Partial<CompanyApplication> & Pick<CompanyApplication, 'id' | 'status' | 'match_score' | 'applied_at'>,
): CompanyApplication {
  return {
    candidate_profile_id: 1,
    job_id: 1,
    cover_letter: null,
    trust_score: null,
    resume_snapshot_path: null,
    status_updated_at: null,
    ...overrides,
  };
}

describe('companyDashboardMatch', () => {
  it('orders needing review first, then highest match, then newest', () => {
    const items = [
      application({
        id: 1,
        status: 'rejected',
        match_score: 99,
        applied_at: '2026-08-14T12:00:00+03:00',
      }),
      application({
        id: 2,
        status: 'submitted',
        match_score: 50,
        applied_at: '2026-08-14T11:00:00+03:00',
      }),
      application({
        id: 3,
        status: 'submitted',
        match_score: 88,
        applied_at: '2026-08-13T10:00:00+03:00',
      }),
      application({
        id: 4,
        status: 'under_review',
        match_score: null,
        applied_at: '2026-08-14T13:00:00+03:00',
      }),
      application({
        id: 5,
        status: 'withdrawn',
        match_score: 70,
        applied_at: '2026-08-14T14:00:00+03:00',
      }),
    ];

    expect(selectPriorityApplications(items, 5).map((item) => item.id)).toEqual([3, 2, 4]);
  });

  it('averages only completed match scores', () => {
    const items = [
      application({ id: 1, status: 'submitted', match_score: 80, applied_at: '2026-08-14T10:00:00+03:00' }),
      application({ id: 2, status: 'submitted', match_score: 90, applied_at: '2026-08-14T11:00:00+03:00' }),
      application({ id: 3, status: 'submitted', match_score: null, applied_at: '2026-08-14T12:00:00+03:00' }),
    ];

    expect(averageCompletedMatchScore(items)).toBe(85);
    expect(averageCompletedMatchScore([items[2]!])).toBeNull();
  });
});

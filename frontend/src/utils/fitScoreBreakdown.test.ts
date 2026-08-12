import { describe, expect, it } from 'vitest';
import { buildFitBreakdown, shouldShowFitScoreBadge } from '@/utils/fitScoreBreakdown';
import type { FitScoreDetails } from '@/types/fitScore';

describe('fitScoreBreakdown', () => {
  it('builds skill breakdown lines from signal evidence', () => {
    const details: FitScoreDetails = {
      signals: {
        required_skills: {
          score: 75,
          confidence: 1,
          evidence: {
            matched_skills: ['Laravel', 'PHP'],
            missing_skills: ['Docker'],
          },
        },
      },
    };

    const items = buildFitBreakdown(details);

    expect(items).toHaveLength(1);
    expect(items[0]?.title).toBe('Gerekli yetenekler');
    expect(items[0]?.lines).toContain('Laravel: Eşleşiyor');
    expect(items[0]?.lines).toContain('Docker: Eksik');
  });

  it('returns empty breakdown when signals are missing', () => {
    expect(buildFitBreakdown(null)).toEqual([]);
    expect(buildFitBreakdown({})).toEqual([]);
  });

  it('formats experience signal from evidence', () => {
    const details: FitScoreDetails = {
      signals: {
        experience: {
          score: 100,
          confidence: 1,
          evidence: {
            job_experience_level: 'senior',
            candidate_years_of_experience: 8,
          },
        },
      },
    };

    const items = buildFitBreakdown(details);

    expect(items[0]?.lines.some((line) => line.includes('Deneyim:'))).toBe(true);
    expect(items[0]?.lines.some((line) => line.includes('8 yıl'))).toBe(true);
  });

  it('shows fit badge for candidate with score', () => {
    expect(shouldShowFitScoreBadge(true, 82, 'completed')).toBe(true);
  });

  it('shows fit badge while analysis is pending', () => {
    expect(shouldShowFitScoreBadge(true, null, 'pending')).toBe(true);
  });

  it('hides fit badge for guest even with score in payload', () => {
    expect(shouldShowFitScoreBadge(false, 82, 'completed')).toBe(false);
  });

  it('hides fit badge for company users', () => {
    expect(shouldShowFitScoreBadge(false, 90, 'completed')).toBe(false);
  });

  it('does not force empty badge when score is null and not pending', () => {
    expect(shouldShowFitScoreBadge(true, null, 'completed')).toBe(false);
    expect(shouldShowFitScoreBadge(true, null, null)).toBe(false);
  });
});

import { describe, expect, it } from 'vitest';
import { buildCompanyMatchExplanation, insufficientMatchDataMessage } from '@/utils/matchExplanation';

describe('matchExplanation', () => {
  it('uses only stored matched and missing skills', () => {
    const explanation = buildCompanyMatchExplanation({
      signals: {
        required_skills: {
          score: 70,
          confidence: 0.9,
          evidence: {
            matched_skills: ['PHP', 'Laravel'],
            missing_skills: ['Docker'],
          },
        },
        preferred_skills: {
          score: 50,
          confidence: 0.8,
          evidence: {
            matched_skills: ['SQL'],
            missing_skills: [],
          },
        },
        experience: {
          score: 80,
          confidence: 0.9,
          evidence: {
            job_experience_level: 'entry',
            candidate_years_of_experience: 2,
          },
        },
      },
    });

    expect(explanation.matchedSkills).toEqual(['PHP', 'Laravel', 'SQL']);
    expect(explanation.attentionSkills).toEqual(['Docker']);
    expect(explanation.skillsInsufficient).toBe(false);
    expect(explanation.experience.jobLevelLabel).toBe('Junior');
    expect(explanation.experience.candidateLabel).toBe('2 yıl');
  });

  it('does not invent missing skills when the engine has no skill list', () => {
    const explanation = buildCompanyMatchExplanation({
      signals: {
        required_skills: {
          score: null,
          confidence: 0,
          evidence: { reason: 'no_skills_defined', required_count: 0 },
        },
      },
    });

    expect(explanation.matchedSkills).toEqual([]);
    expect(explanation.attentionSkills).toEqual([]);
    expect(explanation.skillsInsufficient).toBe(true);
    expect(insufficientMatchDataMessage()).toBe('Bu alan için yeterli profil verisi bulunamadı.');
  });

  it('falls back to structured job and candidate fields for experience', () => {
    const explanation = buildCompanyMatchExplanation(null, {
      jobExperienceLevel: 'mid',
      candidateYears: 4,
    });

    expect(explanation.experience.insufficient).toBe(false);
    expect(explanation.experience.jobLevelLabel).toBe('Mid-Level');
    expect(explanation.experience.candidateLabel).toBe('4 yıl');
  });
});

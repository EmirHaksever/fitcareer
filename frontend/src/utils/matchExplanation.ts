import type { FitScoreDetails, FitSignalDetail } from '@/types/fitScore';
import { formatExperienceLevel } from '@/utils/format';

const INSUFFICIENT_DATA = 'Bu alan için yeterli profil verisi bulunamadı.';

function asStringArray(value: unknown): string[] {
  if (!Array.isArray(value)) {
    return [];
  }

  return value.filter((item): item is string => typeof item === 'string' && item.trim() !== '');
}

function uniqueStrings(values: string[]): string[] {
  return [...new Set(values.map((value) => value.trim()).filter(Boolean))];
}

function signalReason(signal: FitSignalDetail | undefined): string | null {
  const reason = signal?.evidence.reason;
  return typeof reason === 'string' ? reason : null;
}

export interface MatchExperienceExplanation {
  jobLevel: string | null;
  jobLevelLabel: string | null;
  candidateYears: number | null;
  candidateLabel: string | null;
  insufficient: boolean;
}

export interface CompanyMatchExplanation {
  matchedSkills: string[];
  attentionSkills: string[];
  skillsInsufficient: boolean;
  experience: MatchExperienceExplanation;
}

export function buildCompanyMatchExplanation(
  details: FitScoreDetails | null | undefined,
  fallback: {
    jobExperienceLevel?: string | null;
    candidateYears?: number | null;
  } = {},
): CompanyMatchExplanation {
  const signals = details?.signals;
  const required = signals?.required_skills;
  const preferred = signals?.preferred_skills;
  const experience = signals?.experience;

  const matchedSkills = uniqueStrings([
    ...asStringArray(required?.evidence.matched_skills),
    ...asStringArray(preferred?.evidence.matched_skills),
  ]);
  const attentionSkills = uniqueStrings([
    ...asStringArray(required?.evidence.missing_skills),
    ...asStringArray(preferred?.evidence.missing_skills),
  ]);

  const noSkillsDefined =
    signalReason(required) === 'no_skills_defined' &&
    (preferred === undefined || signalReason(preferred) === 'no_skills_defined' || asStringArray(preferred.evidence.matched_skills).length === 0);

  const skillsInsufficient =
    matchedSkills.length === 0 &&
    attentionSkills.length === 0 &&
    (signals === undefined || noSkillsDefined || (required === undefined && preferred === undefined));

  const jobLevelFromSignal =
    typeof experience?.evidence.job_experience_level === 'string'
      ? experience.evidence.job_experience_level
      : null;
  const candidateYearsFromSignal =
    typeof experience?.evidence.candidate_years_of_experience === 'number'
      ? experience.evidence.candidate_years_of_experience
      : null;

  const jobLevel = jobLevelFromSignal ?? fallback.jobExperienceLevel ?? null;
  const candidateYears = candidateYearsFromSignal ?? fallback.candidateYears ?? null;

  return {
    matchedSkills,
    attentionSkills,
    skillsInsufficient,
    experience: {
      jobLevel,
      jobLevelLabel: jobLevel ? formatExperienceLevel(jobLevel) : null,
      candidateYears,
      candidateLabel: candidateYears !== null ? `${candidateYears} yıl` : null,
      insufficient: jobLevel === null && candidateYears === null,
    },
  };
}

export function insufficientMatchDataMessage(): string {
  return INSUFFICIENT_DATA;
}

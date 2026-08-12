export type FitAnalysisStatus = 'pending' | 'completed' | 'failed' | null;

export interface FitSignalDetail {
  score: number | null;
  confidence: number;
  evidence: Record<string, unknown>;
}

export interface FitScoreDetails {
  signals?: Record<string, FitSignalDetail>;
  confidence?: number | null;
  input_fingerprint?: string;
  fit_version?: string;
  candidate_updated_at?: string | null;
  job_updated_at?: string | null;
}

export const FIT_SIGNAL_KEYS = [
  'required_skills',
  'preferred_skills',
  'experience',
  'work_type',
  'location',
  'salary',
] as const;

export type FitSignalKey = (typeof FIT_SIGNAL_KEYS)[number];

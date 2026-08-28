import type { ApplicationStatus, ApplicationStatusHistory } from '@/types/application';
import type { FitScoreDetails } from '@/types/fitScore';

export type CompanyApplicationSort =
  | 'attention'
  | 'match_score_desc'
  | 'match_score_asc'
  | 'applied_at_desc'
  | 'applied_at_asc';

export type MatchAnalysisStatus = 'pending' | 'completed' | 'failed' | null;

export interface CompanyApplicationCandidateUser {
  id: number;
  name: string;
  email: string;
}

export interface CompanyApplicationCandidate {
  id: number;
  headline: string | null;
  city: string | null;
  country: string | null;
  years_of_experience: number | null;
  profile_strength_score: number;
  user: CompanyApplicationCandidateUser | null;
}

export interface CompanyApplicationJob {
  id: number;
  title: string;
  slug: string;
  city: string | null;
  country: string | null;
  status: string;
  experience_level?: string | null;
  employment_type?: string | null;
  work_type?: string | null;
}

export interface CompanyApplication {
  id: number;
  candidate_profile_id: number;
  job_id: number;
  status: ApplicationStatus;
  cover_letter: string | null;
  match_score: number | null;
  match_analysis_status?: MatchAnalysisStatus;
  match_details?: FitScoreDetails | null;
  trust_score: number | null;
  resume_snapshot_path: string | null;
  applied_at: string;
  status_updated_at: string | null;
  candidate?: CompanyApplicationCandidate;
  job?: CompanyApplicationJob;
  status_history?: ApplicationStatusHistory[];
}

export interface PaginatedCompanyApplications {
  items: CompanyApplication[];
  pagination: {
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
  };
}

export interface CompanyApplicationListParams {
  page?: number;
  per_page?: number;
  job_id?: number;
  status?: ApplicationStatus;
  sort?: CompanyApplicationSort;
}

export interface UpdateCompanyApplicationStatusPayload {
  status: ApplicationStatus;
  note?: string | null;
}

export interface CompanyJobOption {
  id: number;
  title: string;
  slug: string;
  status: string;
  city?: string | null;
  country?: string | null;
  work_type?: string | null;
  employment_type?: string | null;
  experience_level?: string | null;
  published_at?: string | null;
  applications_count?: number;
}

export interface PaginatedCompanyJobs {
  items: CompanyJobOption[];
  pagination: {
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
  };
}

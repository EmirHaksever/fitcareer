import type { ApplicationStatus, ApplicationStatusHistory } from '@/types/application';

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
}

export interface CompanyApplication {
  id: number;
  candidate_profile_id: number;
  job_id: number;
  status: ApplicationStatus;
  cover_letter: string | null;
  match_score: number | null;
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
  published_at?: string | null;
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

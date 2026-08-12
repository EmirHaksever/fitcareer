export type ApplicationStatus =
  | 'submitted'
  | 'under_review'
  | 'shortlisted'
  | 'interview'
  | 'offered'
  | 'rejected'
  | 'withdrawn';

export interface ApplicationJobCompany {
  id: number;
  name: string;
  slug: string;
}

export interface ApplicationJob {
  id: number;
  title: string;
  slug: string;
  city?: string | null;
  country?: string | null;
  employment_type?: string | null;
  work_type?: string | null;
  company: ApplicationJobCompany | null;
}

export interface ApplicationStatusHistory {
  id: number;
  from_status: ApplicationStatus | null;
  to_status: ApplicationStatus;
  note: string | null;
  changed_by: number | null;
  created_at: string;
}

export interface Application {
  id: number;
  job_id: number;
  status: ApplicationStatus;
  cover_letter: string | null;
  match_score: number | null;
  trust_score: number | null;
  resume_snapshot_path: string | null;
  applied_at: string;
  status_updated_at: string | null;
  job?: ApplicationJob;
  status_history?: ApplicationStatusHistory[];
}

export interface PaginatedApplications {
  items: Application[];
  pagination: {
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
  };
}

export interface ApplicationListParams {
  page?: number;
  per_page?: number;
}

export interface CreateApplicationPayload {
  job_id: number;
  cover_letter?: string | null;
}

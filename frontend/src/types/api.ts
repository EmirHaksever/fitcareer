export type UserRole = 'candidate' | 'company' | 'admin';

export interface User {
  id: number;
  name: string;
  email: string;
  role: UserRole;
  status: string;
  locale: string;
  email_verified_at: string | null;
  last_login_at: string | null;
}

export interface AuthTokenPayload {
  user: User;
  token: string;
}

export interface ApiResponse<T> {
  success: boolean;
  message: string;
  data: T;
  errors: Record<string, string[]> | null;
}

export type TrustAnalysisStatus = 'pending' | 'analyzing' | 'completed' | 'failed';
export type FitAnalysisStatus = 'pending' | 'completed' | 'failed' | null;

import type { FitScoreDetails } from '@/types/fitScore';

export type { FitScoreDetails, FitSignalDetail } from '@/types/fitScore';

export interface JobListItem {
  id: number;
  title: string;
  slug: string;
  category: string | null;
  employment_type: string | null;
  work_type: string | null;
  experience_level: string | null;
  city: string | null;
  country: string | null;
  salary_min: number | null;
  salary_max: number | null;
  salary_currency: string | null;
  is_salary_visible: boolean;
  published_at: string | null;
  source?: string;
  source_company_name?: string | null;
  source_provider?: {
    id: number;
    name: string;
    type: string;
  } | null;
  company: {
    id: number;
    name: string;
    slug: string;
  } | null;
  trust_score: number | null;
  trust_label: string;
  trust_analysis_status: TrustAnalysisStatus;
  fit_score: number | null;
  fit_analysis_status: FitAnalysisStatus;
}

export interface PaginatedJobs {
  items: JobListItem[];
  pagination: {
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
  };
}

export interface JobSearchParams {
  keyword?: string;
  location?: string;
  category?: string;
  employment_type?: string;
  work_type?: string;
  experience_level?: string;
  min_salary?: number;
  max_salary?: number;
  min_trust_score?: number;
  min_fit_score?: number;
  sort?: 'published_at' | 'salary' | 'trust_score' | 'fit_score';
  page?: number;
  per_page?: number;
}

export interface JobSkill {
  id: number;
  name: string;
  slug: string;
  importance: string;
}

export interface JobDetail extends JobListItem {
  description: string;
  requirements: string | null;
  responsibilities: string | null;
  application_deadline: string | null;
  source: string;
  source_company_name: string | null;
  external_url: string | null;
  expires_at: string | null;
  skills?: JobSkill[];
  fit_details?: FitScoreDetails | null;
  company: {
    id: number;
    name: string;
    slug: string;
    is_verified?: boolean;
  } | null;
}

export type JobSortValue = NonNullable<JobSearchParams['sort']>;

export interface DashboardStat {
  id: string;
  label: string;
  value: string;
  helper: string;
  tone: 'primary' | 'warning' | 'neutral' | 'success';
}

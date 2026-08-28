export type CompanyJobStatus =
  | 'draft'
  | 'pending_review'
  | 'published'
  | 'expired'
  | 'closed'
  | 'flagged';

export type JobSkillImportance = 'required' | 'preferred';

export interface JobSkill {
  id: number;
  name: string;
  slug: string;
  importance: JobSkillImportance;
}

export interface JobSkillDraft {
  skill_id: number;
  name: string;
  slug: string;
  importance: JobSkillImportance;
}

export interface AttachJobSkillPayload {
  skill_id: number;
  importance: JobSkillImportance;
}

export interface SyncJobSkillsPayload {
  skills: AttachJobSkillPayload[];
}

export interface CompanyJob {
  id: number;
  title: string;
  slug: string;
  description: string;
  requirements: string | null;
  responsibilities: string | null;
  category: string | null;
  employment_type: string;
  work_type: string;
  experience_level: string | null;
  city: string | null;
  country: string | null;
  salary_min: number | null;
  salary_max: number | null;
  salary_currency: string | null;
  is_salary_visible: boolean;
  application_deadline: string | null;
  contact_email: string | null;
  contact_phone: string | null;
  status: CompanyJobStatus;
  source: string;
  trust_score: number | null;
  trust_label: string;
  trust_analysis_status: string;
  published_at: string | null;
  created_at: string | null;
  updated_at: string | null;
  applications_count?: number;
  skills?: JobSkill[];
}

export interface PaginatedCompanyJobList {
  items: CompanyJob[];
  pagination: {
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
  };
}

export interface CompanyJobListParams {
  page?: number;
  per_page?: number;
}

export interface CreateCompanyJobPayload {
  title: string;
  description: string;
  employment_type: string;
  work_type: string;
  requirements?: string | null;
  responsibilities?: string | null;
  category?: string | null;
  experience_level?: string | null;
  city?: string | null;
  country?: string | null;
  salary_min?: number | null;
  salary_max?: number | null;
  salary_currency?: string | null;
  is_salary_visible?: boolean;
  application_deadline?: string | null;
  contact_email?: string | null;
  contact_phone?: string | null;
}

export type UpdateCompanyJobPayload = Partial<CreateCompanyJobPayload>;

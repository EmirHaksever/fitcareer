export type WorkPreference = 'remote' | 'hybrid' | 'onsite' | 'any';
export type EmploymentType = 'full_time' | 'part_time' | 'contract' | 'internship' | 'freelance';
export type ProficiencyLevel = 'beginner' | 'intermediate' | 'advanced' | 'expert';

export interface SkillCatalogItem {
  id: number;
  name: string;
  slug: string;
  category: string | null;
}

export interface CandidateExperience {
  id: number;
  company_name: string;
  position_title: string;
  employment_type: EmploymentType | null;
  location: string | null;
  is_current: boolean;
  start_date: string;
  end_date: string | null;
  description: string | null;
}

export interface CandidateEducation {
  id: number;
  school_name: string;
  degree: string | null;
  field_of_study: string | null;
  start_date: string;
  end_date: string | null;
  is_current: boolean;
  grade: string | null;
  description: string | null;
}

export interface CandidateCertification {
  id: number;
  name: string;
  issuing_organization: string;
  issue_date: string | null;
  expiration_date: string | null;
  credential_id: string | null;
  credential_url: string | null;
}

export interface CandidateProject {
  id: number;
  title: string;
  description: string | null;
  project_url: string | null;
  repository_url: string | null;
  start_date: string | null;
  end_date: string | null;
  technologies: string[] | null;
}

export interface CandidateSkill {
  id: number;
  skill_id: number;
  proficiency_level: ProficiencyLevel | null;
  years_of_experience: number | null;
  skill: SkillCatalogItem;
}

export interface CandidateProfile {
  id: number;
  headline: string | null;
  summary: string | null;
  city: string | null;
  country: string | null;
  profile_photo_path: string | null;
  has_cv: boolean;
  profile_strength_score: number;
  open_to_work: boolean;
  desired_position: string | null;
  desired_salary_min: string | null;
  desired_salary_max: string | null;
  work_preference: WorkPreference | null;
  years_of_experience: number | null;
  linkedin_url: string | null;
  github_url: string | null;
  portfolio_url: string | null;
  experiences: CandidateExperience[];
  educations: CandidateEducation[];
  certifications: CandidateCertification[];
  projects: CandidateProject[];
  skills: CandidateSkill[];
}

export interface CvParsedData {
  text: string;
  sections: Record<string, string>;
  source_filename: string;
  parsed_at: string;
  parser_version: string;
}

export interface CvMetadata {
  has_cv: boolean;
  source_filename: string | null;
  cv_parsed_data: CvParsedData | null;
}

export interface UpdateCandidateProfilePayload {
  headline?: string | null;
  summary?: string | null;
  city?: string | null;
  country?: string | null;
  open_to_work?: boolean;
  desired_position?: string | null;
  desired_salary_min?: number | null;
  desired_salary_max?: number | null;
  work_preference?: WorkPreference | null;
  years_of_experience?: number | null;
  linkedin_url?: string | null;
  github_url?: string | null;
  portfolio_url?: string | null;
}

export interface ExperiencePayload {
  company_name: string;
  position_title: string;
  employment_type?: EmploymentType | null;
  location?: string | null;
  is_current?: boolean;
  start_date: string;
  end_date?: string | null;
  description?: string | null;
}

export interface EducationPayload {
  school_name: string;
  degree?: string | null;
  field_of_study?: string | null;
  start_date: string;
  end_date?: string | null;
  is_current?: boolean;
  grade?: string | null;
  description?: string | null;
}

export interface CertificationPayload {
  name: string;
  issuing_organization: string;
  issue_date?: string | null;
  expiration_date?: string | null;
  credential_id?: string | null;
  credential_url?: string | null;
}

export interface ProjectPayload {
  title: string;
  description?: string | null;
  project_url?: string | null;
  repository_url?: string | null;
  start_date?: string | null;
  end_date?: string | null;
  technologies?: string[] | null;
}

export interface AttachSkillPayload {
  skill_id: number;
  proficiency_level?: ProficiencyLevel | null;
  years_of_experience?: number | null;
}

export interface UpdateSkillPayload {
  proficiency_level?: ProficiencyLevel | null;
  years_of_experience?: number | null;
}

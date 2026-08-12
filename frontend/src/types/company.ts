export type CompanyVerificationStatus = 'unverified' | 'pending' | 'verified' | 'rejected';

export interface CompanyProfile {
  id: number;
  name: string;
  slug: string;
  logo_path: string | null;
  website: string | null;
  industry: string | null;
  company_size: string | null;
  founded_year: number | null;
  description: string | null;
  city: string | null;
  country: string | null;
  is_verified: boolean;
  verification_status: CompanyVerificationStatus;
  trust_score: number | null;
  contact_email: string | null;
  contact_phone: string | null;
}

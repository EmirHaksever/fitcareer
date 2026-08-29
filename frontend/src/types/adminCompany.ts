import type { CompanyProfile } from '@/types/company';

export interface PaginatedAdminCompanyList {
  items: CompanyProfile[];
  pagination: {
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
  };
}

export type CompanyVerificationAction = 'approve' | 'reject';

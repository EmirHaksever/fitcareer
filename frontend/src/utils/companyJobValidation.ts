export const MIN_JOB_DESCRIPTION_LENGTH = 100;

export const COMPANY_JOB_FORM_DEFAULTS = {
  title: '',
  description: '',
  requirements: '',
  responsibilities: '',
  category: 'engineering',
  employment_type: 'full_time',
  work_type: '',
  experience_level: null,
  city: '',
  country: 'Türkiye',
  salary_min: null,
  salary_max: null,
  salary_currency: 'TRY',
  is_salary_visible: false,
  application_deadline: null,
  contact_email: '',
  contact_phone: '',
} as const;

export interface CompanyJobValidationInput {
  title?: string | null;
  description?: string | null;
  employment_type?: string | null;
  work_type?: string | null;
  city?: string | null;
  country?: string | null;
  experience_level?: string | null;
}

export function validateCompanyJobPayload(
  input: CompanyJobValidationInput,
): Record<string, string> {
  const errors: Record<string, string> = {};
  const title = input.title?.trim() ?? '';
  const description = input.description?.trim() ?? '';
  const workType = input.work_type?.trim() ?? '';
  const city = input.city?.trim() ?? '';
  const country = input.country?.trim() ?? '';
  const employmentType = input.employment_type?.trim() ?? '';

  if (title.length < 3) {
    errors.title = 'İlan başlığı zorunludur.';
  }

  if (description.length < MIN_JOB_DESCRIPTION_LENGTH) {
    errors.description = `İş tanımı en az ${MIN_JOB_DESCRIPTION_LENGTH} karakter olmalıdır.`;
  }

  if (!employmentType) {
    errors.employment_type = 'İstihdam tipi seçilmelidir.';
  }

  if (!workType) {
    errors.work_type = 'Çalışma tipi seçilmelidir.';
  }

  if (!country) {
    errors.country = 'Ülke zorunludur.';
  }

  if (workType && workType !== 'remote' && !city) {
    errors.city = 'Uzaktan olmayan ilanlar için şehir zorunludur.';
  }

  return errors;
}

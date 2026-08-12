export const EMPLOYMENT_TYPE_OPTIONS = [
  { value: 'full_time', label: 'Tam Zamanlı' },
  { value: 'part_time', label: 'Yarı Zamanlı' },
  { value: 'contract', label: 'Sözleşmeli' },
  { value: 'internship', label: 'Staj' },
  { value: 'freelance', label: 'Freelance' },
] as const;

export const WORK_TYPE_OPTIONS = [
  { value: 'remote', label: 'Uzaktan' },
  { value: 'hybrid', label: 'Hibrit' },
  { value: 'onsite', label: 'Ofisten' },
] as const;

export const EXPERIENCE_LEVEL_OPTIONS = [
  { value: 'intern', label: 'Stajyer' },
  { value: 'entry', label: 'Junior' },
  { value: 'mid', label: 'Mid-Level' },
  { value: 'senior', label: 'Senior' },
  { value: 'lead', label: 'Lead' },
  { value: 'executive', label: 'Yönetici' },
] as const;

export const SORT_OPTIONS = [
  { value: 'published_at', label: 'En yeni' },
  { value: 'fit_score', label: 'En uygun' },
  { value: 'trust_score', label: 'Güven skoru' },
  { value: 'salary', label: 'Maaş' },
] as const;

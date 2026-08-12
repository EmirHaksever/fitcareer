export const WORK_PREFERENCE_OPTIONS = [
  { value: 'remote', label: 'Uzaktan' },
  { value: 'hybrid', label: 'Hibrit' },
  { value: 'onsite', label: 'Ofisten' },
  { value: 'any', label: 'Fark etmez' },
] as const;

export const EMPLOYMENT_TYPE_OPTIONS = [
  { value: 'full_time', label: 'Tam Zamanlı' },
  { value: 'part_time', label: 'Yarı Zamanlı' },
  { value: 'contract', label: 'Sözleşmeli' },
  { value: 'internship', label: 'Staj' },
  { value: 'freelance', label: 'Freelance' },
] as const;

export const PROFICIENCY_OPTIONS = [
  { value: 'beginner', label: 'Başlangıç' },
  { value: 'intermediate', label: 'Orta' },
  { value: 'advanced', label: 'İleri' },
  { value: 'expert', label: 'Uzman' },
] as const;

export const selectClassName =
  'h-11 w-full rounded-xl border border-surface bg-white px-3 text-sm text-ink outline-none transition focus:border-primary focus:ring-4 focus:ring-primary/10';

export const JOB_CATEGORY_OPTIONS = [
  { value: 'engineering', label: 'Mühendislik' },
  { value: 'design', label: 'Tasarım' },
  { value: 'product', label: 'Ürün' },
  { value: 'marketing', label: 'Pazarlama' },
  { value: 'sales', label: 'Satış' },
  { value: 'hr', label: 'İnsan Kaynakları' },
  { value: 'finance', label: 'Finans' },
  { value: 'operations', label: 'Operasyon' },
  { value: 'other', label: 'Diğer' },
] as const;

export const JOB_STATUS_LABELS: Record<string, string> = {
  draft: 'Taslak',
  pending_review: 'İncelemede',
  published: 'Yayında',
  expired: 'Süresi doldu',
  closed: 'Kapalı',
  flagged: 'İşaretlendi',
};

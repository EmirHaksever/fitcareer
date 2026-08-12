import { clsx, type ClassValue } from 'clsx';

export function cn(...inputs: ClassValue[]): string {
  return clsx(inputs);
}

export function getFirstName(fullName: string): string {
  return fullName.trim().split(/\s+/)[0] ?? fullName;
}

export function formatEmploymentType(value: string | null): string {
  if (!value) return 'Belirtilmemiş';

  return value
    .split('_')
    .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
    .join(' ');
}

export function formatWorkType(value: string | null): string {
  const map: Record<string, string> = {
    remote: 'Uzaktan',
    hybrid: 'Hibrit',
    onsite: 'Ofisten',
  };

  return value ? map[value] ?? value : 'Belirtilmemiş';
}

export function formatLocation(city: string | null, country: string | null): string {
  if (city && country) return `${city}, ${country}`;
  return city ?? country ?? 'Konum belirtilmemiş';
}

export function formatExperienceLevel(value: string | null): string {
  const map: Record<string, string> = {
    intern: 'Stajyer',
    entry: 'Junior',
    mid: 'Mid-Level',
    senior: 'Senior',
    lead: 'Lead',
    executive: 'Yönetici',
  };

  return value ? map[value] ?? value : 'Belirtilmemiş';
}

export function formatTrustLabel(value: string): string {
  const map: Record<string, string> = {
    verified: 'Güvenilir',
    suspicious: 'Şüpheli',
    low_trust: 'Düşük Güven',
    unrated: 'Değerlendirilmedi',
  };

  return map[value] ?? value;
}

export function formatWorkPreference(value: string | null): string {
  const map: Record<string, string> = {
    remote: 'Uzaktan',
    hybrid: 'Hibrit',
    onsite: 'Ofisten',
    any: 'Fark etmez',
  };

  return value ? map[value] ?? value : 'Belirtilmemiş';
}

export function formatProficiencyLevel(value: string | null): string {
  const map: Record<string, string> = {
    beginner: 'Başlangıç',
    intermediate: 'Orta',
    advanced: 'İleri',
    expert: 'Uzman',
  };

  return value ? map[value] ?? value : 'Belirtilmemiş';
}

export function formatDate(value: string | null): string {
  if (!value) return '—';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  return date.toLocaleDateString('tr-TR', { year: 'numeric', month: 'short' });
}

export function formatDateRange(
  start: string | null,
  end: string | null,
  isCurrent = false,
): string {
  if (!start) return '—';
  const startLabel = formatDate(start);
  if (isCurrent) return `${startLabel} – Devam ediyor`;
  if (!end) return startLabel;
  return `${startLabel} – ${formatDate(end)}`;
}

export function formatSalary(
  min: number | null,
  max: number | null,
  currency: string | null,
  visible: boolean,
): string | null {
  if (!visible) return null;

  const formatter = new Intl.NumberFormat('tr-TR', {
    style: 'currency',
    currency: currency ?? 'TRY',
    maximumFractionDigits: 0,
  });

  if (min !== null && max !== null) {
    return `${formatter.format(min)} – ${formatter.format(max)}`;
  }

  if (min !== null) return `${formatter.format(min)}+`;
  if (max !== null) return `≤ ${formatter.format(max)}`;

  return null;
}

export function formatRelativeTime(isoDate: string | null): string | null {
  if (!isoDate) return null;

  const date = new Date(isoDate);
  if (Number.isNaN(date.getTime())) return null;

  const diffMs = Date.now() - date.getTime();
  const diffMinutes = Math.floor(diffMs / 60_000);
  const diffHours = Math.floor(diffMs / 3_600_000);
  const diffDays = Math.floor(diffMs / 86_400_000);

  if (diffMinutes < 1) return 'Az önce yayınlandı';
  if (diffMinutes < 60) return `${diffMinutes} dakika önce yayınlandı`;
  if (diffHours < 24) return `${diffHours} saat önce yayınlandı`;
  if (diffDays < 7) return `${diffDays} gün önce yayınlandı`;

  return `${date.toLocaleDateString('tr-TR')} tarihinde yayınlandı`;
}

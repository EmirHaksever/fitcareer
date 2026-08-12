import type { FitSignalDetail, FitSignalKey, FitScoreDetails } from '@/types/fitScore';
import { FIT_SIGNAL_KEYS } from '@/types/fitScore';
import {
  formatExperienceLevel,
  formatWorkType,
} from '@/utils/format';

export const FIT_SIGNAL_TITLES: Record<FitSignalKey, string> = {
  required_skills: 'Gerekli yetenekler',
  preferred_skills: 'Tercih edilen yetenekler',
  experience: 'Deneyim',
  work_type: 'Çalışma şekli',
  location: 'Lokasyon',
  salary: 'Maaş',
};

export interface FitBreakdownItem {
  key: FitSignalKey;
  title: string;
  lines: string[];
  score: number | null;
}

function asStringArray(value: unknown): string[] {
  if (!Array.isArray(value)) {
    return [];
  }

  return value.filter((item): item is string => typeof item === 'string' && item.trim() !== '');
}

function scoreSummary(score: number | null): string | null {
  if (score === null) {
    return null;
  }

  if (score >= 85) return 'Çok uygun';
  if (score >= 70) return 'Uygun';
  if (score >= 50) return 'Kısmen uygun';
  return 'Düşük uyum';
}

function formatSkillSignal(signal: FitSignalDetail): string[] {
  const lines: string[] = [];
  const matched = asStringArray(signal.evidence.matched_skills);
  const missing = asStringArray(signal.evidence.missing_skills);

  matched.forEach((name) => lines.push(`${name}: Eşleşiyor`));
  missing.forEach((name) => lines.push(`${name}: Eksik`));

  if (lines.length === 0 && signal.score !== null) {
    lines.push(`Kapsama: %${signal.score}`);
  }

  return lines;
}

function formatExperienceSignal(signal: FitSignalDetail): string[] {
  if (signal.score === null) {
    return ['Deneyim verisi yetersiz'];
  }

  const lines: string[] = [];
  const summary = scoreSummary(signal.score);
  if (summary) {
    lines.push(`Deneyim: ${summary}`);
  }

  const jobLevel = signal.evidence.job_experience_level;
  const candidateYears = signal.evidence.candidate_years_of_experience;

  if (typeof jobLevel === 'string') {
    lines.push(`İlan beklentisi: ${formatExperienceLevel(jobLevel)}`);
  }

  if (typeof candidateYears === 'number') {
    lines.push(`Profiliniz: ${candidateYears} yıl`);
  }

  return lines;
}

function formatWorkTypeSignal(signal: FitSignalDetail): string[] {
  if (signal.score === null) {
    return ['Çalışma tercihi verisi yetersiz'];
  }

  const lines: string[] = [];
  const summary = scoreSummary(signal.score);
  if (summary) {
    lines.push(`Çalışma şekli: ${summary}`);
  }

  const jobWorkType = signal.evidence.job_work_type;
  const preference = signal.evidence.candidate_work_preference;

  if (typeof jobWorkType === 'string') {
    lines.push(`İlan: ${formatWorkType(jobWorkType)}`);
  }

  if (typeof preference === 'string') {
    lines.push(`Tercihiniz: ${formatWorkType(preference)}`);
  }

  return lines;
}

function formatLocationSignal(signal: FitSignalDetail): string[] {
  if (signal.score === null) {
    return ['Lokasyon verisi yetersiz'];
  }

  const reason = signal.evidence.reason;
  if (reason === 'remote_job_bypass') {
    return ['Uzaktan çalışma: Lokasyon kısıtı uygulanmadı'];
  }

  const matchType = signal.evidence.match_type;
  const labels: Record<string, string> = {
    same_city: 'Aynı şehir',
    same_country: 'Aynı ülke',
    different_country: 'Farklı ülke',
  };

  if (typeof matchType === 'string' && labels[matchType]) {
    return [`Lokasyon: ${labels[matchType]}`];
  }

  const summary = scoreSummary(signal.score);
  return summary ? [`Lokasyon: ${summary}`] : [];
}

function formatSalarySignal(signal: FitSignalDetail): string[] {
  if (signal.score === null) {
    const reason = signal.evidence.reason;
    if (reason === 'salary_not_visible') {
      return ['Maaş bilgisi ilanda gizli'];
    }

    return ['Maaş verisi yetersiz'];
  }

  const overlap = signal.evidence.overlap;
  if (overlap === false) {
    return ['Maaş beklentisi: Örtüşmüyor'];
  }

  if (overlap === true) {
    return [`Maaş beklentisi: Uyumlu (%${signal.score})`];
  }

  const summary = scoreSummary(signal.score);
  return summary ? [`Maaş: ${summary}`] : [];
}

function formatSignalLines(key: FitSignalKey, signal: FitSignalDetail): string[] {
  switch (key) {
    case 'required_skills':
    case 'preferred_skills':
      return formatSkillSignal(signal);
    case 'experience':
      return formatExperienceSignal(signal);
    case 'work_type':
      return formatWorkTypeSignal(signal);
    case 'location':
      return formatLocationSignal(signal);
    case 'salary':
      return formatSalarySignal(signal);
    default:
      return signal.score !== null ? [`Skor: %${signal.score}`] : [];
  }
}

export function buildFitBreakdown(details?: FitScoreDetails | null): FitBreakdownItem[] {
  if (!details?.signals) {
    return [];
  }

  return FIT_SIGNAL_KEYS.flatMap((key) => {
    const signal = details.signals?.[key];
    if (!signal) {
      return [];
    }

    const lines = formatSignalLines(key, signal);
    if (lines.length === 0) {
      return [];
    }

    return [
      {
        key,
        title: FIT_SIGNAL_TITLES[key],
        lines,
        score: signal.score,
      },
    ];
  });
}

export function shouldShowFitScoreBadge(
  showForRole: boolean,
  score: number | null,
  status: string | null,
): boolean {
  if (!showForRole) {
    return false;
  }

  if (score !== null) {
    return true;
  }

  return status === 'pending' || status === 'analyzing';
}

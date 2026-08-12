import type { CandidateProfile } from '@/types/candidate';
import type { ScoreBand } from '@/utils/scores';

export interface ProfileStrengthSuggestion {
  id: string;
  label: string;
  completed: boolean;
}

export function getProfileStrengthSuggestions(profile: CandidateProfile): ProfileStrengthSuggestion[] {
  return [
    { id: 'headline', label: 'Başlık ekle', completed: Boolean(profile.headline?.trim()) },
    { id: 'summary', label: 'Özet ekle', completed: Boolean(profile.summary?.trim()) },
    { id: 'location', label: 'Konum bilgisi ekle', completed: Boolean(profile.city?.trim() && profile.country?.trim()) },
    { id: 'desired_position', label: 'Hedef pozisyon belirle', completed: Boolean(profile.desired_position?.trim()) },
    { id: 'work_preference', label: 'Çalışma tercihi seç', completed: profile.work_preference !== null },
    { id: 'experience', label: 'Deneyim ekle', completed: profile.experiences.length > 0 },
    { id: 'education', label: 'Eğitim ekle', completed: profile.educations.length > 0 },
    { id: 'skills', label: 'Yetenek ekle', completed: profile.skills.length > 0 },
    { id: 'cv', label: 'CV yükle', completed: profile.has_cv },
    { id: 'linkedin', label: 'LinkedIn bağlantısı ekle', completed: Boolean(profile.linkedin_url?.trim()) },
  ];
}

export function getProfileStrengthBand(score: number): ScoreBand {
  if (score >= 85) return 'excellent';
  if (score >= 70) return 'good';
  if (score >= 50) return 'warning';
  if (score > 0) return 'danger';
  return 'neutral';
}

export function getProfileStrengthLabel(score: number): string {
  const band = getProfileStrengthBand(score);
  const labels: Record<ScoreBand, string> = {
    excellent: 'Çok İyi',
    good: 'İyi',
    warning: 'Orta',
    danger: 'Geliştirilmeli',
    neutral: 'Başlangıç',
  };
  return labels[band];
}

export function getProfileStrengthMessage(profile: CandidateProfile): string | null {
  const pending = getProfileStrengthSuggestions(profile).filter((item) => !item.completed);

  if (pending.length === 0) {
    return null;
  }

  const labels = pending.slice(0, 3).map((item) => item.label.toLowerCase());
  return `Profilini güçlendirmek için ${labels.join(', ')}.`;
}

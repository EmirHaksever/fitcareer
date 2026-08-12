import type {
  JobSkill,
  JobSkillDraft,
  JobSkillImportance,
  SyncJobSkillsPayload,
} from '@/types/companyJob';
import type { SkillCatalogItem } from '@/types/candidate';

export const SKILL_IMPORTANCE_OPTIONS = [
  { value: 'required' as const, label: 'Zorunlu' },
  { value: 'preferred' as const, label: 'Tercihli' },
];

export const SKILL_IMPORTANCE_LABELS: Record<JobSkillImportance, string> = {
  required: 'Zorunlu',
  preferred: 'Tercihli',
};

export function mapJobSkillsToDraft(skills: JobSkill[]): JobSkillDraft[] {
  return skills.map((skill) => ({
    skill_id: skill.id,
    name: skill.name,
    slug: skill.slug,
    importance: skill.importance,
  }));
}

export function isDuplicateSkill(skills: JobSkillDraft[], skillId: number): boolean {
  return skills.some((skill) => skill.skill_id === skillId);
}

export function catalogItemToDraft(
  item: SkillCatalogItem,
  importance: JobSkillImportance = 'required',
): JobSkillDraft {
  return {
    skill_id: item.id,
    name: item.name,
    slug: item.slug,
    importance,
  };
}

export type AddSkillResult =
  | { ok: true; skills: JobSkillDraft[] }
  | { ok: false; reason: 'duplicate' };

export function addSkill(skills: JobSkillDraft[], skill: JobSkillDraft): AddSkillResult {
  if (isDuplicateSkill(skills, skill.skill_id)) {
    return { ok: false, reason: 'duplicate' };
  }

  return { ok: true, skills: [...skills, skill] };
}

export function removeSkill(skills: JobSkillDraft[], skillId: number): JobSkillDraft[] {
  return skills.filter((skill) => skill.skill_id !== skillId);
}

export function updateSkillImportance(
  skills: JobSkillDraft[],
  skillId: number,
  importance: JobSkillImportance,
): JobSkillDraft[] {
  return skills.map((skill) =>
    skill.skill_id === skillId ? { ...skill, importance } : skill,
  );
}

export function buildSyncPayload(skills: JobSkillDraft[]): SyncJobSkillsPayload {
  return {
    skills: skills.map((skill) => ({
      skill_id: skill.skill_id,
      importance: skill.importance,
    })),
  };
}

export function mapSkillValidationErrors(errors: Record<string, string[]>): string {
  const messages = Object.entries(errors).flatMap(([, value]) => value);

  if (messages.length > 0) {
    return messages[0] ?? 'Yetenek doğrulama hatası.';
  }

  return 'Yetenekler kaydedilemedi.';
}

import { candidateProfileApi } from '@/api/candidate/profile';
import {
  candidateCertificationsApi,
  candidateEducationsApi,
  candidateExperiencesApi,
  candidateProjectsApi,
  candidateSkillsApi,
  skillsCatalogApi,
} from '@/api/candidate/resources';
import type {
  CandidateProfile,
  CertificationPayload,
  CvParsedData,
  EducationPayload,
  ExperiencePayload,
  ProjectPayload,
  SkillCatalogItem,
  UpdateCandidateProfilePayload,
} from '@/types/candidate';
import { extractProfileSuggestions } from '@/utils/cvProfileFill';
import {
  getSectionText,
  isWatermarkLine,
  normalizeText,
  parseCertificationsSection,
  parseEducationSection,
  parseExperienceSection,
  parseProjectsSection,
  parseSkillsSection,
} from '@/utils/cvSectionParser';
import { formatValidationErrorMessage, runImportStep } from '@/utils/importErrors';
import { sanitizePayload } from '@/utils/payload';

export interface CvImportPlan {
  profile: Partial<UpdateCandidateProfilePayload>;
  experiences: ExperiencePayload[];
  educations: EducationPayload[];
  certifications: CertificationPayload[];
  projects: ProjectPayload[];
  skillNames: string[];
}

export interface CvImportResult {
  profileUpdated: boolean;
  experiencesAdded: number;
  educationsAdded: number;
  certificationsAdded: number;
  projectsAdded: number;
  skillsAdded: number;
  skillsSkipped: string[];
  warnings: string[];
}

function formatImportError(error: unknown): string {
  return formatValidationErrorMessage(error, 'İstek başarısız oldu.');
}

function isDuplicateExperience(existing: CandidateProfile['experiences'], item: ExperiencePayload): boolean {
  const key = `${normalizeText(item.company_name)}|${normalizeText(item.position_title)}|${item.start_date}`;
  return existing.some(
    (entry) =>
      `${normalizeText(entry.company_name)}|${normalizeText(entry.position_title)}|${entry.start_date}` === key,
  );
}

function isDuplicateEducation(existing: CandidateProfile['educations'], item: EducationPayload): boolean {
  const key = `${normalizeText(item.school_name)}|${normalizeText(item.degree ?? '')}|${item.start_date}`;
  return existing.some(
    (entry) =>
      `${normalizeText(entry.school_name)}|${normalizeText(entry.degree ?? '')}|${entry.start_date}` === key,
  );
}

function isDuplicateCertification(existing: CandidateProfile['certifications'], item: CertificationPayload): boolean {
  const key = `${normalizeText(item.name)}|${normalizeText(item.issuing_organization)}`;
  return existing.some(
    (entry) => `${normalizeText(entry.name)}|${normalizeText(entry.issuing_organization)}` === key,
  );
}

function isDuplicateProject(existing: CandidateProfile['projects'], item: ProjectPayload): boolean {
  const key = normalizeText(item.title);
  return existing.some((entry) => normalizeText(entry.title) === key);
}

function normalizeSkillName(value: string): string {
  return normalizeText(value).replace(/\./g, '');
}

function matchSkill(skillName: string, catalog: SkillCatalogItem[]): SkillCatalogItem | null {
  const normalized = normalizeSkillName(skillName);
  const exact = catalog.find((item) => normalizeSkillName(item.name) === normalized);
  if (exact) return exact;

  const slugMatch = catalog.find((item) => item.slug === normalized.replace(/\s+/g, '-'));
  if (slugMatch) return slugMatch;

  if (normalized.length < 3) {
    return null;
  }

  return (
    catalog.find((item) => {
      const catalogName = normalizeSkillName(item.name);
      return catalogName === normalized;
    }) ?? null
  );
}

function buildProfileUpdatePayload(
  profile: CandidateProfile,
  suggestions: Partial<UpdateCandidateProfilePayload>,
  overwriteProfile: boolean,
): Partial<UpdateCandidateProfilePayload> {
  const payload: Partial<UpdateCandidateProfilePayload> = {};

  for (const [key, value] of Object.entries(suggestions) as [keyof UpdateCandidateProfilePayload, unknown][]) {
    if (value === null || value === undefined || value === '') continue;

    const existing = profile[key as keyof CandidateProfile];
    if (!overwriteProfile && existing !== null && existing !== undefined && existing !== '') {
      continue;
    }

    (payload as Record<string, unknown>)[key] = value;
  }

  return payload;
}

function sanitizeImportPlan(plan: CvImportPlan): CvImportPlan {
  return {
    ...plan,
    experiences: plan.experiences.filter(
      (item) =>
        item.company_name.trim().length >= 2 &&
        item.position_title.trim().length >= 2 &&
        !(item.position_title === 'Pozisyon' && item.company_name.length < 20),
    ),
    educations: plan.educations.filter(
      (item) =>
        item.school_name.trim().length >= 4 &&
        !isWatermarkLine(item.school_name) &&
        /üniversite|university|yüksek|lise|school|college|enstitü|myo|fakülte/i.test(item.school_name),
    ),
    projects: plan.projects.filter((item) => item.title.trim().length >= 2),
    skillNames: plan.skillNames.filter((name) => name.trim().length >= 2),
  };
}

export function extractCvImportPlan(parsed: CvParsedData): CvImportPlan {
  return sanitizeImportPlan({
    profile: extractProfileSuggestions(parsed),
    experiences: parseExperienceSection(getSectionText(parsed, 'experience')),
    educations: parseEducationSection(getSectionText(parsed, 'education')),
    certifications: parseCertificationsSection(getSectionText(parsed, 'certifications')),
    projects: parseProjectsSection(getSectionText(parsed, 'projects')),
    skillNames: parseSkillsSection(getSectionText(parsed, 'skills')),
  });
}

export function summarizeCvImportPlan(plan: CvImportPlan): string {
  const parts: string[] = [];

  if (Object.keys(plan.profile).length > 0) parts.push('genel bilgiler');
  if (plan.experiences.length > 0) parts.push(`${plan.experiences.length} deneyim`);
  if (plan.educations.length > 0) parts.push(`${plan.educations.length} eğitim`);
  if (plan.skillNames.length > 0) parts.push(`${plan.skillNames.length} beceri`);
  if (plan.certifications.length > 0) parts.push(`${plan.certifications.length} sertifika`);
  if (plan.projects.length > 0) parts.push(`${plan.projects.length} proje`);

  return parts.length > 0 ? parts.join(', ') : 'aktarılabilir içerik bulunamadı';
}

export function countImportableItems(profile: CandidateProfile, plan: CvImportPlan): number {
  let count = 0;

  if (Object.keys(plan.profile).length > 0) count += 1;
  count += plan.experiences.filter((item) => !isDuplicateExperience(profile.experiences, item)).length;
  count += plan.educations.filter((item) => !isDuplicateEducation(profile.educations, item)).length;
  count += plan.certifications.filter((item) => !isDuplicateCertification(profile.certifications, item)).length;
  count += plan.projects.filter((item) => !isDuplicateProject(profile.projects, item)).length;
  count += plan.skillNames.length;

  return count;
}

export async function applyCvImport(
  profile: CandidateProfile,
  parsed: CvParsedData,
  options?: { overwriteProfile?: boolean },
): Promise<CvImportResult> {
  const plan = extractCvImportPlan(parsed);
  const overwriteProfile = options?.overwriteProfile ?? false;
  const warnings: string[] = [];
  const result: CvImportResult = {
    profileUpdated: false,
    experiencesAdded: 0,
    educationsAdded: 0,
    certificationsAdded: 0,
    projectsAdded: 0,
    skillsAdded: 0,
    skillsSkipped: [],
    warnings,
  };

  const profilePayload = buildProfileUpdatePayload(profile, plan.profile, overwriteProfile);

  if (Object.keys(profilePayload).length > 0) {
    await runImportStep('Genel bilgiler', async () => {
      await candidateProfileApi.update(sanitizePayload(profilePayload));
      result.profileUpdated = true;
    }, warnings);
  }

  for (const experience of plan.experiences) {
    if (isDuplicateExperience(profile.experiences, experience)) continue;

    await runImportStep(`Deneyim: ${experience.position_title}`, async () => {
      await candidateExperiencesApi.create(sanitizePayload(experience));
      result.experiencesAdded += 1;
    }, warnings);
  }

  for (const education of plan.educations) {
    if (isDuplicateEducation(profile.educations, education)) continue;

    await runImportStep(`Eğitim: ${education.school_name}`, async () => {
      await candidateEducationsApi.create(sanitizePayload(education));
      result.educationsAdded += 1;
    }, warnings);
  }

  for (const certification of plan.certifications) {
    if (isDuplicateCertification(profile.certifications, certification)) continue;

    await runImportStep(`Sertifika: ${certification.name}`, async () => {
      await candidateCertificationsApi.create(sanitizePayload(certification));
      result.certificationsAdded += 1;
    }, warnings);
  }

  for (const project of plan.projects) {
    if (isDuplicateProject(profile.projects, project)) continue;

    await runImportStep(`Proje: ${project.title}`, async () => {
      await candidateProjectsApi.create(sanitizePayload(project));
      result.projectsAdded += 1;
    }, warnings);
  }

  if (plan.skillNames.length > 0) {
    let catalog: SkillCatalogItem[] = [];
    try {
      catalog = await skillsCatalogApi.search('', 50);
    } catch (error) {
      warnings.push(`Beceri kataloğu: ${formatImportError(error)}`);
    }

    const attachedSkillIds = new Set(profile.skills.map((skill) => skill.skill_id));

    for (const skillName of plan.skillNames) {
      const matched = matchSkill(skillName, catalog);
      if (!matched) {
        result.skillsSkipped.push(skillName);
        continue;
      }

      if (attachedSkillIds.has(matched.id)) {
        continue;
      }

      await runImportStep(`Beceri: ${skillName}`, async () => {
        await candidateSkillsApi.attach({ skill_id: matched.id });
        attachedSkillIds.add(matched.id);
        result.skillsAdded += 1;
      }, warnings);
    }
  }

  const hasChanges =
    result.profileUpdated ||
    result.experiencesAdded > 0 ||
    result.educationsAdded > 0 ||
    result.certificationsAdded > 0 ||
    result.projectsAdded > 0 ||
    result.skillsAdded > 0;

  if (!hasChanges && warnings.length > 0) {
    throw new Error(warnings.join(' '));
  }

  return result;
}

export function formatCvImportResult(result: CvImportResult): string {
  const parts: string[] = [];

  if (result.profileUpdated) parts.push('genel bilgiler güncellendi');
  if (result.experiencesAdded > 0) parts.push(`${result.experiencesAdded} deneyim eklendi`);
  if (result.educationsAdded > 0) parts.push(`${result.educationsAdded} eğitim eklendi`);
  if (result.skillsAdded > 0) parts.push(`${result.skillsAdded} beceri eklendi`);
  if (result.certificationsAdded > 0) parts.push(`${result.certificationsAdded} sertifika eklendi`);
  if (result.projectsAdded > 0) parts.push(`${result.projectsAdded} proje eklendi`);

  if (parts.length === 0 && result.warnings.length === 0) {
    return 'Yeni eklenecek içerik bulunamadı.';
  }

  if (result.warnings.length > 0) {
    parts.push(`${result.warnings.length} kayıt atlandı`);
  }

  if (parts.length === 0) {
    return result.warnings.join(' ');
  }

  return `${parts.join(', ')}.`;
}

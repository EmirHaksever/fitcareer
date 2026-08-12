import { describe, expect, it } from 'vitest';
import {
  addSkill,
  buildSyncPayload,
  catalogItemToDraft,
  isDuplicateSkill,
  mapJobSkillsToDraft,
  mapSkillValidationErrors,
  removeSkill,
  updateSkillImportance,
} from '@/utils/jobSkills';
import type { JobSkill, JobSkillDraft } from '@/types/companyJob';

const flutter: JobSkillDraft = {
  skill_id: 1,
  name: 'Flutter',
  slug: 'flutter',
  importance: 'required',
};

const dart: JobSkillDraft = {
  skill_id: 2,
  name: 'Dart',
  slug: 'dart',
  importance: 'required',
};

const firebase: JobSkillDraft = {
  skill_id: 3,
  name: 'Firebase',
  slug: 'firebase',
  importance: 'preferred',
};

describe('jobSkills utils', () => {
  it('maps backend job skills to draft items for edit hydration', () => {
    const backendSkills: JobSkill[] = [
      { id: 1, name: 'Flutter', slug: 'flutter', importance: 'required' },
      { id: 3, name: 'Firebase', slug: 'firebase', importance: 'preferred' },
    ];

    expect(mapJobSkillsToDraft(backendSkills)).toEqual([
      { skill_id: 1, name: 'Flutter', slug: 'flutter', importance: 'required' },
      { skill_id: 3, name: 'Firebase', slug: 'firebase', importance: 'preferred' },
    ]);
  });

  it('detects duplicate skills', () => {
    expect(isDuplicateSkill([flutter], 1)).toBe(true);
    expect(isDuplicateSkill([flutter], 2)).toBe(false);
  });

  it('adds a skill when not duplicate', () => {
    const result = addSkill([flutter], dart);

    expect(result.ok).toBe(true);
    if (result.ok) {
      expect(result.skills).toHaveLength(2);
    }
  });

  it('rejects duplicate skill selection', () => {
    const result = addSkill([flutter], flutter);

    expect(result.ok).toBe(false);
    if (!result.ok) {
      expect(result.reason).toBe('duplicate');
    }
  });

  it('updates required/preferred importance', () => {
    expect(updateSkillImportance([flutter], 1, 'preferred')).toEqual([
      { ...flutter, importance: 'preferred' },
    ]);
  });

  it('removes a skill from the list', () => {
    expect(removeSkill([flutter, dart, firebase], 2)).toEqual([flutter, firebase]);
  });

  it('builds bulk sync payload for save', () => {
    expect(buildSyncPayload([flutter, dart, firebase])).toEqual({
      skills: [
        { skill_id: 1, importance: 'required' },
        { skill_id: 2, importance: 'required' },
        { skill_id: 3, importance: 'preferred' },
      ],
    });
  });

  it('creates draft item from catalog selection', () => {
    expect(
      catalogItemToDraft({ id: 4, name: 'Git', slug: 'git', category: 'tools' }, 'required'),
    ).toEqual({
      skill_id: 4,
      name: 'Git',
      slug: 'git',
      importance: 'required',
    });
  });

  it('maps API validation errors to a user-facing message', () => {
    expect(
      mapSkillValidationErrors({
        'skills.0.skill_id': ['Seçilen skill geçersiz.'],
      }),
    ).toBe('Seçilen skill geçersiz.');
  });
});

import { describe, expect, it } from 'vitest';
import { DEFAULT_FIT_SCORE_WEIGHTS } from '@/types/companyJobFitScoreSettings';
import {
  areFitScoreWeightsEqual,
  canSaveFitScoreWeights,
  getSourceBadge,
  getTotalWeightLabel,
  getTotalWeightTone,
  isFitScoreSettingsEditable,
  isWeightTotalValid,
  mapFitScoreWeightValidationErrors,
  parseWeightInput,
  sumFitScoreWeights,
  updateWeightValue,
} from '@/utils/fitScoreWeights';

describe('fitScoreWeights utils', () => {
  it('sums all six weight fields', () => {
    expect(sumFitScoreWeights(DEFAULT_FIT_SCORE_WEIGHTS)).toBe(100);
  });

  it('validates total equals 100', () => {
    expect(isWeightTotalValid(100)).toBe(true);
    expect(isWeightTotalValid(99)).toBe(false);
    expect(isWeightTotalValid(101)).toBe(false);
  });

  it('disables save when total is not 100', () => {
    const invalid = { ...DEFAULT_FIT_SCORE_WEIGHTS, salary: 10 };

    expect(canSaveFitScoreWeights(invalid, true, false)).toBe(false);
  });

  it('enables save when total is 100 and form is dirty', () => {
    const changed = { ...DEFAULT_FIT_SCORE_WEIGHTS, experience: 25, salary: 0 };

    expect(canSaveFitScoreWeights(changed, true, false)).toBe(true);
  });

  it('disables save when readonly', () => {
    expect(canSaveFitScoreWeights(DEFAULT_FIT_SCORE_WEIGHTS, true, true)).toBe(false);
  });

  it('disables save when not dirty', () => {
    expect(canSaveFitScoreWeights(DEFAULT_FIT_SCORE_WEIGHTS, false, false)).toBe(false);
  });

  it('rejects negative values during parsing', () => {
    expect(parseWeightInput('-5')).toBeNull();
  });

  it('rejects decimal values during parsing', () => {
    expect(parseWeightInput('12.5')).toBeNull();
    expect(parseWeightInput('12,5')).toBeNull();
  });

  it('accepts zero weight', () => {
    expect(parseWeightInput('0')).toBe(0);
    expect(parseWeightInput('')).toBe(0);
  });

  it('accepts valid integer weights', () => {
    expect(parseWeightInput('45')).toBe(45);
    expect(parseWeightInput('100')).toBe(100);
  });

  it('returns default source badge label', () => {
    expect(getSourceBadge('default')).toEqual({
      label: 'Varsayılan ayarlar',
      tone: 'default',
    });
  });

  it('returns custom source badge label', () => {
    expect(getSourceBadge('custom')).toEqual({
      label: 'Özel ayarlar',
      tone: 'info',
    });
  });

  it('detects editable draft and pending review statuses', () => {
    expect(isFitScoreSettingsEditable('draft')).toBe(true);
    expect(isFitScoreSettingsEditable('pending_review')).toBe(true);
    expect(isFitScoreSettingsEditable('published')).toBe(false);
  });

  it('maps API validation errors to a readable message', () => {
    const message = mapFitScoreWeightValidationErrors({
      weights: ['Fit score weights must sum to exactly 100.'],
      'weights.experience': ['Weight cannot be negative.'],
    });

    expect(message).toContain('Fit score weights must sum to exactly 100.');
    expect(message).toContain('Weight cannot be negative.');
  });

  it('updates a single weight immutably', () => {
    const next = updateWeightValue(DEFAULT_FIT_SCORE_WEIGHTS, 'experience', 30);

    expect(next.experience).toBe(30);
    expect(DEFAULT_FIT_SCORE_WEIGHTS.experience).toBe(20);
  });

  it('compares weight objects for dirty state', () => {
    const changed = updateWeightValue(DEFAULT_FIT_SCORE_WEIGHTS, 'experience', 30);

    expect(areFitScoreWeightsEqual(DEFAULT_FIT_SCORE_WEIGHTS, DEFAULT_FIT_SCORE_WEIGHTS)).toBe(
      true,
    );
    expect(areFitScoreWeightsEqual(DEFAULT_FIT_SCORE_WEIGHTS, changed)).toBe(false);
  });

  it('formats total label', () => {
    expect(getTotalWeightLabel(100)).toBe('Toplam: 100 / 100');
    expect(getTotalWeightLabel(95)).toBe('Toplam: 95 / 100');
  });

  it('uses success tone only when total is 100', () => {
    expect(getTotalWeightTone(100)).toBe('success');
    expect(getTotalWeightTone(95)).toBe('danger');
  });
});

describe('fitScoreWeights UI scenarios', () => {
  it('renders six configured weight keys', () => {
    expect(Object.keys(DEFAULT_FIT_SCORE_WEIGHTS)).toHaveLength(6);
  });

  it('allows zero for salary while keeping total at 100', () => {
    const weights = {
      ...DEFAULT_FIT_SCORE_WEIGHTS,
      salary: 0,
      experience: 25,
    };

    expect(weights.salary).toBe(0);
    expect(sumFitScoreWeights(weights)).toBe(100);
    expect(canSaveFitScoreWeights(weights, true, false)).toBe(true);
  });

  it('simulates successful save by syncing saved weights and source', () => {
    let saved = { ...DEFAULT_FIT_SCORE_WEIGHTS };
    let source: 'default' | 'custom' = 'default';

    const next = updateWeightValue(saved, 'required_skills', 50);
    saved = next;
    source = 'custom';

    expect(source).toBe('custom');
    expect(areFitScoreWeightsEqual(saved, next)).toBe(true);
  });
});

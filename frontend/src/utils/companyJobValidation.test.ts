import { describe, expect, it } from 'vitest';
import { COMPANY_JOB_FORM_DEFAULTS, validateCompanyJobPayload } from '@/utils/companyJobValidation';

const validJuniorIstanbulJob = {
  title: 'Junior Backend Developer',
  description:
    'Istanbul ofisinde junior backend geliştirici arıyoruz. Laravel, MySQL ve temel API tasarımı üzerine çalışacak, mentorluk alacak bir ekip arkadaşı arıyoruz.',
  employment_type: 'full_time',
  work_type: 'onsite',
  city: 'İstanbul',
  country: 'Türkiye',
  experience_level: 'entry',
};

describe('company job posting quality gates', () => {
  it('does not silently default to mid-level or remote', () => {
    expect(COMPANY_JOB_FORM_DEFAULTS.experience_level).toBeNull();
    expect(COMPANY_JOB_FORM_DEFAULTS.work_type).toBe('');
    expect(COMPANY_JOB_FORM_DEFAULTS.city).toBe('');
  });

  it('rejects a one-character description', () => {
    const errors = validateCompanyJobPayload({
      ...validJuniorIstanbulJob,
      description: 'x',
    });

    expect(errors.description).toBeDefined();
  });

  it('rejects onsite jobs without a city', () => {
    const errors = validateCompanyJobPayload({
      ...validJuniorIstanbulJob,
      city: '',
      work_type: 'onsite',
    });

    expect(errors.city).toBeDefined();
  });

  it('accepts remote jobs without a city', () => {
    const errors = validateCompanyJobPayload({
      ...validJuniorIstanbulJob,
      work_type: 'remote',
      city: '',
    });

    expect(errors.city).toBeUndefined();
  });

  it('accepts a junior Istanbul job without forcing mid-level or remote', () => {
    const errors = validateCompanyJobPayload(validJuniorIstanbulJob);

    expect(errors).toEqual({});
  });

  it('does not require experience level', () => {
    const errors = validateCompanyJobPayload({
      ...validJuniorIstanbulJob,
      experience_level: null,
    });

    expect(errors.experience_level).toBeUndefined();
    expect(errors).toEqual({});
  });
});

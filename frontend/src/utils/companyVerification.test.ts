import { describe, expect, it } from 'vitest';
import {
  canRequestCompanyVerification,
  companyVerificationCopy,
  companyVerificationHeadline,
  resolveCompanySettingsView,
} from '@/utils/companyVerification';

describe('companyVerification', () => {
  it('does not treat unverified as pending', () => {
    expect(companyVerificationHeadline('unverified')).toBe(
      'Şirket doğrulaması henüz başlatılmadı.',
    );
    expect(companyVerificationHeadline(null)).toBe('Şirket doğrulaması henüz başlatılmadı.');
    expect(companyVerificationHeadline('pending')).toBe('Doğrulama talebiniz inceleniyor.');
    expect(companyVerificationCopy('unverified').title).toBe('Şirket Doğrulaması');
    expect(companyVerificationCopy('unverified').body).toBe('Şirketiniz henüz doğrulanmadı.');
    expect(companyVerificationCopy('unverified').body).not.toContain('bekleniyor');
    expect(companyVerificationCopy('pending').title).toBe('Doğrulama İnceleniyor');
    expect(companyVerificationCopy('verified').title).toBe('Doğrulanmış Şirket');
  });

  it('describes verified and rejected states honestly', () => {
    expect(companyVerificationHeadline('verified')).toBe('Şirketiniz doğrulandı.');
    expect(companyVerificationHeadline('rejected')).toBe('Doğrulama talebiniz reddedildi.');
  });

  it('allows a verification request only for unverified or rejected companies', () => {
    expect(canRequestCompanyVerification('unverified')).toBe(true);
    expect(canRequestCompanyVerification('rejected')).toBe(true);
    expect(canRequestCompanyVerification('pending')).toBe(false);
    expect(canRequestCompanyVerification('verified')).toBe(false);
  });

  it('maps settings page loading and error states', () => {
    expect(resolveCompanySettingsView({ isLoading: true, isError: false, profile: null })).toEqual({
      kind: 'loading',
    });
    expect(resolveCompanySettingsView({ isLoading: false, isError: true, profile: null })).toEqual({
      kind: 'error',
    });
  });

  it('exposes password and logout actions once the profile is ready', () => {
    const unverified = resolveCompanySettingsView({
      isLoading: false,
      isError: false,
      profile: { verification_status: 'unverified', is_verified: false },
    });

    expect(unverified).toMatchObject({
      kind: 'ready',
      headline: 'Şirket doğrulaması henüz başlatılmadı.',
      canRequest: true,
      showVerifiedBadge: false,
      showPasswordForm: true,
      showLogout: true,
    });
  });

  it('shows the verified badge only when is_verified is true', () => {
    const pending = resolveCompanySettingsView({
      isLoading: false,
      isError: false,
      profile: { verification_status: 'pending', is_verified: false },
    });
    const verified = resolveCompanySettingsView({
      isLoading: false,
      isError: false,
      profile: { verification_status: 'verified', is_verified: true },
    });
    const rejected = resolveCompanySettingsView({
      isLoading: false,
      isError: false,
      profile: { verification_status: 'rejected', is_verified: false },
    });

    expect(pending).toMatchObject({
      kind: 'ready',
      headline: 'Doğrulama talebiniz inceleniyor.',
      canRequest: false,
      showVerifiedBadge: false,
    });
    expect(verified).toMatchObject({
      kind: 'ready',
      headline: 'Şirketiniz doğrulandı.',
      canRequest: false,
      showVerifiedBadge: true,
    });
    expect(rejected).toMatchObject({
      kind: 'ready',
      headline: 'Doğrulama talebiniz reddedildi.',
      canRequest: true,
      showVerifiedBadge: false,
    });
  });
});

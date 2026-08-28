export type CompanyVerificationStatus = 'unverified' | 'pending' | 'verified' | 'rejected';

export function companyVerificationHeadline(status: CompanyVerificationStatus | null | undefined): string {
  switch (status) {
    case 'pending':
      return 'Doğrulama talebiniz inceleniyor.';
    case 'verified':
      return 'Şirketiniz doğrulandı.';
    case 'rejected':
      return 'Doğrulama talebiniz reddedildi.';
    default:
      return 'Şirket doğrulaması henüz başlatılmadı.';
  }
}

export type CompanyVerificationCopy = {
  title: string;
  body: string;
  ctaLabel: string | null;
};

export function companyVerificationCopy(
  status: CompanyVerificationStatus | null | undefined,
): CompanyVerificationCopy {
  switch (status) {
    case 'pending':
      return {
        title: 'Doğrulama İnceleniyor',
        body: 'Talebiniz şu anda inceleniyor.',
        ctaLabel: null,
      };
    case 'verified':
      return {
        title: 'Doğrulanmış Şirket',
        body: 'Şirket bilgileriniz doğrulandı.',
        ctaLabel: null,
      };
    case 'rejected':
      return {
        title: 'Doğrulama Reddedildi',
        body: 'Doğrulama talebiniz reddedildi.',
        ctaLabel: 'Doğrulama Talep Et',
      };
    default:
      return {
        title: 'Şirket Doğrulaması',
        body: 'Şirketiniz henüz doğrulanmadı.',
        ctaLabel: 'Doğrulama Talep Et',
      };
  }
}

export function canRequestCompanyVerification(
  status: CompanyVerificationStatus | null | undefined,
): boolean {
  return status === 'unverified' || status === 'rejected' || status == null;
}

export type CompanySettingsView =
  | { kind: 'loading' }
  | { kind: 'error' }
  | {
      kind: 'ready';
      headline: string;
      title: string;
      body: string;
      ctaLabel: string | null;
      canRequest: boolean;
      showVerifiedBadge: boolean;
      showPasswordForm: true;
      showLogout: true;
    };

export function resolveCompanySettingsView(input: {
  isLoading: boolean;
  isError: boolean;
  profile: {
    verification_status?: CompanyVerificationStatus | null;
    is_verified?: boolean;
  } | null;
}): CompanySettingsView {
  if (input.isLoading) {
    return { kind: 'loading' };
  }

  if (input.isError || !input.profile) {
    return { kind: 'error' };
  }

  const copy = companyVerificationCopy(input.profile.verification_status);

  return {
    kind: 'ready',
    headline: companyVerificationHeadline(input.profile.verification_status),
    title: copy.title,
    body: copy.body,
    ctaLabel: copy.ctaLabel,
    canRequest: canRequestCompanyVerification(input.profile.verification_status),
    showVerifiedBadge: input.profile.is_verified === true,
    showPasswordForm: true,
    showLogout: true,
  };
}

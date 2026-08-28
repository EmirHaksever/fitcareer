export const COMPANY_SETTINGS_PATH = '/company/settings';
export const COMPANY_POST_LOGOUT_PATH = '/login';

export function companyPublicJobPath(
  status: string | null | undefined,
  slug: string | null | undefined,
): string | null {
  if (status !== 'published' || !slug) {
    return null;
  }

  return `/jobs/${slug}`;
}

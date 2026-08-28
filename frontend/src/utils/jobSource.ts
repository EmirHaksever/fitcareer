export interface JobSourceProvider {
  id: number;
  name: string;
  type: string;
}

export interface JobSourceFields {
  source?: string;
  source_company_name?: string | null;
  source_provider?: JobSourceProvider | null;
  company?: {
    name?: string;
    is_verified?: boolean;
  } | null;
  external_url?: string | null;
}

const SOURCE_LABELS: Record<string, string> = {
  internal: 'Doğrudan işveren ilanı',
  'kariyer.net': 'Kariyer.net',
  'kariyer-net': 'Kariyer.net',
  remotive: 'Remotive',
};

export function isExternalJob(job: Pick<JobSourceFields, 'source'>): boolean {
  return job.source === 'scraped';
}

export function formatJobSourceLabel(job: JobSourceFields): string {
  if (job.source === 'internal' || !job.source) {
    return SOURCE_LABELS.internal;
  }

  const providerName = job.source_provider?.name?.trim();

  if (providerName) {
    const normalized = providerName.toLowerCase();

    if (SOURCE_LABELS[normalized]) {
      return SOURCE_LABELS[normalized];
    }

    if (normalized.includes('kariyer')) {
      return SOURCE_LABELS['kariyer.net'];
    }

    if (normalized.includes('remotive')) {
      return SOURCE_LABELS.remotive;
    }

    return providerName;
  }

  if (job.source === 'scraped') {
    return 'Dış Kaynak';
  }

  return SOURCE_LABELS.internal;
}

/** Uppercase badge label for job cards and detail headers. */
export function formatJobSourceBadgeLabel(job: JobSourceFields): string {
  const label = formatJobSourceLabel(job);

  if (label === 'Doğrudan işveren ilanı') {
    return 'DOĞRUDAN İŞVEREN İLANI';
  }

  if (label === 'Dış Kaynak') {
    return 'DIŞ KAYNAK';
  }

  return label.toUpperCase();
}

export function isVerifiedCompany(job: Pick<JobSourceFields, 'company'>): boolean {
  return job.company?.is_verified === true;
}

export function isInternalJob(job: Pick<JobSourceFields, 'source'>): boolean {
  return !isExternalJob(job);
}

export function getJobCompanyName(job: JobSourceFields): string {
  const companyName = job.company?.name?.trim();

  if (companyName) {
    return companyName;
  }

  if (isExternalJob(job)) {
    const sourceCompanyName = job.source_company_name?.trim();

    if (sourceCompanyName) {
      return sourceCompanyName;
    }

    return 'Şirket belirtilmemiş';
  }

  return 'Şirket bilgisi yok';
}

export function getExternalJobUrl(job: Pick<JobSourceFields, 'external_url'>): string | null {
  const url = job.external_url?.trim();

  if (!url) {
    return null;
  }

  try {
    const parsed = new URL(url);

    if (parsed.protocol !== 'http:' && parsed.protocol !== 'https:') {
      return null;
    }

    return parsed.toString();
  } catch {
    return null;
  }
}

export function openExternalJobUrl(url: string): void {
  if (typeof window === 'undefined') {
    return;
  }

  window.open(url, '_blank', 'noopener,noreferrer');
}

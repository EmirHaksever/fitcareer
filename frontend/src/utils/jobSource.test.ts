import { describe, expect, it, vi } from 'vitest';
import {
  formatJobSourceBadgeLabel,
  formatJobSourceLabel,
  getExternalJobUrl,
  getJobCompanyName,
  isExternalJob,
  openExternalJobUrl,
} from '@/utils/jobSource';

describe('jobSource utils', () => {
  it('maps internal jobs to FitCareer', () => {
    expect(formatJobSourceLabel({ source: 'internal' })).toBe('FitCareer');
    expect(isExternalJob({ source: 'internal' })).toBe(false);
  });

  it('maps scraped provider names to user-facing labels', () => {
    expect(
      formatJobSourceLabel({
        source: 'scraped',
        source_provider: { id: 1, name: 'Kariyer.net', type: 'scraper' },
      }),
    ).toBe('Kariyer.net');

    expect(
      formatJobSourceLabel({
        source: 'scraped',
        source_provider: { id: 2, name: 'Remotive', type: 'api_integration' },
      }),
    ).toBe('Remotive');
  });

  it('falls back to external source label for unknown providers', () => {
    expect(
      formatJobSourceLabel({
        source: 'scraped',
        source_provider: { id: 3, name: 'Unknown Board', type: 'scraper' },
      }),
    ).toBe('Unknown Board');
  });

  it('renders uppercase badge labels from backend provider names', () => {
    expect(
      formatJobSourceBadgeLabel({
        source: 'scraped',
        source_provider: { id: 1, name: 'Kariyer.net', type: 'scraper' },
      }),
    ).toBe('KARIYER.NET');

    expect(
      formatJobSourceBadgeLabel({
        source: 'internal',
      }),
    ).toBe('FITCAREER');

    expect(
      formatJobSourceBadgeLabel({
        source: 'scraped',
      }),
    ).toBe('DIŞ KAYNAK');
  });

  it('uses source_company_name for external jobs when company relation is empty', () => {
    expect(
      getJobCompanyName({
        source: 'scraped',
        company: null,
        source_company_name: 'Fonet Bilgi Teknolojileri A.Ş.',
      }),
    ).toBe('Fonet Bilgi Teknolojileri A.Ş.');
  });

  it('prefers company.name over source_company_name', () => {
    expect(
      getJobCompanyName({
        source: 'scraped',
        company: { name: 'Linked Company' },
        source_company_name: 'External Name',
      }),
    ).toBe('Linked Company');
  });

  it('returns fallback when external company data is missing', () => {
    expect(
      getJobCompanyName({
        source: 'scraped',
        company: null,
        source_company_name: null,
      }),
    ).toBe('Şirket belirtilmemiş');
  });

  it('validates external job urls and opens them safely', () => {
    expect(getExternalJobUrl({ external_url: 'https://www.kariyer.net/is-ilani/example-123' })).toBe(
      'https://www.kariyer.net/is-ilani/example-123',
    );
    expect(getExternalJobUrl({ external_url: 'javascript:alert(1)' })).toBeNull();
    expect(getExternalJobUrl({ external_url: null })).toBeNull();

    const openMock = vi.fn();
    vi.stubGlobal('window', { open: openMock });
    openExternalJobUrl('https://remotive.com/remote-jobs/example');
    expect(openMock).toHaveBeenCalledWith('https://remotive.com/remote-jobs/example', '_blank', 'noopener,noreferrer');
    vi.unstubAllGlobals();
  });
});

import type { JobSearchParams, JobSortValue } from '@/types/api';

const SORT_VALUES: JobSortValue[] = ['published_at', 'salary', 'trust_score', 'fit_score'];

export const DEFAULT_JOB_SEARCH: JobSearchParams = {
  sort: 'published_at',
  page: 1,
  per_page: 10,
};

export function parseJobSearchParams(searchParams: URLSearchParams): JobSearchParams {
  const parseNumber = (key: string): number | undefined => {
    const value = searchParams.get(key);
    if (!value) return undefined;
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : undefined;
  };

  const sort = searchParams.get('sort');
  const parsedSort = SORT_VALUES.includes(sort as JobSortValue) ? (sort as JobSortValue) : DEFAULT_JOB_SEARCH.sort;

  return {
    keyword: searchParams.get('keyword') || undefined,
    location: searchParams.get('location') || undefined,
    category: searchParams.get('category') || undefined,
    employment_type: searchParams.get('employment_type') || undefined,
    work_type: searchParams.get('work_type') || undefined,
    experience_level: searchParams.get('experience_level') || undefined,
    min_trust_score: parseNumber('min_trust_score'),
    min_fit_score: parseNumber('min_fit_score'),
    sort: parsedSort,
    page: parseNumber('page') ?? DEFAULT_JOB_SEARCH.page,
    per_page: parseNumber('per_page') ?? DEFAULT_JOB_SEARCH.per_page,
  };
}

export function buildJobSearchParams(params: JobSearchParams): URLSearchParams {
  const next = new URLSearchParams();

  Object.entries(params).forEach(([key, value]) => {
    if (value === undefined || value === null || value === '') return;
    next.set(key, String(value));
  });

  return next;
}

export function countActiveFilters(params: JobSearchParams): number {
  const filterKeys: (keyof JobSearchParams)[] = [
    'location',
    'category',
    'employment_type',
    'work_type',
    'experience_level',
    'min_trust_score',
    'min_fit_score',
  ];

  return filterKeys.filter((key) => params[key] !== undefined && params[key] !== '').length;
}

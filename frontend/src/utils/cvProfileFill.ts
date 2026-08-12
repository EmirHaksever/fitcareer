import type { CvParsedData, UpdateCandidateProfilePayload } from '@/types/candidate';

function normalizeUrl(match: string): string | null {
  const trimmed = match.trim().replace(/[),.;]+$/, '');
  if (!trimmed) return null;

  try {
    const url = new URL(trimmed.startsWith('http') ? trimmed : `https://${trimmed}`);
    if (!['http:', 'https:'].includes(url.protocol)) {
      return null;
    }

    if (/linkedin\.com$/i.test(url.hostname) && !url.pathname.includes('/in/')) {
      return null;
    }

    if (/github\.com$/i.test(url.hostname) && url.pathname === '/') {
      return null;
    }

    if (/youtube\.com$/i.test(url.hostname) || /youtu\.be$/i.test(url.hostname)) {
      return null;
    }

    if (/^(gmail|hotmail|outlook|yahoo)\./i.test(url.hostname)) {
      return null;
    }

    return url.toString();
  } catch {
    return null;
  }
}

function extractLinkedinUrl(text: string): string | null {
  const match = text.match(/linkedin\.com\/in\/([\w%-]+(?: [\w%-]+)*)/i);
  if (!match) return null;
  return normalizeUrl(`https://www.linkedin.com/in/${match[1].replace(/\s+/g, '')}`);
}

function extractGithubUrl(text: string): string | null {
  return extractUrl(text, /(?:https?:\/\/)?(?:www\.)?github\.com\/[A-Za-z0-9_-]+/i);
}

function extractUrl(text: string, pattern: RegExp): string | null {
  const match = text.match(pattern);
  if (!match) return null;
  return normalizeUrl(match[0]);
}

function extractLocation(text: string): { city?: string; country?: string } {
  const lines = text
    .split('\n')
    .map((line) => line.trim())
    .filter(Boolean);

  for (const line of lines) {
    if (line.includes('@') || /linkedin|github|http/i.test(line)) {
      continue;
    }

    const commaParts = line.split(',').map((part) => part.trim()).filter(Boolean);
    if (commaParts.length >= 2) {
      return { city: commaParts[0], country: commaParts[commaParts.length - 1] };
    }
  }

  return {};
}

export function extractProfileSuggestions(parsed: CvParsedData): Partial<UpdateCandidateProfilePayload> {
  const sections = parsed.sections ?? {};
  const contactText = [sections.contact, parsed.text].filter(Boolean).join('\n');
  const result: Partial<UpdateCandidateProfilePayload> = {};

  const summary = sections.summary?.trim();
  if (summary) {
    result.summary = summary.slice(0, 5000);
    const firstLine = summary.split('\n').find((line) => line.trim())?.trim();
    if (firstLine) {
      result.headline = firstLine.slice(0, 255);
    }
  }

  const linkedin = extractLinkedinUrl(contactText);
  if (linkedin) result.linkedin_url = linkedin;

  const github = extractGithubUrl(contactText);
  if (github) result.github_url = github;

  const portfolio = extractUrl(
    contactText,
    /(?:https?:\/\/)?(?:www\.)?(?!linkedin\.com|github\.com|youtube\.com|youtu\.be)[a-z0-9.-]+\.[a-z]{2,}(?:\/[^\s?#]*)?/i,
  );
  if (portfolio) {
    result.portfolio_url = portfolio;
  }

  const location = extractLocation(sections.contact ?? '');
  if (location.city) result.city = location.city;
  if (location.country) result.country = location.country;

  return result;
}

export function mergeProfileSuggestions(
  current: UpdateCandidateProfilePayload,
  suggestions: Partial<UpdateCandidateProfilePayload>,
  overwrite = false,
): UpdateCandidateProfilePayload {
  const merged = { ...current };

  for (const [key, value] of Object.entries(suggestions) as [keyof UpdateCandidateProfilePayload, unknown][]) {
    if (value === null || value === undefined || value === '') continue;
    const existing = merged[key];
    if (overwrite || existing === null || existing === undefined || existing === '') {
      (merged as Record<string, unknown>)[key] = value;
    }
  }

  return merged;
}

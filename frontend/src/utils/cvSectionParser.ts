import type {
  CertificationPayload,
  EducationPayload,
  ExperiencePayload,
  ProjectPayload,
} from '@/types/candidate';

const MONTHS: Record<string, string> = {
  jan: '01',
  january: '01',
  ocak: '01',
  feb: '02',
  february: '02',
  subat: '02',
  şubat: '02',
  mar: '03',
  march: '03',
  mart: '03',
  apr: '04',
  april: '04',
  nisan: '04',
  may: '05',
  mayis: '05',
  mayıs: '05',
  jun: '06',
  june: '06',
  haziran: '06',
  jul: '07',
  july: '07',
  temmuz: '07',
  aug: '08',
  august: '08',
  agustos: '08',
  ağustos: '08',
  sep: '09',
  sept: '09',
  september: '09',
  eylul: '09',
  eylül: '09',
  oct: '10',
  october: '10',
  ekim: '10',
  nov: '11',
  november: '11',
  kasim: '11',
  kasım: '11',
  dec: '12',
  december: '12',
  aralik: '12',
  aralık: '12',
};

const CURRENT_TOKENS = /^(present|current|ongoing|devam|günümüz|gunumuz|şimdi|simdi|now)$/i;

interface ParsedBlock {
  headerLines: string[];
  dateLine: string | null;
  bodyLines: string[];
}

export function normalizeText(value: string): string {
  return value.trim().toLowerCase().replace(/\s+/g, ' ');
}

function cleanLine(line: string): string {
  return line.replace(/^[-*•●]\s*/, '').trim();
}

function parseDatePart(part: string): string | null {
  const trimmed = cleanLine(part);
  if (!trimmed) return null;

  if (CURRENT_TOKENS.test(trimmed)) {
    return null;
  }

  const slashMatch = trimmed.match(/^(\d{1,2})[/.-](\d{4})$/);
  if (slashMatch) {
    const month = slashMatch[1].padStart(2, '0');
    return `${slashMatch[2]}-${month}-01`;
  }

  const monthYearMatch = trimmed.match(/^([a-zçğıöşü.]+)\s+(\d{4})$/i);
  if (monthYearMatch) {
    const month = MONTHS[monthYearMatch[1].toLowerCase().replace(/\./g, '')];
    if (month) {
      return `${monthYearMatch[2]}-${month}-01`;
    }
  }

  const yearOnlyMatch = trimmed.match(/^(\d{4})$/);
  if (yearOnlyMatch) {
    return `${yearOnlyMatch[1]}-01-01`;
  }

  return null;
}

export function parseDateRange(line: string): {
  start_date: string;
  end_date: string | null;
  is_current: boolean;
} | null {
  const cleaned = cleanLine(line).replace(/(\p{L}+)\s*(\d{4})/gu, '$1 $2');
  const match = cleaned.match(/^(.+?)\s*[-–—]\s*(.+)$/);
  if (!match) return null;

  const start = parseDatePart(match[1]);
  const endPart = match[2].trim();
  const isCurrent = CURRENT_TOKENS.test(endPart);
  const end = isCurrent ? null : parseDatePart(endPart);

  if (!start && !end && !isCurrent) {
    return null;
  }

  return {
    start_date: start ?? end ?? `${new Date().getFullYear()}-01-01`,
    end_date: isCurrent ? null : end,
    is_current: isCurrent,
  };
}

export function isDateRangeLine(line: string): boolean {
  return /\d{4}/.test(line) && /[-–—]/.test(line) && parseDateRange(line) !== null;
}

export function splitSectionBlocks(text: string): ParsedBlock[] {
  const lines = text
    .split('\n')
    .map(cleanLine)
    .filter(Boolean);

  if (lines.length === 0) {
    return [];
  }

  const dateIndices = lines
    .map((line, index) => (isDateRangeLine(line) ? index : -1))
    .filter((index) => index >= 0);

  if (dateIndices.length === 0) {
    return [{ headerLines: lines, dateLine: null, bodyLines: [] }];
  }

  const blocks: ParsedBlock[] = [];
  let cursor = 0;

  for (let index = 0; index < dateIndices.length; index += 1) {
    const dateLineIndex = dateIndices[index];
    const nextDateLineIndex = dateIndices[index + 1] ?? lines.length;
    const headerLines = lines.slice(cursor, dateLineIndex);
    const between = lines.slice(dateLineIndex + 1, nextDateLineIndex);

    let bodyLines = between;
    let nextCursor = nextDateLineIndex;

    if (index < dateIndices.length - 1 && between.length > 0) {
      const nextHeaderSize = between.length >= 2 ? 2 : 1;
      bodyLines = between.slice(0, Math.max(0, between.length - nextHeaderSize));
      nextCursor = nextDateLineIndex - nextHeaderSize;
    }

    blocks.push({
      headerLines,
      dateLine: lines[dateLineIndex],
      bodyLines,
    });

    cursor = nextCursor;
  }

  if (cursor < lines.length) {
    const trailing = lines.slice(cursor);
    if (trailing.length > 0) {
      blocks.push({ headerLines: trailing, dateLine: null, bodyLines: [] });
    }
  }

  return blocks;
}

function extractYearFallback(text: string): string | null {
  const match = text.match(/\b(19|20)\d{2}\b/);
  return match ? `${match[0]}-01-01` : null;
}

function parseHeaderPair(headerLines: string[]): {
  primary: string;
  secondary: string;
  tertiary?: string;
} {
  if (headerLines.length === 0) {
    return { primary: '', secondary: '' };
  }

  if (headerLines.length === 1) {
    const line = headerLines[0];
    const atMatch = line.match(/^(.+?)\s+(?:at|@|–|—|-)\s+(.+)$/i);
    if (atMatch) {
      return { primary: atMatch[1].trim(), secondary: atMatch[2].trim() };
    }

    const pipeParts = line.split('|').map((part) => part.trim()).filter(Boolean);
    if (pipeParts.length >= 2) {
      return {
        primary: pipeParts[0],
        secondary: pipeParts[1],
        tertiary: pipeParts[2],
      };
    }

    const commaParts = line.split(',').map((part) => part.trim()).filter(Boolean);
    if (commaParts.length >= 2) {
      return { primary: commaParts[0], secondary: commaParts.slice(1).join(', ') };
    }

    return { primary: line, secondary: '' };
  }

  if (headerLines.length >= 2) {
    const secondaryLine = headerLines[1];
    const pipeParts = secondaryLine.split('|').map((part) => part.trim()).filter(Boolean);
    if (pipeParts.length >= 2) {
      return {
        primary: headerLines[0],
        secondary: pipeParts[0],
        tertiary: pipeParts[1] ?? headerLines[2],
      };
    }

    return {
      primary: headerLines[0],
      secondary: secondaryLine,
      tertiary: headerLines[2],
    };
  }

  return { primary: '', secondary: '' };
}

export function parseExperienceSection(text: string): ExperiencePayload[] {
  const items: ExperiencePayload[] = [];

  for (const block of splitSectionBlocks(text)) {
    const { primary, secondary, tertiary } = parseHeaderPair(block.headerLines);
    const dates = block.dateLine ? parseDateRange(block.dateLine) : null;
    const startDate = dates?.start_date ?? extractYearFallback(block.headerLines.join(' '));
    if (!startDate || !primary) continue;

    const companyName = secondary || primary;
    const positionTitle = secondary ? primary : 'Pozisyon';
    const location = tertiary ?? null;
    const description = block.bodyLines.join('\n').trim() || null;

    items.push({
      company_name: companyName.slice(0, 255),
      position_title: positionTitle.slice(0, 255),
      location: location ? location.slice(0, 255) : null,
      employment_type: null,
      is_current: dates?.is_current ?? !dates?.end_date,
      start_date: startDate,
      end_date: dates?.is_current || !dates?.end_date ? null : dates.end_date,
      description: description ? description.slice(0, 5000) : null,
    });
  }

  return items;
}

export function parseEducationSection(text: string): EducationPayload[] {
  const items: EducationPayload[] = [];

  for (const block of splitSectionBlocks(text)) {
    const { primary, secondary } = parseHeaderPair(block.headerLines);
    if (!primary || isWatermarkLine(primary)) continue;
    const dates = block.dateLine ? parseDateRange(block.dateLine) : null;
    const startDate = dates?.start_date ?? extractYearFallback(block.headerLines.join(' '));
    if (!startDate || !primary) continue;

    const degreeParts = secondary.split(',').map((part) => part.trim()).filter(Boolean);
    const description = block.bodyLines.join('\n').trim() || null;

    items.push({
      school_name: primary.slice(0, 255),
      degree: degreeParts[0]?.slice(0, 255) ?? null,
      field_of_study: degreeParts[1]?.slice(0, 255) ?? (secondary ? secondary.slice(0, 255) : null),
      start_date: startDate,
      end_date: dates?.is_current || !dates?.end_date ? null : dates.end_date,
      is_current: dates?.is_current ?? !dates?.end_date,
      grade: null,
      description: description ? description.slice(0, 5000) : null,
    });
  }

  return items;
}

export function parseSkillsSection(text: string): string[] {
  const normalized = text.replace(/\r?\n/g, ', ');
  const values = normalized
    .split(/[,;|•·]/)
    .map((item) => cleanLine(item))
    .filter((item) => item.length >= 2 && item.length <= 80);

  return [...new Set(values)];
}

function normalizeHeading(line: string): string {
  return line.toLocaleLowerCase('tr-TR').normalize('NFC').trim();
}

export function isWatermarkLine(line: string): boolean {
  const normalized = normalizeHeading(line);
  return normalized.includes('özgeçmiş') && normalized.includes('indirilmiştir');
}
const SECTION_HEADING_PATTERNS: Record<string, RegExp[]> = {
  experience: [
    /^(work\s+)?experience$/i,
    /^employment\s+history$/i,
    /^professional\s+experience$/i,
    /^iş\s+deneyimleri?$/i,
    /^deneyim$/i,
  ],
  education: [
    /^education$/i,
    /^academic\s+background$/i,
    /^eğitim(\s+bilgileri)?$/i,
  ],
  skills: [/^(technical\s+)?skills$/i, /^competencies$/i, /^yetenekler$/i, /^beceriler$/i],
  certifications: [/^certifications?$/i, /^licenses?$/i, /^sertifikalar$/i],
  projects: [/^projects?$/i, /^portfolios?$/i, /^projeler$/i],
  summary: [/^(professional\s+)?summary$/i, /^profile$/i, /^objective$/i, /^özet$/i, /^hakkımda$/i],
  contact: [/^contact(\s+info)?$/i, /^iletişim$/i],
};

function extractSectionFromText(text: string, sectionKey: string): string {
  const lines = text.split('\n').map((line) => line.trim()).filter((line) => line && !isWatermarkLine(line));
  let capturing = false;
  const buffer: string[] = [];

  for (const line of lines) {
    const normalized = normalizeHeading(line);
    const matchedSection = Object.entries(SECTION_HEADING_PATTERNS).find(([, headings]) =>
      headings.some((pattern) => pattern.test(normalized)),
    );

    if (matchedSection) {
      if (matchedSection[0] === sectionKey) {
        capturing = true;
        continue;
      }

      if (capturing) {
        break;
      }
    }

    if (capturing) {
      buffer.push(line);
    }
  }

  return buffer.join('\n').trim();
}

export function getSectionText(parsed: { text: string; sections: Record<string, string> }, key: string): string {
  return parsed.sections[key]?.trim() || extractSectionFromText(parsed.text, key);
}

export function parseCertificationsSection(text: string): CertificationPayload[] {
  const items: CertificationPayload[] = [];

  for (const block of splitSectionBlocks(text)) {
    const lines = [...block.headerLines, ...block.bodyLines].filter(Boolean);
    if (lines.length === 0) continue;

    const name = lines[0];
    const secondLine = lines[1] ?? '';
    const orgParts = secondLine.split(',').map((part) => part.trim()).filter(Boolean);
    const issueDate =
      (block.dateLine ? parseDateRange(block.dateLine)?.start_date : null) ??
      extractYearFallback(lines.join(' '));

    items.push({
      name: name.slice(0, 255),
      issuing_organization: (orgParts[0] || 'Belirtilmedi').slice(0, 255),
      issue_date: issueDate,
      expiration_date: null,
      credential_id: null,
      credential_url: null,
    });
  }

  return items;
}

function parseTechnologies(line: string): string[] | null {
  const match = line.match(/^(?:tech(?:nologies)?|stack|tools|teknolojiler)\s*:\s*(.+)$/i);
  if (!match) return null;
  return match[1]
    .split(/[,;|]/)
    .map((item) => item.trim())
    .filter(Boolean);
}

export function parseProjectsSection(text: string): ProjectPayload[] {
  const items: ProjectPayload[] = [];

  for (const block of splitSectionBlocks(text)) {
    const title = block.headerLines[0];
    if (!title) continue;

    const dates = block.dateLine ? parseDateRange(block.dateLine) : null;
    const technologiesLine = block.bodyLines.find((line) => parseTechnologies(line));
    const technologies = technologiesLine ? parseTechnologies(technologiesLine) : null;
    const descriptionLines = [
      ...block.headerLines.slice(1),
      ...block.bodyLines.filter((line) => line !== technologiesLine),
    ];
    const description = descriptionLines.join('\n').trim() || null;

    items.push({
      title: title.slice(0, 255),
      description: description ? description.slice(0, 5000) : null,
      project_url: null,
      repository_url: null,
      start_date: dates?.start_date ?? extractYearFallback(block.headerLines.join(' ')),
      end_date: dates?.is_current ? null : dates?.end_date ?? null,
      technologies,
    });
  }

  return items;
}

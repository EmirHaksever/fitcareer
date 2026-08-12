export interface JobDescriptionSection {
  title: string | null;
  items: string[];
}

const SECTION_HEADERS = [
  'İş Tanımı',
  'Pozisyon Hakkında',
  'Şirket Hakkında',
  'Görev ve Sorumluluklar',
  'Görevler',
  'Sorumluluklar',
  'Aranan Nitelikler',
  'Zorunlu Nitelikler',
  'Tercihen',
  'Teknik Yığın',
  'Teknoloji Stack',
  'Aday Kriterleri',
  'Nitelikler',
  'Kısa Şirket/Proje Tanımı',
];

function normalizeDescription(text: string): string {
  let normalized = text.replace(/\r\n/g, '\n').trim();

  for (const header of SECTION_HEADERS) {
    const pattern = new RegExp(`(?<!\\n)${escapeRegExp(header)}`, 'g');
    normalized = normalized.replace(pattern, `\n\n${header}\n`);
  }

  normalized = normalized.replace(/\s*•\s*/g, '\n• ');
  normalized = normalized.replace(/\s*-\s+(?=[A-ZİĞÜŞÖÇ0-9])/g, '\n• ');

  return normalized.replace(/\n{3,}/g, '\n\n').trim();
}

function escapeRegExp(value: string): string {
  return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

function splitItems(block: string): string[] {
  const lines = block
    .split('\n')
    .map((line) => line.trim())
    .filter(Boolean);

  const items: string[] = [];

  for (const line of lines) {
    if (line.startsWith('• ')) {
      items.push(line.slice(2).trim());
      continue;
    }

    if (line.length > 180 && !line.includes('\n')) {
      line.split(/(?<=[.;!?])\s+(?=[A-ZİĞÜŞÖÇ])/).forEach((part) => {
        const trimmed = part.trim();
        if (trimmed) items.push(trimmed);
      });
      continue;
    }

    items.push(line);
  }

  return items;
}

export function parseJobDescription(text: string): JobDescriptionSection[] {
  const normalized = normalizeDescription(text);
  const chunks = normalized.split(/\n\n+/);
  const sections: JobDescriptionSection[] = [];
  let currentTitle: string | null = null;
  let currentItems: string[] = [];

  const flush = (): void => {
    if (currentItems.length === 0) {
      return;
    }

    sections.push({
      title: currentTitle,
      items: currentItems,
    });
    currentItems = [];
  };

  for (const chunk of chunks) {
    const trimmed = chunk.trim();
    if (!trimmed) continue;

    const header = SECTION_HEADERS.find((value) => trimmed === value || trimmed.startsWith(`${value}\n`));

    if (header && (trimmed === header || trimmed.startsWith(`${header}\n`))) {
      flush();
      currentTitle = header;
      const remainder = trimmed.slice(header.length).trim();
      currentItems = remainder ? splitItems(remainder) : [];
      continue;
    }

    if (SECTION_HEADERS.includes(trimmed)) {
      flush();
      currentTitle = trimmed;
      continue;
    }

    currentItems.push(...splitItems(trimmed));
  }

  flush();

  if (sections.length === 0) {
    return [{ title: null, items: splitItems(normalized) }];
  }

  return sections;
}

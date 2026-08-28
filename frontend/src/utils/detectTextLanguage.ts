const ENGLISH_HINT_WORDS = [
  'the',
  'and',
  'you',
  'your',
  'will',
  'with',
  'our',
  'we',
  'are',
  'for',
  'this',
  'that',
  'have',
  'from',
  'about',
  'role',
  'team',
  'work',
  'experience',
  'requirements',
  'responsibilities',
];

const TURKISH_CHARS = /[ğüşıöçĞÜŞİÖÇ]/;

export function isLikelyEnglishText(text: string | null | undefined): boolean {
  if (!text?.trim()) {
    return false;
  }

  if (TURKISH_CHARS.test(text)) {
    return false;
  }

  const normalized = text.toLowerCase().replace(/[^a-z0-9\s]/g, ' ');
  const words = normalized.split(/\s+/).filter(Boolean);

  if (words.length < 12) {
    return false;
  }

  const englishHits = words.filter((word) => ENGLISH_HINT_WORDS.includes(word)).length;
  const ratio = englishHits / words.length;

  return englishHits >= 3 && ratio >= 0.08;
}

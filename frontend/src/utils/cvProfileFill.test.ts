import { describe, expect, it } from 'vitest';
import { extractProfileSuggestions, mergeProfileSuggestions } from '@/utils/cvProfileFill';
import type { CvParsedData } from '@/types/candidate';

const baseParsed: CvParsedData = {
  text: '',
  sections: {},
  source_filename: 'cv.pdf',
  parsed_at: '2026-08-01T00:00:00Z',
  parser_version: '1',
};

describe('extractProfileSuggestions', () => {
  it('maps summary and headline from parsed sections', () => {
    const result = extractProfileSuggestions({
      ...baseParsed,
      sections: {
        summary: 'Product Manager\nDriving growth through data-informed product decisions.',
      },
    });

    expect(result.summary).toContain('Product Manager');
    expect(result.headline).toBe('Product Manager');
  });

  it('extracts contact links and location', () => {
    const result = extractProfileSuggestions({
      ...baseParsed,
      sections: {
        contact: 'Istanbul, Turkey\nlinkedin.com/in/emir-haksever\ngithub.com/emirhaksever',
      },
    });

    expect(result.city).toBe('Istanbul');
    expect(result.country).toBe('Turkey');
    expect(result.linkedin_url).toBe('https://www.linkedin.com/in/emir-haksever');
    expect(result.github_url).toBe('https://github.com/emirhaksever');
  });
});

describe('mergeProfileSuggestions', () => {
  it('fills only empty fields by default', () => {
    const merged = mergeProfileSuggestions(
      { headline: 'Existing', summary: null },
      { headline: 'New', summary: 'From CV' },
    );

    expect(merged.headline).toBe('Existing');
    expect(merged.summary).toBe('From CV');
  });
});

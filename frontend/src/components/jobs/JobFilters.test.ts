import { describe, expect, it } from 'vitest';
import { readFileSync } from 'node:fs';
import { join } from 'node:path';

describe('JobFilters localization', () => {
  it('renders Turkish score filter labels', () => {
    const source = readFileSync(join(process.cwd(), 'src/components/jobs/JobFilters.tsx'), 'utf8');

    expect(source).toContain('En düşük güven skoru');
    expect(source).toContain('En düşük uyum skoru');
    expect(source).not.toContain('Minimum Trust Score');
    expect(source).not.toContain('Minimum Fit Score');
  });
});

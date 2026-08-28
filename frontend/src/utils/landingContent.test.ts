import { readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';
import { LANDING_UNSUPPORTED_CLAIMS } from '@/utils/landingContent';

const landingSource = readFileSync(
  resolve(dirname(fileURLToPath(import.meta.url)), '../pages/public/LandingPage.tsx'),
  'utf8',
);

describe('landing page truthfulness', () => {
  it('does not show unsupported user or company statistics', () => {
    for (const claim of LANDING_UNSUPPORTED_CLAIMS) {
      expect(landingSource).not.toContain(claim);
    }
  });
});

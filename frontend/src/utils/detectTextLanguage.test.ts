import { describe, expect, it } from 'vitest';
import { isLikelyEnglishText } from '@/utils/detectTextLanguage';

describe('isLikelyEnglishText', () => {
  it('detects English job descriptions without Turkish characters', () => {
    const text =
      'We are looking for a Senior Backend Developer who will work with our team. ' +
      'You will have experience with PHP and Laravel. This role requires strong communication skills.';

    expect(isLikelyEnglishText(text)).toBe(true);
  });

  it('does not flag Turkish descriptions', () => {
    const text =
      'Yazılım ekibimize katılacak deneyimli bir backend geliştirici arıyoruz. ' +
      'PHP ve Laravel deneyimi olan adaylar değerlendirilecektir. İstanbul ofisinde çalışılacaktır.';

    expect(isLikelyEnglishText(text)).toBe(false);
  });

  it('returns false for short snippets', () => {
    expect(isLikelyEnglishText('Short English note')).toBe(false);
  });
});

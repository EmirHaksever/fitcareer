import { describe, expect, it } from 'vitest';
import { parseJobDescription } from '@/utils/formatJobDescription';

describe('parseJobDescription', () => {
  it('splits scraped kariyer.net style content into sections', () => {
    const text =
      'Fonet Bilgi Teknolojileri HakkındaSağlık bilişimi alanında çalışıyoruz.İş TanımıTeknik liderlik yapılacaktır.Görev ve Sorumluluklar• Java geliştirme• React geliştirmeAranan Nitelikler• Spring Boot deneyimi';

    const sections = parseJobDescription(text);

    expect(sections.some((section) => section.title === 'İş Tanımı')).toBe(true);
    expect(
      sections
        .flatMap((section) => section.items)
        .some((item) => item.includes('Java geliştirme')),
    ).toBe(true);
    expect(
      sections
        .flatMap((section) => section.items)
        .some((item) => item.includes('Spring Boot')),
    ).toBe(true);
  });

  it('returns a single section for plain paragraphs', () => {
    const sections = parseJobDescription('Tek paragraf ilan açıklaması.');

    expect(sections).toHaveLength(1);
    expect(sections[0]?.items[0]).toContain('Tek paragraf');
  });
});

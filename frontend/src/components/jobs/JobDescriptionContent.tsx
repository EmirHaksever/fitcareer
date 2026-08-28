import { parseJobDescription } from '@/utils/formatJobDescription';

interface JobDescriptionContentProps {
  content: string;
}

export function JobDescriptionContent({ content }: JobDescriptionContentProps) {
  const sections = parseJobDescription(content);

  return (
    <div className="space-y-6 break-words">
      {sections.map((section, index) => (
        <section key={`${section.title ?? 'section'}-${index}`} className="space-y-3">
          {section.title ? <h3 className="text-base font-semibold text-ink">{section.title}</h3> : null}
          {section.items.length > 1 || section.items[0]?.startsWith('•') ? (
            <ul className="space-y-2 text-sm leading-7 text-ink-muted">
              {section.items.map((item) => (
                <li key={item} className="flex gap-2">
                  <span className="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-primary" aria-hidden="true" />
                  <span className="min-w-0 break-words">{item}</span>
                </li>
              ))}
            </ul>
          ) : (
            <p className="whitespace-pre-wrap break-words text-sm leading-7 text-ink-muted">
              {section.items.join('\n')}
            </p>
          )}
        </section>
      ))}
    </div>
  );
}

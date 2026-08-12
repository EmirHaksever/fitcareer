import { CheckCircle2, MinusCircle, XCircle } from 'lucide-react';
import type { FitScoreDetails } from '@/types/fitScore';
import { buildFitBreakdown } from '@/utils/fitScoreBreakdown';
import { cn } from '@/utils/format';

interface FitScoreBreakdownProps {
  details?: FitScoreDetails | null;
  className?: string;
}

function lineTone(line: string): 'positive' | 'negative' | 'neutral' {
  if (line.includes('Eksik') || line.includes('Örtüşmüyor') || line.includes('Düşük')) {
    return 'negative';
  }

  if (line.includes('Eşleşiyor') || line.includes('Uygun') || line.includes('uyumlu')) {
    return 'positive';
  }

  return 'neutral';
}

const toneIcon = {
  positive: CheckCircle2,
  negative: XCircle,
  neutral: MinusCircle,
};

const toneClass = {
  positive: 'text-secondary',
  negative: 'text-danger',
  neutral: 'text-ink-muted',
};

export function FitScoreBreakdown({ details, className }: FitScoreBreakdownProps) {
  const items = buildFitBreakdown(details);

  if (items.length === 0) {
    return null;
  }

  return (
    <div className={cn('space-y-4', className)}>
      <h3 className="text-sm font-semibold uppercase tracking-wide text-ink-muted">
        Uyum Detayları
      </h3>
      <div className="space-y-4">
        {items.map((item) => (
          <section
            key={item.key}
            className="rounded-xl border border-surface bg-white px-4 py-3"
          >
            <div className="mb-2 flex items-center justify-between gap-2">
              <h4 className="text-sm font-semibold text-ink">{item.title}</h4>
              {item.score !== null ? (
                <span className="text-xs font-medium text-secondary">%{item.score}</span>
              ) : null}
            </div>
            <ul className="space-y-1.5">
              {item.lines.map((line) => {
                const tone = lineTone(line);
                const Icon = toneIcon[tone];

                return (
                  <li key={line} className="flex items-start gap-2 text-sm text-ink-muted">
                    <Icon className={cn('mt-0.5 h-4 w-4 shrink-0', toneClass[tone])} aria-hidden="true" />
                    <span>{line}</span>
                  </li>
                );
              })}
            </ul>
          </section>
        ))}
      </div>
    </div>
  );
}

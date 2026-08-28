import { cn } from '@/utils/format';
import { formatMatchListPrimary, resolveMatchDisplay, type MatchDisplay } from '@/utils/matchDisplay';
import type { MatchAnalysisStatus } from '@/types/companyApplication';

interface MatchScoreDisplayProps {
  score: number | null | undefined;
  status?: MatchAnalysisStatus | string | null;
  variant?: 'hero' | 'compact' | 'inline';
  className?: string;
}

function toneClass(display: MatchDisplay): string {
  if (display.state !== 'completed' || display.score === null) {
    return 'text-ink-muted';
  }

  if (display.score >= 80) return 'text-secondary';
  if (display.score >= 60) return 'text-primary';
  if (display.score >= 40) return 'text-amber-700';
  return 'text-danger';
}

export function MatchScoreDisplay({
  score,
  status,
  variant = 'compact',
  className,
}: MatchScoreDisplayProps) {
  const display = resolveMatchDisplay(score, status);

  if (variant === 'hero') {
    return (
      <div className={cn('min-w-0 space-y-1', className)}>
        <p className={cn('text-4xl font-bold tracking-tight sm:text-5xl', toneClass(display))}>
          {display.state === 'completed' ? display.primary : display.primary}
        </p>
        <p className="text-base font-semibold text-ink">{display.secondary}</p>
      </div>
    );
  }

  if (variant === 'inline') {
    if (display.state === 'completed') {
      return (
        <span className={cn('font-semibold', toneClass(display), className)}>
          {display.primary} {display.label}
        </span>
      );
    }

    return <span className={cn('text-ink-muted', className)}>{formatMatchListPrimary(display)}</span>;
  }

  return (
    <div className={cn('min-w-0', className)}>
      <p className={cn('text-sm font-semibold', toneClass(display))}>{formatMatchListPrimary(display)}</p>
      {display.state === 'completed' && display.label ? (
        <p className="text-xs font-medium text-ink-muted">{display.label}</p>
      ) : null}
    </div>
  );
}

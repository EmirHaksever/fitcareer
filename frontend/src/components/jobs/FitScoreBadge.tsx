import { ScoreRing } from '@/components/ui/ScoreRing';
import { getFitBand, getFitBandLabel, isFitPending } from '@/utils/scores';
import { cn } from '@/utils/format';

interface FitScoreBadgeProps {
  score: number | null;
  status: string | null;
  size?: 'sm' | 'md' | 'lg';
  className?: string;
}

export function FitScoreBadge({ score, status, size = 'sm', className }: FitScoreBadgeProps) {
  const pending = isFitPending(status);
  const displayScore = pending ? null : score;
  const band = getFitBand(displayScore);

  return (
    <div className={cn('inline-flex flex-col items-center', className)}>
      <ScoreRing
        value={displayScore}
        label="Uyum Skoru"
        band={band}
        pendingLabel={pending ? 'Analiz ediliyor' : undefined}
        size={size}
      />
      {!pending && displayScore !== null ? (
        <p className="mt-0.5 text-xs font-medium text-secondary">{getFitBandLabel(band)}</p>
      ) : null}
    </div>
  );
}

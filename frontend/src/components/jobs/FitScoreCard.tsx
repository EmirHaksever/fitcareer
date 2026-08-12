import { ScoreRing } from '@/components/ui/ScoreRing';
import { getFitBand, getFitBandLabel, isFitPending } from '@/utils/scores';
import { cn } from '@/utils/format';

interface FitScoreCardProps {
  score: number | null;
  status: string | null;
  className?: string;
}

export function FitScoreCard({ score, status, className }: FitScoreCardProps) {
  const pending = isFitPending(status);
  const displayScore = pending ? null : score;
  const band = getFitBand(displayScore);

  return (
    <div
      className={cn(
        'flex min-w-[148px] flex-1 flex-col items-center rounded-xl border border-secondary/25 bg-secondary/5 px-4 py-5 shadow-card',
        className,
      )}
    >
      <ScoreRing
        value={displayScore}
        label="Uyum Skoru"
        band={band}
        pendingLabel={pending ? 'Analiz ediliyor' : undefined}
        size="lg"
      />
      {!pending && displayScore !== null ? (
        <p className="mt-1 text-sm font-semibold text-secondary">{getFitBandLabel(band)}</p>
      ) : null}
    </div>
  );
}

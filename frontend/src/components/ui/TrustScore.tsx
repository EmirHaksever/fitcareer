import { ScoreRing } from '@/components/ui/ScoreRing';
import { getTrustBand, getTrustBandLabel, isTrustPending } from '@/utils/scores';
import { cn } from '@/utils/format';
import type { TrustAnalysisStatus } from '@/types/api';

interface TrustScoreProps {
  score: number | null;
  status: TrustAnalysisStatus;
  size?: 'sm' | 'md' | 'lg';
  className?: string;
}

export function TrustScore({ score, status, size = 'sm', className }: TrustScoreProps) {
  const pending = isTrustPending(status);
  const displayScore = pending ? null : score;
  const band = getTrustBand(displayScore);

  return (
    <div className={cn('inline-flex flex-col items-center', className)}>
      <ScoreRing
        value={displayScore}
        label="Güven Skoru"
        band={band}
        pendingLabel={pending ? 'Analiz ediliyor' : undefined}
        size={size}
      />
      {!pending && displayScore !== null ? (
        <p className="mt-0.5 text-xs font-medium text-primary">{getTrustBandLabel(band)}</p>
      ) : (
        <span className="mt-0.5 block h-4" aria-hidden="true" />
      )}
    </div>
  );
}

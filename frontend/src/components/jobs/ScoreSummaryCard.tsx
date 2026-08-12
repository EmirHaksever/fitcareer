import { ScoreRing } from '@/components/ui/ScoreRing';
import {
  getFitBand,
  getFitBandLabel,
  getTrustBand,
  getTrustBandLabel,
  isFitPending,
  isTrustPending,
} from '@/utils/scores';
import type { TrustAnalysisStatus } from '@/types/api';

interface ScoreSummaryCardProps {
  type: 'fit' | 'trust';
  score: number | null;
  status: TrustAnalysisStatus | string | null;
}

export function ScoreSummaryCard({ type, score, status }: ScoreSummaryCardProps) {
  const pending =
    type === 'fit'
      ? isFitPending(status)
      : isTrustPending(status as TrustAnalysisStatus);
  const displayScore = pending ? null : score;
  const band = type === 'fit' ? getFitBand(displayScore) : getTrustBand(displayScore);
  const label = type === 'fit' ? 'Uyum Skoru' : 'Güven Skoru';
  const descriptor =
    type === 'fit' ? getFitBandLabel(band) : getTrustBandLabel(band);

  return (
    <div className="flex min-w-[148px] flex-1 flex-col items-center rounded-xl border border-surface bg-white px-4 py-5 shadow-card">
      <ScoreRing
        value={displayScore}
        label={label}
        band={band}
        pendingLabel={pending ? 'Analiz ediliyor' : undefined}
        size="lg"
      />
      {!pending && displayScore !== null ? (
        <p className="mt-1 text-sm font-semibold text-primary">{descriptor}</p>
      ) : null}
    </div>
  );
}

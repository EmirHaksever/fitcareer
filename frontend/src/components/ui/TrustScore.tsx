import { ScoreRing } from '@/components/ui/ScoreRing';
import { getTrustBand, isTrustPending } from '@/utils/scores';
import type { TrustAnalysisStatus } from '@/types/api';

interface TrustScoreProps {
  score: number | null;
  status: TrustAnalysisStatus;
  size?: 'sm' | 'md' | 'lg';
}

export function TrustScore({ score, status, size = 'sm' }: TrustScoreProps) {
  const pending = isTrustPending(status);
  const displayScore = pending ? null : score;
  const band = getTrustBand(displayScore);

  return (
    <ScoreRing
      value={displayScore}
      label="Güven Skoru"
      band={band}
      pendingLabel={pending ? 'Analiz ediliyor' : undefined}
      size={size}
    />
  );
}

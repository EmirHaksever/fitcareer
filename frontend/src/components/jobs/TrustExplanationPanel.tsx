import { CheckCircle2, Circle, XCircle } from 'lucide-react';
import type { JobDetail } from '@/types/api';
import { TRUST_SCORE_DISCLAIMER, buildTrustFactors } from '@/utils/trustExplanation';

export function TrustExplanationPanel({ job }: { job: JobDetail }) {
  const factors = buildTrustFactors(job);

  return (
    <div className="space-y-4">
      <div>
        <p className="text-sm font-medium text-ink">Bu skor nasıl oluştu?</p>
        <ul className="mt-3 space-y-2">
          {factors.map((factor) => (
            <li key={factor.id} className="flex items-start gap-2.5 text-sm text-ink-muted">
              {factor.status === 'supported' ? (
                <CheckCircle2 className="mt-0.5 h-4 w-4 shrink-0 text-secondary" aria-hidden="true" />
              ) : factor.status === 'unsupported' ? (
                <XCircle className="mt-0.5 h-4 w-4 shrink-0 text-warning" aria-hidden="true" />
              ) : (
                <Circle className="mt-0.5 h-4 w-4 shrink-0 text-ink-subtle" aria-hidden="true" />
              )}
              <span>{factor.label}</span>
            </li>
          ))}
        </ul>
      </div>

      <p className="rounded-lg border border-surface bg-background px-3 py-2 text-xs leading-5 text-ink-muted">
        {TRUST_SCORE_DISCLAIMER}
      </p>
    </div>
  );
}

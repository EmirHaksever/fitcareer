import { CompanyApplicationStatusBadge } from '@/components/company-applications/CompanyApplicationStatusBadge';
import type { ApplicationStatusHistory } from '@/types/application';
import { formatApplicationDateTime } from '@/utils/applicationStatus';

interface CompanyApplicationStatusTimelineProps {
  history: ApplicationStatusHistory[];
}

export function CompanyApplicationStatusTimeline({ history }: CompanyApplicationStatusTimelineProps) {
  if (history.length === 0) {
    return <p className="text-sm text-ink-muted">Henüz durum geçmişi kaydı bulunmuyor.</p>;
  }

  return (
    <ol className="space-y-0">
      {history.map((entry, index) => {
        const isLast = index === history.length - 1;

        return (
          <li key={entry.id} className="relative flex gap-4 pb-6 last:pb-0">
            {!isLast ? (
              <span
                className="absolute left-[11px] top-6 h-[calc(100%-12px)] w-px bg-surface"
                aria-hidden="true"
              />
            ) : null}

            <span
              className="relative z-10 mt-1 h-[22px] w-[22px] shrink-0 rounded-full border-2 border-primary bg-white"
              aria-hidden="true"
            />

            <div className="min-w-0 flex-1 space-y-2">
              <div className="flex flex-wrap items-center gap-2">
                <CompanyApplicationStatusBadge status={entry.to_status} />
                <time className="text-xs text-ink-subtle" dateTime={entry.created_at}>
                  {formatApplicationDateTime(entry.created_at)}
                </time>
              </div>

              {entry.note ? <p className="text-sm text-ink-muted">{entry.note}</p> : null}
            </div>
          </li>
        );
      })}
    </ol>
  );
}

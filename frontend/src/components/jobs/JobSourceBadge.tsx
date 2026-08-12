import { cn } from '@/utils/format';
import { formatJobSourceBadgeLabel, isExternalJob, type JobSourceFields } from '@/utils/jobSource';

interface JobSourceBadgeProps {
  job: JobSourceFields;
  className?: string;
  prominent?: boolean;
}

export function JobSourceBadge({ job, className, prominent = false }: JobSourceBadgeProps) {
  const external = isExternalJob(job);

  return (
    <span
      className={cn(
        'inline-flex items-center rounded-full border font-semibold tracking-wide',
        prominent
          ? 'border-primary/20 bg-primary/5 px-3 py-1 text-[11px] text-primary'
          : 'border-surface bg-background px-2.5 py-1 text-xs font-medium text-ink-muted',
        className,
      )}
    >
      {formatJobSourceBadgeLabel(job)}
      {prominent && external ? (
        <span className="sr-only"> dış kaynak ilanı</span>
      ) : null}
    </span>
  );
}

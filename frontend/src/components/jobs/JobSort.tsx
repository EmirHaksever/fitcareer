import type { JobSortValue } from '@/types/api';
import { SORT_OPTIONS } from '@/components/jobs/jobFilterOptions';
import { cn } from '@/utils/format';

interface JobSortProps {
  value: JobSortValue;
  onChange: (value: JobSortValue) => void;
  className?: string;
}

export function JobSort({ value, onChange, className }: JobSortProps) {
  const selectedLabel = SORT_OPTIONS.find((option) => option.value === value)?.label ?? 'En uygun';

  return (
    <label className={cn('flex items-center gap-2 text-sm text-ink-muted', className)}>
      <span className="shrink-0 font-medium">Sıralama:</span>
      <select
        value={value}
        onChange={(event) => onChange(event.target.value as JobSortValue)}
        className="h-10 min-w-[140px] rounded-xl border border-surface bg-white px-3 text-sm font-medium text-ink outline-none transition focus:border-primary focus:ring-4 focus:ring-primary/10"
        aria-label="İlan sıralama"
      >
        {SORT_OPTIONS.map((option) => (
          <option key={option.value} value={option.value}>
            {option.label}
          </option>
        ))}
      </select>
      <span className="sr-only">{selectedLabel}</span>
    </label>
  );
}

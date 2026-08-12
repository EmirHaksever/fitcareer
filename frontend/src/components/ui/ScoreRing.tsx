import { cn } from '@/utils/format';
import { bandRingClasses, type ScoreBand } from '@/utils/scores';

interface ScoreRingProps {
  value: number | null;
  label: string;
  band: ScoreBand;
  pendingLabel?: string;
  size?: 'sm' | 'md' | 'lg';
}

const sizeMap = {
  sm: { box: 'h-16 w-16', text: 'text-sm', sub: 'text-[10px]' },
  md: { box: 'h-20 w-20', text: 'text-lg', sub: 'text-xs' },
  lg: { box: 'h-28 w-28', text: 'text-2xl', sub: 'text-sm' },
};

export function ScoreRing({
  value,
  label,
  band,
  pendingLabel,
  size = 'md',
}: ScoreRingProps) {
  const dimensions = sizeMap[size];
  const progress = value ?? 0;
  const radius = 34;
  const circumference = 2 * Math.PI * radius;
  const offset = circumference - (progress / 100) * circumference;

  return (
    <div className="flex flex-col items-center gap-2 text-center">
      <div className={cn('relative', dimensions.box)}>
        <svg className="h-full w-full -rotate-90" viewBox="0 0 80 80" aria-hidden="true">
          <circle cx="40" cy="40" r={radius} className="stroke-surface" strokeWidth="6" fill="none" />
          {value !== null ? (
            <circle
              cx="40"
              cy="40"
              r={radius}
              className={bandRingClasses[band]}
              strokeWidth="6"
              fill="none"
              strokeLinecap="round"
              strokeDasharray={circumference}
              strokeDashoffset={offset}
            />
          ) : null}
        </svg>
        <div className="absolute inset-0 flex flex-col items-center justify-center">
          <span className={cn('font-semibold text-ink', dimensions.text)}>
            {value !== null ? `%${value}` : '—'}
          </span>
        </div>
      </div>
      <div>
        <p className={cn('font-medium text-ink', dimensions.sub)}>{label}</p>
        {pendingLabel ? <p className="text-xs text-ink-muted">{pendingLabel}</p> : null}
      </div>
    </div>
  );
}

import type { ChartSegment } from '@/utils/dashboardCharts';
import { getTotalSegmentValue } from '@/utils/dashboardCharts';
import { cn } from '@/utils/format';

interface JobStatusChartProps {
  segments: ChartSegment[];
  className?: string;
}

const CHART_SIZE = 120;
const STROKE_WIDTH = 18;
const RADIUS = (CHART_SIZE - STROKE_WIDTH) / 2;
const CIRCUMFERENCE = 2 * Math.PI * RADIUS;

export function JobStatusChart({ segments, className }: JobStatusChartProps) {
  const total = getTotalSegmentValue(segments);

  if (total === 0) {
    return (
      <div className={cn('flex flex-col items-center justify-center gap-2 py-6 text-center', className)}>
        <div className="flex h-[120px] w-[120px] items-center justify-center rounded-full border-8 border-surface text-sm text-ink-muted">
          0
        </div>
        <p className="text-sm text-ink-muted">Henüz ilan yok</p>
      </div>
    );
  }

  let offset = 0;

  return (
    <div className={cn('flex flex-col items-center gap-5 sm:flex-row sm:items-center', className)}>
      <div className="relative shrink-0">
        <svg width={CHART_SIZE} height={CHART_SIZE} viewBox={`0 0 ${CHART_SIZE} ${CHART_SIZE}`}>
          <circle
            cx={CHART_SIZE / 2}
            cy={CHART_SIZE / 2}
            r={RADIUS}
            fill="none"
            stroke="#E2E8F0"
            strokeWidth={STROKE_WIDTH}
          />
          {segments.map((segment) => {
            const fraction = segment.value / total;
            const dash = fraction * CIRCUMFERENCE;
            const circle = (
              <circle
                key={segment.key}
                cx={CHART_SIZE / 2}
                cy={CHART_SIZE / 2}
                r={RADIUS}
                fill="none"
                stroke={segment.color}
                strokeWidth={STROKE_WIDTH}
                strokeDasharray={`${dash} ${CIRCUMFERENCE - dash}`}
                strokeDashoffset={-offset}
                strokeLinecap="round"
                transform={`rotate(-90 ${CHART_SIZE / 2} ${CHART_SIZE / 2})`}
              />
            );
            offset += dash;
            return circle;
          })}
        </svg>
        <div className="absolute inset-0 flex flex-col items-center justify-center">
          <span className="text-2xl font-bold text-ink">{total}</span>
          <span className="text-[11px] text-ink-muted">ilan</span>
        </div>
      </div>

      <div className="flex-1 space-y-2">
        {segments.map((segment) => (
          <div key={segment.key} className="flex items-center justify-between gap-3 text-sm">
            <div className="flex items-center gap-2">
              <span
                className="h-2.5 w-2.5 rounded-full"
                style={{ backgroundColor: segment.color }}
              />
              <span className="text-ink">{segment.label}</span>
            </div>
            <span className="font-medium text-ink-muted">{segment.value}</span>
          </div>
        ))}
      </div>
    </div>
  );
}

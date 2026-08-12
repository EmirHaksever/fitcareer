import type { ChartSegment } from '@/utils/dashboardCharts';
import { getTotalSegmentValue } from '@/utils/dashboardCharts';
import { cn } from '@/utils/format';

interface PipelineBarChartProps {
  segments: ChartSegment[];
  className?: string;
}

export function PipelineBarChart({ segments, className }: PipelineBarChartProps) {
  const total = getTotalSegmentValue(segments);
  const maxValue = Math.max(...segments.map((segment) => segment.value), 1);

  return (
    <div className={cn('space-y-4', className)}>
      {segments.map((segment) => {
        const width = segment.value === 0 ? 0 : Math.max(8, (segment.value / maxValue) * 100);
        const percentage = total > 0 ? Math.round((segment.value / total) * 100) : 0;

        return (
          <div key={segment.key} className="space-y-1.5">
            <div className="flex items-center justify-between gap-3 text-sm">
              <span className="font-medium text-ink">{segment.label}</span>
              <span className="text-ink-muted">
                {segment.value.toLocaleString('tr-TR')}
                {total > 0 ? ` · %${percentage}` : ''}
              </span>
            </div>
            <div className="h-2.5 overflow-hidden rounded-full bg-background">
              <div
                className="h-full rounded-full transition-all"
                style={{ width: `${width}%`, backgroundColor: segment.color }}
              />
            </div>
          </div>
        );
      })}
    </div>
  );
}

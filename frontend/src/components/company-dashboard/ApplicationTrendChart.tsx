import type { TrendPoint } from '@/utils/dashboardCharts';
import { getMaxTrendCount } from '@/utils/dashboardCharts';
import { cn } from '@/utils/format';

interface ApplicationTrendChartProps {
  points: TrendPoint[];
  className?: string;
}

export function ApplicationTrendChart({ points, className }: ApplicationTrendChartProps) {
  const maxCount = getMaxTrendCount(points);
  const total = points.reduce((sum, point) => sum + point.count, 0);

  return (
    <div className={cn('space-y-4', className)}>
      <div className="flex items-end justify-between gap-2">
        <div>
          <p className="text-2xl font-bold text-ink">{total.toLocaleString('tr-TR')}</p>
          <p className="text-xs text-ink-muted">Son 14 günde toplam başvuru</p>
        </div>
      </div>

      <div className="flex h-28 items-end gap-1.5">
        {points.map((point) => {
          const height = point.count === 0 ? 4 : Math.max(12, (point.count / maxCount) * 100);

          return (
            <div key={point.dateKey} className="group flex flex-1 flex-col items-center gap-2">
              <div className="relative flex h-full w-full items-end">
                <div
                  className="w-full rounded-t-md bg-gradient-to-t from-primary to-primary/60 transition group-hover:from-primary/90"
                  style={{ height: `${height}%` }}
                  title={`${point.label}: ${point.count} başvuru`}
                />
              </div>
              <span className="text-[10px] font-medium text-ink-subtle">{point.label}</span>
            </div>
          );
        })}
      </div>
    </div>
  );
}

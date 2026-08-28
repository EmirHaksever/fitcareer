import type { DashboardTrustBucket } from '@/types/api';
import { colorClassForTrustBucket } from '@/utils/dashboardStats';

interface TrustDistributionChartProps {
  buckets: DashboardTrustBucket[];
  totalJobs: number;
}

export function TrustDistributionChart({ buckets, totalJobs }: TrustDistributionChartProps) {
  const chartBuckets = buckets.filter((bucket) => bucket.id !== 'pending_analysis');
  const maxCount = Math.max(1, ...chartBuckets.map((bucket) => bucket.count));

  if (totalJobs === 0) {
    return (
      <p className="text-sm text-ink-muted">Henüz güvenilirlik dağılımı için yeterli ilan yok.</p>
    );
  }

  return (
    <div className="space-y-4">
      <div className="flex h-36 items-end gap-2">
        {chartBuckets.map((bucket) => {
          const height = bucket.count === 0 ? 4 : Math.max(12, (bucket.count / maxCount) * 100);
          const colorClass = colorClassForTrustBucket(bucket.id);

          return (
            <div key={bucket.id} className="flex flex-1 flex-col items-center gap-2">
              <span className="text-xs font-medium text-ink-muted">{bucket.count}</span>
              <div
                className={`w-full rounded-t-lg ${colorClass}`}
                style={{ height: `${height}%`, minHeight: bucket.count > 0 ? '12px' : '4px' }}
                title={`${bucket.label}: ${bucket.count}`}
              />
            </div>
          );
        })}
      </div>

      <div className="grid grid-cols-2 gap-2">
        {buckets.map((bucket) => (
          <div key={bucket.id} className="flex items-center gap-2 text-xs text-ink-muted">
            <span className={`h-2.5 w-2.5 shrink-0 rounded-full ${colorClassForTrustBucket(bucket.id)}`} />
            <span>
              {bucket.label} ({bucket.percentage}%)
            </span>
          </div>
        ))}
      </div>
    </div>
  );
}

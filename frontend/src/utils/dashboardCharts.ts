import type { ApplicationStatus } from '@/types/application';
import type { CompanyApplication } from '@/types/companyApplication';
import { APPLICATION_STATUS_LABELS } from '@/utils/applicationStatus';
import { JOB_STATUS_LABELS } from '@/components/company-jobs/jobFormOptions';

export interface TrendPoint {
  label: string;
  dateKey: string;
  count: number;
}

export interface ChartSegment {
  key: string;
  label: string;
  value: number;
  color: string;
}

const TREND_DAYS = 14;

const STATUS_COLORS: Record<ApplicationStatus, string> = {
  submitted: '#22c55e',
  under_review: '#0ea5e9',
  shortlisted: '#8b5cf6',
  interview: '#f59e0b',
  offered: '#10b981',
  rejected: '#ef4444',
  withdrawn: '#94a3b8',
};

const JOB_STATUS_COLORS: Record<string, string> = {
  draft: '#94a3b8',
  pending_review: '#f59e0b',
  published: '#22c55e',
  expired: '#ef4444',
  closed: '#64748b',
  flagged: '#f97316',
};

function formatDayLabel(date: Date): string {
  return date.toLocaleDateString('tr-TR', { day: 'numeric', month: 'short' });
}

function toDateKey(date: Date): string {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

export function buildApplicationTrend(applications: CompanyApplication[], days = TREND_DAYS): TrendPoint[] {
  const today = new Date();
  today.setHours(0, 0, 0, 0);

  const buckets = new Map<string, number>();

  for (let offset = days - 1; offset >= 0; offset -= 1) {
    const date = new Date(today);
    date.setDate(today.getDate() - offset);
    buckets.set(toDateKey(date), 0);
  }

  applications.forEach((application) => {
    const dateKey = application.applied_at.slice(0, 10);
    if (buckets.has(dateKey)) {
      buckets.set(dateKey, (buckets.get(dateKey) ?? 0) + 1);
    }
  });

  return Array.from(buckets.entries()).map(([dateKey, count]) => {
    const date = new Date(`${dateKey}T00:00:00`);
    return {
      label: formatDayLabel(date),
      dateKey,
      count,
    };
  });
}

export function buildPipelineSegments(
  counts: Array<{ status: ApplicationStatus; count: number }>,
): ChartSegment[] {
  return counts.map(({ status, count }) => ({
    key: status,
    label: APPLICATION_STATUS_LABELS[status],
    value: count,
    color: STATUS_COLORS[status],
  }));
}

export function buildJobStatusSegments(items: Array<{ status: string }>): ChartSegment[] {
  const totals = new Map<string, number>();

  items.forEach((item) => {
    totals.set(item.status, (totals.get(item.status) ?? 0) + 1);
  });

  return Array.from(totals.entries())
    .map(([status, value]) => ({
      key: status,
      label: JOB_STATUS_LABELS[status] ?? status,
      value,
      color: JOB_STATUS_COLORS[status] ?? '#94a3b8',
    }))
    .sort((a, b) => b.value - a.value);
}

export function getMaxTrendCount(points: TrendPoint[]): number {
  return Math.max(...points.map((point) => point.count), 1);
}

export function getTotalSegmentValue(segments: ChartSegment[]): number {
  return segments.reduce((sum, segment) => sum + segment.value, 0);
}

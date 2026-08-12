import { cn } from '@/utils/format';
import type { ReactNode } from 'react';

export function LoadingState({ label = 'Yükleniyor...' }: { label?: string }) {
  return (
    <div className="flex min-h-40 items-center justify-center text-sm text-ink-muted" role="status">
      {label}
    </div>
  );
}

export function Skeleton({ className }: { className?: string }) {
  return <div className={cn('animate-pulse rounded-xl bg-surface/80', className)} />;
}

export function EmptyState({
  title,
  description,
  action,
}: {
  title: string;
  description: string;
  action?: ReactNode;
}) {
  return (
    <div className="flex min-h-48 flex-col items-center justify-center gap-3 rounded-xl border border-dashed border-surface bg-white px-6 py-10 text-center">
      <h3 className="text-lg font-semibold text-ink">{title}</h3>
      <p className="max-w-md text-sm text-ink-muted">{description}</p>
      {action}
    </div>
  );
}

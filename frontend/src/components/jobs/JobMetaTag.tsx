import { cn } from '@/utils/format';

export function JobMetaTag({ children, className }: { children: string; className?: string }) {
  return (
    <span
      className={cn(
        'inline-flex items-center rounded-full bg-background px-2.5 py-1 text-xs font-medium text-ink-muted',
        className,
      )}
    >
      {children}
    </span>
  );
}

import { cn } from '@/utils/format';

type BadgeTone = 'default' | 'success' | 'warning' | 'danger' | 'info';

interface BadgeProps {
  children: string;
  tone?: BadgeTone;
  className?: string;
}

const toneClasses: Record<BadgeTone, string> = {
  default: 'bg-background text-ink-muted',
  success: 'bg-success text-primary-800',
  warning: 'bg-amber-50 text-amber-700',
  danger: 'bg-red-50 text-danger',
  info: 'bg-blue-50 text-supporting',
};

export function Badge({ children, tone = 'default', className }: BadgeProps) {
  return (
    <span
      className={cn(
        'inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium',
        toneClasses[tone],
        className,
      )}
    >
      {children}
    </span>
  );
}

import { cn } from '@/utils/format';

interface JobCompanyAvatarProps {
  name?: string | null;
  size?: 'sm' | 'md' | 'lg';
  className?: string;
}

const sizeClasses = {
  sm: 'h-10 w-10 text-xs',
  md: 'h-12 w-12 text-sm',
  lg: 'h-14 w-14 text-base',
};

export function JobCompanyAvatar({ name, size = 'md', className }: JobCompanyAvatarProps) {
  const initials = name?.slice(0, 2).toUpperCase() ?? 'FC';

  return (
    <div
      className={cn(
        'flex shrink-0 items-center justify-center rounded-full bg-primary/10 font-semibold text-primary ring-1 ring-primary/10',
        sizeClasses[size],
        className,
      )}
      aria-hidden="true"
    >
      {initials}
    </div>
  );
}

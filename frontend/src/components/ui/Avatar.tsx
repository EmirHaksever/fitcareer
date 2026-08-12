import { cn } from '@/utils/format';

interface AvatarProps {
  name: string;
  className?: string;
}

export function Avatar({ name, className }: AvatarProps) {
  const initials = name
    .split(' ')
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase() ?? '')
    .join('');

  return (
    <div
      aria-label={name}
      className={cn(
        'flex h-10 w-10 items-center justify-center rounded-full bg-primary/10 text-sm font-semibold text-primary',
        className,
      )}
    >
      {initials}
    </div>
  );
}

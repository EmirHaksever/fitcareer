import { cn } from '@/utils/format';

export const LOGO_SRC = '/assets/fitcareer-logo.png';

type LogoSize = 'sm' | 'md' | 'auth';

const sizeClasses: Record<LogoSize, string> = {
  sm: 'w-[128px]',
  md: 'w-[190px]',
  auth: 'w-[220px] max-lg:w-[190px]',
};

interface LogoProps {
  compact?: boolean;
  size?: LogoSize;
  showSlogan?: boolean;
  className?: string;
}

export function Logo({ compact = false, size = 'md', className }: LogoProps) {
  const resolvedSize = compact ? 'sm' : size;

  return (
    <img
      src={LOGO_SRC}
      alt="FitCareer"
      width={835}
      height={246}
      className={cn('h-auto max-w-full object-contain', sizeClasses[resolvedSize], className)}
      decoding="async"
    />
  );
}

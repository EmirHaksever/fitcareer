import { cn } from '@/utils/format';
import type { ButtonHTMLAttributes } from 'react';

type ButtonVariant = 'primary' | 'secondary' | 'ghost' | 'outline';
type ButtonSize = 'sm' | 'md' | 'lg';

interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: ButtonVariant;
  size?: ButtonSize;
  loading?: boolean;
  loadingLabel?: string;
}

const variantClasses: Record<ButtonVariant, string> = {
  primary: 'bg-primary text-white hover:bg-primary-700 focus-visible:ring-primary/30',
  secondary: 'bg-secondary text-white hover:bg-secondary-800 focus-visible:ring-secondary/30',
  ghost: 'bg-transparent text-ink hover:bg-white focus-visible:ring-surface',
  outline: 'border border-surface bg-white text-ink hover:bg-background focus-visible:ring-surface',
};

const sizeClasses: Record<ButtonSize, string> = {
  sm: 'h-9 px-3 text-sm',
  md: 'h-11 px-4 text-sm',
  lg: 'h-12 px-5 text-base',
};

export function Button({
  className,
  variant = 'primary',
  size = 'md',
  loading = false,
  loadingLabel = 'Yükleniyor...',
  disabled,
  children,
  ...props
}: ButtonProps) {
  return (
    <button
      className={cn(
        'inline-flex items-center justify-center gap-2 rounded-xl font-medium transition focus-visible:outline-none focus-visible:ring-4 disabled:cursor-not-allowed disabled:opacity-60',
        variantClasses[variant],
        sizeClasses[size],
        className,
      )}
      disabled={disabled || loading}
      {...props}
    >
      {loading ? loadingLabel : children}
    </button>
  );
}

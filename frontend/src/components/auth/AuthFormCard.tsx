import type { ReactNode } from 'react';
import { cn } from '@/utils/format';

interface AuthFormCardProps {
  children: ReactNode;
  className?: string;
  footer?: ReactNode;
}

export function AuthFormCard({ children, className, footer }: AuthFormCardProps) {
  return (
    <div
      className={cn(
        'w-full rounded-[20px] border border-[#E2E8F0] bg-white p-8 shadow-[0_4px_24px_rgba(15,23,42,0.06),0_12px_48px_rgba(15,23,42,0.04)] sm:p-9',
        className,
      )}
    >
      {children}
      {footer}
    </div>
  );
}

export function AuthDivider() {
  return (
    <div className="relative py-2">
      <div className="absolute inset-0 flex items-center" aria-hidden="true">
        <div className="w-full border-t border-[#E2E8F0]" />
      </div>
      <div className="relative flex justify-center">
        <span className="bg-white px-3 text-xs text-[#94A3B8]">veya</span>
      </div>
    </div>
  );
}

import { cn } from '@/utils/format';
import type { InputHTMLAttributes, ReactNode } from 'react';

interface InputProps extends InputHTMLAttributes<HTMLInputElement> {
  label: string;
  error?: string;
  icon?: ReactNode;
}

export function Input({ label, error, icon, className, id, ...props }: InputProps) {
  const inputId = id ?? props.name;

  return (
    <label className="block space-y-2" htmlFor={inputId}>
      <span className="text-sm font-medium text-[#334155]">{label}</span>
      <div className="relative">
        {icon ? (
          <span className="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-[#94A3B8]">
            {icon}
          </span>
        ) : null}
        <input
          id={inputId}
          className={cn(
            'h-12 w-full rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] text-sm text-[#0F172A] outline-none transition placeholder:text-[#94A3B8] focus:border-primary focus:bg-white focus:ring-4 focus:ring-primary/10',
            icon ? 'pl-11 pr-3' : 'px-3.5',
            error && 'border-danger focus:border-danger focus:ring-danger/10',
            className,
          )}
          {...props}
        />
      </div>
      {error ? <span className="text-sm text-danger">{error}</span> : null}
    </label>
  );
}

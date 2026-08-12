import type { ReactNode } from 'react';
import { cn } from '@/utils/format';

interface SegmentedControlOption<T extends string> {
  value: T;
  label: string;
  icon?: ReactNode;
}

interface SegmentedControlProps<T extends string> {
  value: T;
  options: SegmentedControlOption<T>[];
  onChange: (value: T) => void;
  ariaLabel: string;
}

export function SegmentedControl<T extends string>({
  value,
  options,
  onChange,
  ariaLabel,
}: SegmentedControlProps<T>) {
  return (
    <div
      className="grid grid-cols-2 gap-2 rounded-xl border border-[#E2E8F0] bg-[#F1F5F9] p-1.5"
      role="tablist"
      aria-label={ariaLabel}
    >
      {options.map((option) => {
        const active = option.value === value;

        return (
          <button
            key={option.value}
            type="button"
            role="tab"
            aria-selected={active}
            className={cn(
              'inline-flex h-11 items-center justify-center gap-2 rounded-[10px] text-sm font-medium transition focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary/10',
              active
                ? 'border border-primary bg-white text-primary shadow-sm'
                : 'border border-transparent bg-transparent text-[#64748B] hover:text-[#334155]',
            )}
            onClick={() => onChange(option.value)}
          >
            {option.icon}
            {option.label}
          </button>
        );
      })}
    </div>
  );
}

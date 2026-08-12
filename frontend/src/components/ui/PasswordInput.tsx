import { useState, type InputHTMLAttributes, type ReactNode } from 'react';
import { Eye, EyeOff } from 'lucide-react';
import { cn } from '@/utils/format';

interface PasswordInputProps extends Omit<InputHTMLAttributes<HTMLInputElement>, 'type'> {
  label: string;
  error?: string;
  icon?: ReactNode;
}

export function PasswordInput({
  label,
  error,
  icon,
  className,
  id,
  name,
  ...props
}: PasswordInputProps) {
  const [visible, setVisible] = useState(false);
  const inputId = id ?? name;

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
          name={name}
          type={visible ? 'text' : 'password'}
          className={cn(
            'h-12 w-full rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] text-sm text-[#0F172A] outline-none transition placeholder:text-[#94A3B8] focus:border-primary focus:bg-white focus:ring-4 focus:ring-primary/10',
            icon ? 'pl-11 pr-11' : 'px-3.5 pr-11',
            error && 'border-danger focus:border-danger focus:ring-danger/10',
            className,
          )}
          {...props}
        />
        <button
          type="button"
          className="absolute right-2.5 top-1/2 inline-flex h-8 w-8 -translate-y-1/2 items-center justify-center rounded-lg text-[#94A3B8] transition hover:bg-white hover:text-[#64748B]"
          onClick={() => setVisible((current) => !current)}
          aria-label={visible ? 'Şifreyi gizle' : 'Şifreyi göster'}
        >
          {visible ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
        </button>
      </div>
      {error ? <span className="text-sm text-danger">{error}</span> : null}
    </label>
  );
}

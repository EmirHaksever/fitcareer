interface ScoreRingProps {
  label: string;
  value: number;
  helper: string;
  color?: string;
}

export function ScoreRing({ label, value, helper, color = '#059669' }: ScoreRingProps) {
  const radius = 34;
  const circumference = 2 * Math.PI * radius;
  const progress = (value / 100) * circumference;

  return (
    <div className="rounded-2xl border border-[#E2E8F0] bg-white p-4 shadow-[0_1px_3px_rgba(15,23,42,0.05)]">
      <p className="text-[11px] font-semibold uppercase tracking-[0.08em] text-[#64748B]">
        {label}
      </p>
      <div className="relative mx-auto mt-3 flex h-[88px] w-[88px] items-center justify-center">
        <svg className="h-[88px] w-[88px] -rotate-90" viewBox="0 0 88 88" aria-hidden="true">
          <circle cx="44" cy="44" r={radius} fill="none" stroke="#E2E8F0" strokeWidth="7" />
          <circle
            cx="44"
            cy="44"
            r={radius}
            fill="none"
            stroke={color}
            strokeWidth="7"
            strokeLinecap="round"
            strokeDasharray={`${progress} ${circumference}`}
          />
        </svg>
        <span className="absolute text-[28px] font-bold leading-none text-[#0F172A]">{value}</span>
      </div>
      <p className="mt-2 text-center text-sm font-medium text-primary">{helper}</p>
    </div>
  );
}

import { FileUser, ShieldCheck, Sparkles } from 'lucide-react';
import { Logo } from '@/components/brand/Logo';
import { ScoreRing } from '@/components/auth/ScoreRing';

const indicators = [
  {
    title: 'Trust Score',
    description: 'İlanın güvenilirliğini gör.',
    icon: ShieldCheck,
  },
  {
    title: 'Fit Score',
    description: 'Sana uygun ilanları öne çıkar.',
    icon: Sparkles,
  },
  {
    title: 'CV Profili',
    description: 'Kariyer bilgilerini tek yerden yönet.',
    icon: FileUser,
  },
] as const;

export function AuthBrandingPanel() {
  return (
    <div className="relative flex h-full flex-col justify-center py-6">
      <div className="relative space-y-10">
        <Logo size="auth" className="w-[220px]" />

        <div className="space-y-5">
          <h1 className="max-w-md text-[2.5rem] font-bold leading-[1.12] tracking-tight text-[#0F172A]">
            Doğru işi bul.
            <br />
            Güvenli kariyer kur.
          </h1>
          <p className="max-w-md text-[15px] leading-7 text-[#64748B]">
            FitCareer, ilan güvenilirliğini Trust Score ve profiline uyumu Fit Score ile birlikte
            gösterir.
          </p>
        </div>

        <ul className="space-y-5">
          {indicators.map((item) => {
            const Icon = item.icon;

            return (
              <li key={item.title} className="flex items-start gap-3.5">
                <span className="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-[#D1FAE5] text-primary">
                  <Icon className="h-[18px] w-[18px]" aria-hidden="true" />
                </span>
                <div className="pt-0.5">
                  <p className="text-sm font-semibold text-[#0F172A]">{item.title}</p>
                  <p className="text-sm text-[#64748B]">{item.description}</p>
                </div>
              </li>
            );
          })}
        </ul>

        <div className="grid max-w-[340px] grid-cols-2 gap-4">
          <ScoreRing label="Trust Score" value={95} helper="Güvenilir" />
          <ScoreRing label="Fit Score" value={91} helper="Uyumlu" color="#0F766E" />
        </div>
      </div>
    </div>
  );
}

export function AuthBrandingMobile() {
  return (
    <div className="space-y-4 lg:hidden">
      <Logo size="md" />
      <div className="space-y-2">
        <h1 className="text-2xl font-bold leading-tight text-[#0F172A]">
          Doğru işi bul. Güvenli kariyer kur.
        </h1>
        <p className="text-sm leading-6 text-[#64748B]">
          FitCareer, ilan güvenilirliğini Trust Score ve profiline uyumu Fit Score ile birlikte
          gösterir.
        </p>
      </div>
    </div>
  );
}

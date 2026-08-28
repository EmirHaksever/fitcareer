import {
  ArrowRight,
  ChartLine,
  FileUser,
  ShieldCheck,
  Target,
} from 'lucide-react';
import { Link } from 'react-router-dom';
import { Logo } from '@/components/brand/Logo';
import { Button } from '@/components/ui/Button';

const miniFeatures = [
  {
    title: 'Trust Score',
    description: 'İlanın güvenilirliğini gör.',
    icon: ShieldCheck,
  },
  {
    title: 'Fit Score',
    description: 'Sana uygun ilanları öne çıkar.',
    icon: Target,
  },
  {
    title: 'CV Profili',
    description: 'Profilini tek yerden yönet.',
    icon: FileUser,
  },
] as const;

const whyFeatures = [
  {
    title: 'Trust Score ile ilan güvenilirliğini analiz et',
    description: 'İlanların güvenilirlik skorunu görerek riskli ilanlardan kaçın.',
    icon: ShieldCheck,
  },
  {
    title: 'Fit Score ile yapay zeka destekli iş eşleşmesi',
    description: 'Profiline en uygun ilanları öne çıkar, zaman kaybetme.',
    icon: Target,
  },
  {
    title: 'CV profilini merkezi olarak yönet',
    description: 'Kariyer bilgilerini tek yerden güncelle ve başvurularında kullan.',
    icon: FileUser,
  },
  {
    title: 'Başvurularını ve analizlerini takip et',
    description: 'Başvuru sürecini ve uyum analizlerini tek panelden izle.',
    icon: ChartLine,
  },
] as const;

export function LandingPage() {
  return (
    <div className="relative min-h-screen overflow-x-hidden bg-[#F5F7F8]">
      <header className="relative border-b border-[#E2E8F0] bg-white/90 backdrop-blur-sm">
        <div className="mx-auto flex h-[76px] max-w-6xl items-center justify-between px-4 lg:px-8">
          <Link to="/" className="inline-flex shrink-0 items-center">
            <Logo size="md" />
          </Link>
          <div className="flex items-center gap-4">
            <Link
              to="/login"
              className="text-sm font-medium text-[#334155] transition hover:text-primary"
            >
              Giriş Yap
            </Link>
            <Link to="/register">
              <Button className="h-11 px-5">Kayıt Ol</Button>
            </Link>
          </div>
        </div>
      </header>

      <main className="relative">
        <section className="mx-auto max-w-6xl px-4 pb-10 pt-14 lg:px-8 lg:pt-16">
          <div className="grid gap-12 lg:grid-cols-2 lg:items-start lg:gap-10">
            <div className="space-y-8">
              <span className="inline-flex rounded-full bg-[#D1FAE5] px-3.5 py-1.5 text-sm font-medium text-primary">
                Trust Score + Fit Score
              </span>

              <div className="space-y-5">
                <h1 className="max-w-xl text-4xl font-bold leading-[1.12] tracking-tight text-[#0F172A] lg:text-[3.15rem]">
                  İş aramayı{' '}
                  <span className="text-primary">güvenilir</span> ve{' '}
                  <span className="text-primary">kişisel</span> hale getir.
                </h1>
                <p className="max-w-xl text-base leading-7 text-[#64748B] lg:text-[17px]">
                  FitCareer, ilanların güvenilirliğini ve profiline uyumunu aynı ekranda gösterir.
                  Klasik job board değil; kariyer kararlarını destekleyen bir platform.
                </p>
              </div>

              <div className="flex flex-wrap gap-3">
                <Link to="/register">
                  <Button size="lg" className="h-12 px-6">
                    <span>Ücretsiz Başla</span>
                    <ArrowRight className="h-4 w-4" aria-hidden="true" />
                  </Button>
                </Link>
                <Link to="/login">
                  <Button size="lg" variant="outline" className="h-12 border-[#E2E8F0] px-6">
                    <span>Giriş Yap</span>
                    <ArrowRight className="h-4 w-4" aria-hidden="true" />
                  </Button>
                </Link>
              </div>

              <div className="grid gap-4 sm:grid-cols-3">
                {miniFeatures.map((feature) => {
                  const Icon = feature.icon;

                  return (
                    <div key={feature.title} className="flex items-start gap-3">
                      <span className="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-[#D1FAE5] text-primary">
                        <Icon className="h-[18px] w-[18px]" aria-hidden="true" />
                      </span>
                      <div>
                        <p className="text-sm font-semibold text-[#0F172A]">{feature.title}</p>
                        <p className="text-xs leading-5 text-[#64748B]">{feature.description}</p>
                      </div>
                    </div>
                  );
                })}
              </div>
            </div>

            <div className="rounded-[20px] border border-[#E2E8F0] bg-white p-7 shadow-[0_4px_24px_rgba(15,23,42,0.06),0_12px_48px_rgba(15,23,42,0.04)] sm:p-8">
              <h2 className="text-xl font-bold text-[#0F172A]">Neden FitCareer?</h2>
              <ul className="mt-6 space-y-5">
                {whyFeatures.map((feature) => {
                  const Icon = feature.icon;

                  return (
                    <li key={feature.title} className="flex items-start gap-4">
                      <span className="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#D1FAE5] text-primary">
                        <Icon className="h-5 w-5" aria-hidden="true" />
                      </span>
                      <div className="space-y-1">
                        <p className="text-sm font-semibold leading-6 text-[#0F172A]">{feature.title}</p>
                        <p className="text-sm leading-6 text-[#64748B]">{feature.description}</p>
                      </div>
                    </li>
                  );
                })}
              </ul>
            </div>
          </div>
        </section>

        <section className="mx-auto max-w-6xl px-4 pb-20 lg:px-8">
          <div className="overflow-hidden rounded-[20px] border border-[#E2E8F0] bg-white shadow-[0_4px_24px_rgba(15,23,42,0.06),0_12px_48px_rgba(15,23,42,0.04)]">
            <div className="flex items-center gap-1.5 border-b border-[#E2E8F0] bg-[#F8FAFC] px-4 py-3">
              <span className="h-2.5 w-2.5 rounded-full bg-[#E2E8F0]" />
              <span className="h-2.5 w-2.5 rounded-full bg-[#E2E8F0]" />
              <span className="h-2.5 w-2.5 rounded-full bg-[#E2E8F0]" />
            </div>
            <img
              src="/assets/product-dashboard.png"
              alt="FitCareer aday paneli — Trust Score ve Fit Score ile ilan önerileri"
              className="w-full"
              loading="lazy"
            />
          </div>
          <p className="mt-4 text-center text-sm text-[#64748B]">
            Gerçek aday paneli: güvenilirlik skoruna göre sıralanan ilanlar ve profil uyumu.
          </p>
        </section>
      </main>
    </div>
  );
}

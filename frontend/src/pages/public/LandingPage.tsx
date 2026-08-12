import {
  ArrowRight,
  BriefcaseBusiness,
  ChartLine,
  FileUser,
  ShieldCheck,
  Target,
  Users,
  Zap,
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

const stats = [
  { value: '%95', label: 'Güvenilir İlanlar', icon: ShieldCheck },
  { value: '50K+', label: 'Aktif Kullanıcı', icon: Users },
  { value: '10K+', label: 'Şirket', icon: BriefcaseBusiness },
  { value: '7/24', label: 'Güncel İlanlar', icon: Zap },
] as const;

const partners = ['trendyol', 'hepsiburada', 'aselsan', 'TURKISH AIRLINES', 'Garanti BBVA', 'Koç'];

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

          <div className="mt-12 rounded-[20px] border border-[#E2E8F0] bg-white px-4 py-5 shadow-[0_1px_3px_rgba(15,23,42,0.05)] sm:px-6">
            <div className="grid grid-cols-2 gap-6 lg:grid-cols-4">
              {stats.map((stat, index) => {
                const Icon = stat.icon;

                return (
                  <div
                    key={stat.label}
                    className={`flex items-center gap-3 ${index > 0 ? 'lg:border-l lg:border-[#E2E8F0] lg:pl-6' : ''}`}
                  >
                    <span className="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#D1FAE5] text-primary">
                      <Icon className="h-5 w-5" aria-hidden="true" />
                    </span>
                    <div>
                      <p className="text-lg font-bold text-[#0F172A]">{stat.value}</p>
                      <p className="text-sm text-[#64748B]">{stat.label}</p>
                    </div>
                  </div>
                );
              })}
            </div>
          </div>
        </section>

        <section className="relative border-t border-[#E2E8F0] bg-white py-12">
          <div className="mx-auto max-w-6xl px-4 text-center lg:px-8">
            <p className="text-sm text-[#64748B]">
              Binlerce şirket ve on binlerce aday FitCareer ile buluşuyor.
            </p>
            <div className="mt-8 flex flex-wrap items-center justify-center gap-x-10 gap-y-4">
              {partners.map((partner) => (
                <span
                  key={partner}
                  className="text-sm font-semibold uppercase tracking-wide text-[#94A3B8]"
                >
                  {partner}
                </span>
              ))}
            </div>
          </div>
        </section>
      </main>
    </div>
  );
}

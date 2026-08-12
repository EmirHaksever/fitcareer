import {
  Bell,
  Bookmark,
  BriefcaseBusiness,
  ChartPie,
  FileUser,
  Home,
  LayoutDashboard,
  Settings,
  Sparkles,
  Users,
} from 'lucide-react';
import { NavLink } from 'react-router-dom';
import { Logo } from '@/components/brand/Logo';
import { Button } from '@/components/ui/Button';
import { Card, CardBody } from '@/components/ui/Card';
import { useAuth } from '@/hooks/useAuth';
import { cn } from '@/utils/format';

const candidateNav = [
  { to: '/dashboard', label: 'Ana Sayfa', icon: Home },
  { to: '/jobs', label: 'İş İlanları', icon: BriefcaseBusiness },
  { to: '/applications', label: 'Başvurularım', icon: LayoutDashboard },
  { to: '/profile', label: 'CV Profilim', icon: FileUser },
  { to: '/fit-analysis', label: 'Uyum Analizi', icon: ChartPie },
  { to: '/saved', label: 'Kaydedilenler', icon: Bookmark },
  { to: '/notifications', label: 'Bildirimler', icon: Bell },
  { to: '/settings', label: 'Ayarlar', icon: Settings },
];

const companyNav = [
  { to: '/company/dashboard', label: 'Ana Sayfa', icon: Home },
  { to: '/company/jobs', label: 'İlanlarım', icon: BriefcaseBusiness },
  { to: '/company/applications', label: 'Başvurular', icon: Users },
];

interface SidebarProps {
  onNavigate?: () => void;
}

export function Sidebar({ onNavigate }: SidebarProps) {
  const { user } = useAuth();
  const navItems = user?.role === 'company' ? companyNav : candidateNav;

  return (
    <aside className="flex h-full w-72 flex-col border-r border-surface bg-white">
      <div className="border-b border-surface px-5 py-4">
        <Logo size="sm" />
      </div>

      <nav className="flex-1 space-y-1 px-3 py-4">
        {navItems.map((item) => {
          const Icon = item.icon;

          return (
            <NavLink
              key={item.to}
              to={item.to}
              onClick={onNavigate}
              className={({ isActive }) =>
                cn(
                  'flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition',
                  isActive
                    ? 'bg-primary/10 text-primary'
                    : 'text-ink-muted hover:bg-background hover:text-ink',
                )
              }
            >
              <Icon className="h-4 w-4" aria-hidden="true" />
              {item.label}
            </NavLink>
          );
        })}
      </nav>

      <div className="px-4 pb-5">
        {user?.role === 'company' ? (
          <Card className="overflow-hidden border-primary/10 bg-gradient-to-br from-primary/5 to-secondary/5">
            <CardBody className="space-y-3">
              <div className="flex items-center gap-2 text-primary">
                <BriefcaseBusiness className="h-4 w-4" aria-hidden="true" />
                <p className="text-sm font-semibold">İlan & Aday Yönetimi</p>
              </div>
              <p className="text-xs leading-5 text-ink-muted">
                Yeni ilan oluştur, başvuruları incele ve süreci takip et.
              </p>
              <NavLink to="/company/jobs/new" onClick={onNavigate}>
                <Button className="w-full" size="sm">
                  Yeni İlan Oluştur
                </Button>
              </NavLink>
            </CardBody>
          </Card>
        ) : (
          <Card className="overflow-hidden border-primary/10 bg-gradient-to-br from-primary/5 to-secondary/5">
            <CardBody className="space-y-3">
              <div className="flex items-center gap-2 text-primary">
                <Sparkles className="h-4 w-4" aria-hidden="true" />
                <p className="text-sm font-semibold">Kariyer Asistanı</p>
              </div>
              <p className="text-xs leading-5 text-ink-muted">
                CV uyum analizini başlat ve sana en uygun ilanları keşfet.
              </p>
              <Button className="w-full" size="sm" disabled>
                Analiz Başlat
              </Button>
              <p className="text-[11px] text-ink-subtle">TODO: Fit analysis endpoint bekleniyor</p>
            </CardBody>
          </Card>
        )}
      </div>
    </aside>
  );
}

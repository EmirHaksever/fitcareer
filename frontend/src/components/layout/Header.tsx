import { Menu, Search } from 'lucide-react';
import { Avatar } from '@/components/ui/Avatar';
import { useAuth } from '@/hooks/useAuth';

interface HeaderProps {
  onMenuClick?: () => void;
}

export function Header({ onMenuClick }: HeaderProps) {
  const { user } = useAuth();

  return (
    <header className="flex h-16 items-center gap-4 border-b border-surface bg-white px-4 lg:px-6">
      <button
        type="button"
        className="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-surface text-ink-muted lg:hidden"
        onClick={onMenuClick}
        aria-label="Menüyü aç"
      >
        <Menu className="h-5 w-5" />
      </button>

      <div className="relative hidden flex-1 md:block">
        <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-subtle" />
        <input
          type="search"
          placeholder="Pozisyon, şirket veya anahtar kelime ara..."
          className="h-11 w-full max-w-xl rounded-xl border border-surface bg-background pl-10 pr-4 text-sm outline-none transition focus:border-primary focus:ring-4 focus:ring-primary/10"
          disabled
          aria-label="Global arama"
        />
      </div>

      <div className="ml-auto flex items-center gap-3">
        <button
          type="button"
          className="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-surface text-ink-muted"
          aria-label="Bildirimler"
        >
          <span className="relative">
            <span className="absolute right-0 top-0 h-2 w-2 rounded-full bg-warning" />
            <svg viewBox="0 0 24 24" className="h-5 w-5 fill-none stroke-current" aria-hidden="true">
              <path d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 1 0-12 0v3.2c0 .5-.2 1-.6 1.4L4 17h5m6 0a3 3 0 1 1-6 0" />
            </svg>
          </span>
        </button>
        {user ? <Avatar name={user.name} /> : null}
      </div>
    </header>
  );
}

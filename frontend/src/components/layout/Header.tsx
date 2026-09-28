import { useState, type FormEvent } from 'react';
import { Bell, Menu, Search } from 'lucide-react';
import { Link, useNavigate } from 'react-router-dom';
import { Avatar } from '@/components/ui/Avatar';
import { useAuth } from '@/hooks/useAuth';
import { useNotificationUnreadCount } from '@/hooks/useNotifications';

interface HeaderProps {
  onMenuClick?: () => void;
}

export function Header({ onMenuClick }: HeaderProps) {
  const { user } = useAuth();
  const navigate = useNavigate();
  const [searchTerm, setSearchTerm] = useState('');
  const { data: unreadData } = useNotificationUnreadCount(user?.role === 'candidate');
  const unreadCount = unreadData?.unread_count ?? 0;

  function handleSearch(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();

    const keyword = searchTerm.trim();
    if (!keyword) {
      navigate('/jobs');
      return;
    }

    navigate(`/jobs?keyword=${encodeURIComponent(keyword)}`);
  }

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

      <form className="relative hidden flex-1 md:block" onSubmit={handleSearch} role="search">
        <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-subtle" />
        <input
          type="search"
          placeholder="Pozisyon, şirket veya anahtar kelime ara..."
          className="h-11 w-full max-w-xl rounded-xl border border-surface bg-background pl-10 pr-4 text-sm outline-none transition focus:border-primary focus:ring-4 focus:ring-primary/10"
          aria-label="Global arama"
          value={searchTerm}
          onChange={(event) => setSearchTerm(event.target.value)}
          onKeyDown={(event) => {
            if (event.key === 'Escape') {
              setSearchTerm('');
            }
          }}
        />
      </form>

      <div className="ml-auto flex items-center gap-3">
        {user?.role !== 'company' ? (
        <Link
          to="/notifications"
          className="relative inline-flex h-10 w-10 items-center justify-center rounded-xl border border-surface text-ink-muted transition hover:bg-background hover:text-primary"
          aria-label={unreadCount > 0 ? `Bildirimler, ${unreadCount} okunmamış` : 'Bildirimler'}
        >
          <Bell className="h-5 w-5" aria-hidden="true" />
          {unreadCount > 0 ? (
            <span className="absolute -right-1 -top-1 inline-flex min-h-5 min-w-5 items-center justify-center rounded-full bg-warning px-1 text-[10px] font-bold text-white">
              {unreadCount > 9 ? '9+' : unreadCount}
            </span>
          ) : null}
        </Link>
        ) : null}
        {user ? <Avatar name={user.name} /> : null}
      </div>
    </header>
  );
}

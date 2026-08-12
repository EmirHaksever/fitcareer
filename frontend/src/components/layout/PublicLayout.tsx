import { Link } from 'react-router-dom';
import { Logo } from '@/components/brand/Logo';
import { Button } from '@/components/ui/Button';

export function PublicLayout() {
  return (
    <div className="min-h-screen bg-background">
      <header className="border-b border-surface bg-white">
        <div className="mx-auto flex h-[76px] max-w-6xl items-center justify-between px-4 lg:px-8">
          <Link to="/" className="inline-flex shrink-0 items-center">
            <Logo size="md" />
          </Link>
          <div className="flex items-center gap-3">
            <Link to="/login">
              <Button variant="ghost">Giriş Yap</Button>
            </Link>
            <Link to="/register">
              <Button>Kayıt Ol</Button>
            </Link>
          </div>
        </div>
      </header>
      <main />
    </div>
  );
}

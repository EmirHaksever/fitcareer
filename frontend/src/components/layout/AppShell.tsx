import { useState } from 'react';
import { Outlet } from 'react-router-dom';
import { Header } from '@/components/layout/Header';
import { Sidebar } from '@/components/layout/Sidebar';
import { cn } from '@/utils/format';

export function AppShell() {
  const [mobileOpen, setMobileOpen] = useState(false);

  return (
    <div className="min-h-screen bg-background text-ink">
      <div className="flex min-h-screen">
        <div className="hidden lg:block">
          <Sidebar />
        </div>

        <div
          className={cn(
            'fixed inset-0 z-40 bg-black/30 lg:hidden',
            mobileOpen ? 'block' : 'hidden',
          )}
          onClick={() => setMobileOpen(false)}
          aria-hidden="true"
        />

        <div
          className={cn(
            'fixed inset-y-0 left-0 z-50 w-72 transform bg-white transition lg:hidden',
            mobileOpen ? 'translate-x-0' : '-translate-x-full',
          )}
        >
          <Sidebar onNavigate={() => setMobileOpen(false)} />
        </div>

        <div className="flex min-h-screen flex-1 flex-col">
          <Header onMenuClick={() => setMobileOpen(true)} />
          <main className="flex-1 px-4 py-6 lg:px-8 lg:py-8">
            <Outlet />
          </main>
        </div>
      </div>
    </div>
  );
}

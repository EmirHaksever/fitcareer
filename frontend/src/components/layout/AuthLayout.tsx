import { Outlet } from 'react-router-dom';
import { AuthBrandingMobile, AuthBrandingPanel } from '@/components/auth/AuthBrandingPanel';

export function AuthLayout() {
  return (
    <div className="relative min-h-screen overflow-x-hidden bg-[#F5F7F8]">
      <div className="relative mx-auto flex min-h-screen w-full max-w-[1280px] items-center px-6 py-10 sm:px-10 lg:px-12">
        <div className="hidden w-1/2 shrink-0 pr-12 xl:pr-20 lg:block">
          <AuthBrandingPanel />
        </div>

        <div className="flex w-full flex-1 items-center justify-center lg:w-1/2 lg:justify-end">
          <div className="w-full max-w-[460px] space-y-8">
            <AuthBrandingMobile />
            <Outlet />
          </div>
        </div>
      </div>
    </div>
  );
}

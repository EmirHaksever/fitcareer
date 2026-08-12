import { Navigate, Outlet } from 'react-router-dom';
import { LoadingState } from '@/components/ui/States';
import { useAuth } from '@/hooks/useAuth';
import type { UserRole } from '@/types/api';
import { getDefaultRouteForRole } from '@/utils/routing';

interface ProtectedRouteProps {
  allowedRoles?: UserRole[];
}

export function ProtectedRoute({ allowedRoles }: ProtectedRouteProps) {
  const { isAuthenticated, isBootstrapping, user } = useAuth();

  if (isBootstrapping) {
    return <LoadingState label="Oturum kontrol ediliyor..." />;
  }

  if (!isAuthenticated || !user) {
    return <Navigate to="/login" replace />;
  }

  if (allowedRoles && !allowedRoles.includes(user.role)) {
    return <Navigate to={getDefaultRouteForRole(user.role)} replace />;
  }

  return <Outlet />;
}

export function GuestRoute() {
  const { isAuthenticated, isBootstrapping, user } = useAuth();

  if (isBootstrapping) {
    return <LoadingState label="Yükleniyor..." />;
  }

  if (isAuthenticated && user) {
    return <Navigate to={getDefaultRouteForRole(user.role)} replace />;
  }

  return <Outlet />;
}

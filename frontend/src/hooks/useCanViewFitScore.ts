import { useAuth } from '@/hooks/useAuth';

export function useCanViewFitScore(): boolean {
  const { user } = useAuth();

  return user?.role === 'candidate';
}

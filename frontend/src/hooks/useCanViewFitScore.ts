import { useAuth } from '@/hooks/useAuth';
import { useCandidateProfile } from '@/hooks/useCandidateProfile';

export function useCanViewFitScore(): boolean {
  const { user } = useAuth();
  const { data: profile } = useCandidateProfile({ enabled: user?.role === 'candidate' });

  return user?.role === 'candidate' && profile?.has_cv === true;
}

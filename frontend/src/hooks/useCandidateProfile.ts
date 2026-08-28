import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { candidateProfileApi } from '@/api/candidate/profile';
import type { UpdateCandidateProfilePayload } from '@/types/candidate';
import { invalidateFitRelatedQueries } from '@/hooks/invalidateFitQueries';

export const CANDIDATE_PROFILE_KEY = ['candidate', 'profile'] as const;
export const CANDIDATE_CV_KEY = ['candidate', 'cv'] as const;

export function useCandidateProfile() {
  return useQuery({
    queryKey: CANDIDATE_PROFILE_KEY,
    queryFn: () => candidateProfileApi.get(),
  });
}

export function useCandidateCv() {
  return useQuery({
    queryKey: CANDIDATE_CV_KEY,
    queryFn: () => candidateProfileApi.getCv(),
  });
}

export function useCandidateProfileMutations() {
  const queryClient = useQueryClient();

  const invalidateProfile = () => {
    void queryClient.invalidateQueries({ queryKey: CANDIDATE_PROFILE_KEY });
    void queryClient.invalidateQueries({ queryKey: CANDIDATE_CV_KEY });
  };

  const invalidateProfileAndFit = () => {
    invalidateProfile();
    invalidateFitRelatedQueries(queryClient);
  };

  const updateProfile = useMutation({
    mutationFn: (payload: UpdateCandidateProfilePayload) => candidateProfileApi.update(payload),
    onSuccess: invalidateProfileAndFit,
  });

  const uploadPhoto = useMutation({
    mutationFn: (file: File) => candidateProfileApi.uploadPhoto(file),
    onSuccess: invalidateProfile,
  });

  const deletePhoto = useMutation({
    mutationFn: () => candidateProfileApi.deletePhoto(),
    onSuccess: invalidateProfile,
  });

  const uploadCv = useMutation({
    mutationFn: (file: File) => candidateProfileApi.uploadCv(file),
    onSuccess: invalidateProfileAndFit,
  });

  const deleteCv = useMutation({
    mutationFn: () => candidateProfileApi.deleteCv(),
    onSuccess: invalidateProfileAndFit,
  });

  return {
    updateProfile,
    uploadPhoto,
    deletePhoto,
    uploadCv,
    deleteCv,
    invalidateProfile,
    invalidateProfileAndFit,
  };
}

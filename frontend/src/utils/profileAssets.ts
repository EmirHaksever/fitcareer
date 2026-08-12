import { useCallback } from 'react';
import { candidateProfileApi } from '@/api/candidate/profile';

export function openBlobInNewTab(blob: Blob) {
  const url = URL.createObjectURL(blob);
  window.open(url, '_blank', 'noopener,noreferrer');
  window.setTimeout(() => URL.revokeObjectURL(url), 60_000);
}

export function downloadBlob(blob: Blob, filename: string) {
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = filename;
  document.body.appendChild(link);
  link.click();
  link.remove();
  URL.revokeObjectURL(url);
}

export function useProfilePhotoBlob(profilePhotoPath: string | null) {
  const fetchPhoto = useCallback(() => candidateProfileApi.downloadPhoto(), []);

  return {
    fetchPhoto,
    enabled: Boolean(profilePhotoPath),
    refreshKey: profilePhotoPath,
  };
}

export async function previewCv() {
  const blob = await candidateProfileApi.downloadCv();
  openBlobInNewTab(blob);
}

export async function downloadCvFile(filename?: string | null) {
  const blob = await candidateProfileApi.downloadCv();
  downloadBlob(blob, filename ?? 'cv.pdf');
}

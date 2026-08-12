import { useRef, useState } from 'react';
import { Eye, Upload } from 'lucide-react';
import { Button } from '@/components/ui/Button';
import { getApiErrorMessage } from '@/api/client';
import { previewCv } from '@/utils/profileAssets';

export function useProfileCvActions({
  hasCv,
  onUpload,
  onError,
}: {
  hasCv: boolean;
  filename: string;
  onUpload: (file: File) => Promise<void>;
  onError?: (message: string) => void;
}) {
  const fileRef = useRef<HTMLInputElement>(null);
  const [previewLoading, setPreviewLoading] = useState(false);
  const [uploadLoading, setUploadLoading] = useState(false);

  async function handlePreview() {
    if (!hasCv) return;
    setPreviewLoading(true);
    try {
      await previewCv();
    } catch (error) {
      onError?.(getApiErrorMessage(error, 'CV önizlenemedi.'));
    } finally {
      setPreviewLoading(false);
    }
  }

  async function handleUpload(file: File | null) {
    if (!file) return;
    setUploadLoading(true);
    try {
      await onUpload(file);
      if (fileRef.current) fileRef.current.value = '';
    } catch (error) {
      onError?.(getApiErrorMessage(error, 'CV yüklenemedi.'));
    } finally {
      setUploadLoading(false);
    }
  }

  const uploadButton = (
    <>
      <input
        ref={fileRef}
        type="file"
        accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
        className="hidden"
        onChange={(e) => void handleUpload(e.target.files?.[0] ?? null)}
      />
      <Button type="button" variant="outline" size="sm" onClick={() => fileRef.current?.click()} loading={uploadLoading}>
        <Upload className="h-4 w-4" aria-hidden="true" />
        CV Yükle / Güncelle
      </Button>
    </>
  );

  const previewButton = hasCv ? (
    <Button type="button" variant="outline" size="sm" onClick={() => void handlePreview()} loading={previewLoading}>
      <Eye className="h-4 w-4" aria-hidden="true" />
      Önizle
    </Button>
  ) : (
    <Button type="button" variant="outline" size="sm" disabled title="Önce CV yükleyin">
      <Eye className="h-4 w-4" aria-hidden="true" />
      Önizle
    </Button>
  );

  return { uploadButton, previewButton };
}

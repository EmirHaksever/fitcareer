import { useState } from 'react';
import { Eye, FileText, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/Button';
import { ProfileSectionCard } from '@/components/profile/ProfileSectionCard';
import { EmptyState } from '@/components/ui/States';
import { useCandidateCv, useCandidateProfileMutations } from '@/hooks/useCandidateProfile';
import { getApiErrorMessage } from '@/api/client';
import { formatDate } from '@/utils/format';
import { previewCv } from '@/utils/profileAssets';
import type { CandidateProfile } from '@/types/candidate';

interface ProfileCvSectionProps {
  profile: CandidateProfile;
  onUpdated?: (message: string) => void;
  onError?: (message: string) => void;
}

export function ProfileCvSection({ profile, onUpdated, onError }: ProfileCvSectionProps) {
  const { data: cvMeta, isLoading } = useCandidateCv();
  const { deleteCv } = useCandidateProfileMutations();
  const [previewLoading, setPreviewLoading] = useState(false);
  const filename = cvMeta?.source_filename ?? cvMeta?.cv_parsed_data?.source_filename ?? 'cv.pdf';

  async function handleDelete() {
    if (!window.confirm('CV dosyasını silmek istediğinize emin misiniz?')) return;
    try {
      await deleteCv.mutateAsync();
      onUpdated?.('CV silindi.');
    } catch (error) {
      onError?.(getApiErrorMessage(error, 'CV silinemedi.'));
    }
  }

  async function handlePreview() {
    setPreviewLoading(true);
    try {
      await previewCv();
    } catch (error) {
      onError?.(getApiErrorMessage(error, 'CV önizlenemedi.'));
    } finally {
      setPreviewLoading(false);
    }
  }

  const parsed = cvMeta?.cv_parsed_data;

  return (
    <ProfileSectionCard title="CV">
      {isLoading ? <p className="text-sm text-ink-muted">CV bilgisi yükleniyor...</p> : null}

      {!isLoading && !profile.has_cv ? (
        <EmptyState
          title="Henüz CV yüklenmedi"
          description="Sayfanın üstündeki CV Yükle / Güncelle butonunu kullanarak PDF, DOC veya DOCX yükleyebilirsin."
        />
      ) : null}

      {!isLoading && profile.has_cv ? (
        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <div className="flex items-start gap-3">
            <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-primary/10 text-primary">
              <FileText className="h-5 w-5" aria-hidden="true" />
            </div>
            <div>
              <p className="text-sm font-semibold text-ink">{filename}</p>
              {parsed?.parsed_at ? (
                <p className="text-xs text-ink-muted">Yüklenme: {formatDate(parsed.parsed_at)}</p>
              ) : null}
              <p className="text-xs text-ink-muted">PDF, DOC veya DOCX</p>
            </div>
          </div>
          <div className="flex flex-wrap gap-2">
            <Button type="button" variant="outline" size="sm" onClick={() => void handlePreview()} loading={previewLoading}>
              <Eye className="h-4 w-4" aria-hidden="true" />
              Önizle
            </Button>
            <Button type="button" variant="outline" size="sm" onClick={() => void handleDelete()} loading={deleteCv.isPending}>
              <Trash2 className="h-4 w-4" aria-hidden="true" />
              CV Sil
            </Button>
          </div>
        </div>
      ) : null}
    </ProfileSectionCard>
  );
}

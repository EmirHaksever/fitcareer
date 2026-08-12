import { useState } from 'react';
import { Pencil } from 'lucide-react';
import { Button } from '@/components/ui/Button';
import { Modal } from '@/components/ui/Modal';
import { ProfileSectionCard } from '@/components/profile/ProfileSectionCard';
import { useCandidateProfileMutations } from '@/hooks/useCandidateProfile';
import { getApiErrorMessage, getValidationErrors } from '@/api/client';
import type { CandidateProfile } from '@/types/candidate';

interface ProfileAboutSectionProps {
  profile: CandidateProfile;
  onUpdated?: (message: string) => void;
  onError?: (message: string) => void;
}

export function ProfileAboutSection({ profile, onUpdated, onError }: ProfileAboutSectionProps) {
  const { updateProfile } = useCandidateProfileMutations();
  const [open, setOpen] = useState(false);
  const [headline, setHeadline] = useState('');
  const [summary, setSummary] = useState('');
  const [errors, setErrors] = useState<Record<string, string>>({});

  function openEdit() {
    setHeadline(profile.headline ?? '');
    setSummary(profile.summary ?? '');
    setErrors({});
    setOpen(true);
  }

  async function handleSave() {
    try {
      await updateProfile.mutateAsync({ headline: headline || null, summary: summary || null });
      setOpen(false);
      onUpdated?.('Hakkımda bilgileri güncellendi.');
    } catch (error) {
      const validation = getValidationErrors(error);
      setErrors({
        headline: validation.headline?.[0] ?? '',
        summary: validation.summary?.[0] ?? '',
      });
      onError?.(getApiErrorMessage(error, 'Güncelleme başarısız.'));
    }
  }

  return (
    <>
      <ProfileSectionCard
        title="Hakkımda"
        action={
          <Button type="button" variant="outline" size="sm" onClick={openEdit}>
            <Pencil className="h-4 w-4" aria-hidden="true" />
            Düzenle
          </Button>
        }
      >
        <div className="space-y-4">
          <div>
            <p className="text-xs font-medium text-ink-muted">Başlık</p>
            <p className="text-sm text-ink">{profile.headline ?? 'Henüz eklenmedi.'}</p>
          </div>
          <div>
            <p className="text-xs font-medium text-ink-muted">Özet</p>
            <p className="whitespace-pre-wrap text-sm leading-7 text-ink-muted">
              {profile.summary ?? 'Henüz eklenmedi.'}
            </p>
          </div>
        </div>
      </ProfileSectionCard>

      <Modal open={open} title="Hakkımda Düzenle" onClose={() => setOpen(false)}>
        <div className="space-y-4">
          <label className="block space-y-2">
            <span className="text-sm font-medium text-ink-muted">Başlık</span>
            <input
              value={headline}
              onChange={(e) => setHeadline(e.target.value)}
              className="h-11 w-full rounded-xl border border-surface bg-white px-3 text-sm outline-none focus:border-primary focus:ring-4 focus:ring-primary/10"
            />
            {errors.headline ? <span className="text-sm text-danger">{errors.headline}</span> : null}
          </label>
          <label className="block space-y-2">
            <span className="text-sm font-medium text-ink-muted">Özet</span>
            <textarea
              value={summary}
              onChange={(e) => setSummary(e.target.value)}
              rows={6}
              className="w-full rounded-xl border border-surface bg-white px-3 py-2 text-sm outline-none focus:border-primary focus:ring-4 focus:ring-primary/10"
            />
            {errors.summary ? <span className="text-sm text-danger">{errors.summary}</span> : null}
          </label>
          <div className="flex justify-end gap-2">
            <Button type="button" variant="outline" onClick={() => setOpen(false)}>
              Vazgeç
            </Button>
            <Button type="button" onClick={() => void handleSave()} loading={updateProfile.isPending}>
              Kaydet
            </Button>
          </div>
        </div>
      </Modal>
    </>
  );
}

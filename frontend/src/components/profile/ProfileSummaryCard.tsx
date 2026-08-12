import { useCallback, useRef, useState } from 'react';
import { Camera, Pencil, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/Button';
import { Modal } from '@/components/ui/Modal';
import { Input } from '@/components/ui/Input';
import { ProfileSectionCard } from '@/components/profile/ProfileSectionCard';
import { selectClassName, WORK_PREFERENCE_OPTIONS } from '@/components/profile/profileFormOptions';
import { useCandidateProfileMutations } from '@/hooks/useCandidateProfile';
import { useAuthenticatedBlob } from '@/hooks/useAuthenticatedBlob';
import { useAuth } from '@/hooks/useAuth';
import { candidateProfileApi } from '@/api/candidate/profile';
import { getApiErrorMessage, getValidationErrors } from '@/api/client';
import type { CandidateProfile, UpdateCandidateProfilePayload } from '@/types/candidate';
import { sanitizePayload } from '@/utils/payload';
import { cn, formatLocation, formatWorkPreference } from '@/utils/format';

interface ProfileSummaryCardProps {
  profile: CandidateProfile;
  onUpdated?: (message: string) => void;
  onError?: (message: string) => void;
}

function ProfileAvatar({
  name,
  imageUrl,
}: {
  name: string;
  imageUrl: string | null;
}) {
  const initials = name
    .split(' ')
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase() ?? '')
    .join('');

  if (imageUrl) {
    return (
      <img
        src={imageUrl}
        alt={name}
        className="h-20 w-20 rounded-full object-cover ring-2 ring-primary/10"
      />
    );
  }

  return (
    <div
      className={cn(
        'flex h-20 w-20 items-center justify-center rounded-full bg-primary/10 text-xl font-semibold text-primary ring-2 ring-primary/10',
      )}
      aria-label={name}
    >
      {initials}
    </div>
  );
}

export function ProfileSummaryCard({ profile, onUpdated, onError }: ProfileSummaryCardProps) {
  const { user } = useAuth();
  const { updateProfile, uploadPhoto, deletePhoto } = useCandidateProfileMutations();
  const [editOpen, setEditOpen] = useState(false);
  const [localPhotoPreview, setLocalPhotoPreview] = useState<string | null>(null);
  const photoInputRef = useRef<HTMLInputElement>(null);
  const [form, setForm] = useState<UpdateCandidateProfilePayload>({});
  const [formErrors, setFormErrors] = useState<Record<string, string>>({});

  const fetchPhoto = useCallback(() => candidateProfileApi.downloadPhoto(), []);
  const storedPhotoUrl = useAuthenticatedBlob(fetchPhoto, Boolean(profile.profile_photo_path), profile.profile_photo_path);
  const displayPhotoUrl = localPhotoPreview ?? storedPhotoUrl;

  const displayName = user?.name ?? 'Aday';

  function openEdit() {
    setForm({
      headline: profile.headline,
      city: profile.city,
      country: profile.country,
      desired_position: profile.desired_position,
      work_preference: profile.work_preference,
      linkedin_url: profile.linkedin_url,
      github_url: profile.github_url,
      portfolio_url: profile.portfolio_url,
      open_to_work: profile.open_to_work,
      years_of_experience: profile.years_of_experience,
    });
    setFormErrors({});
    setEditOpen(true);
  }

  async function handleSave() {
    try {
      await updateProfile.mutateAsync(sanitizePayload(form));
      setEditOpen(false);
      onUpdated?.('Profil bilgileri güncellendi.');
    } catch (error) {
      const validation = getValidationErrors(error);
      if (Object.keys(validation).length > 0) {
        const mapped = Object.fromEntries(
          Object.entries(validation).map(([key, value]) => [key, value[0] ?? '']),
        );
        setFormErrors(mapped);
      }
      onError?.(getApiErrorMessage(error, 'Profil güncellenemedi.'));
    }
  }

  async function handlePhotoChange(file: File | null) {
    if (!file) return;
    const preview = URL.createObjectURL(file);
    setLocalPhotoPreview(preview);
    try {
      await uploadPhoto.mutateAsync(file);
      onUpdated?.('Profil fotoğrafı yüklendi.');
    } catch (error) {
      URL.revokeObjectURL(preview);
      setLocalPhotoPreview(null);
      onError?.(getApiErrorMessage(error, 'Fotoğraf yüklenemedi.'));
    }
  }

  async function handlePhotoDelete() {
    if (!window.confirm('Profil fotoğrafını silmek istediğinize emin misiniz?')) return;
    try {
      await deletePhoto.mutateAsync();
      if (localPhotoPreview) URL.revokeObjectURL(localPhotoPreview);
      setLocalPhotoPreview(null);
      onUpdated?.('Profil fotoğrafı silindi.');
    } catch (error) {
      onError?.(getApiErrorMessage(error, 'Fotoğraf silinemedi.'));
    }
  }

  return (
    <>
      <ProfileSectionCard
        title="Profil Özeti"
        action={
          <Button type="button" variant="outline" size="sm" onClick={openEdit}>
            <Pencil className="h-4 w-4" aria-hidden="true" />
            Düzenle
          </Button>
        }
      >
        <div className="flex flex-col gap-5 sm:flex-row sm:items-start">
          <div className="flex flex-col items-center gap-3 sm:items-start">
            <ProfileAvatar name={displayName} imageUrl={displayPhotoUrl} />
            <div className="flex gap-2">
              <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={() => photoInputRef.current?.click()}
                loading={uploadPhoto.isPending}
              >
                <Camera className="h-4 w-4" aria-hidden="true" />
                Fotoğraf
              </Button>
              {profile.profile_photo_path || displayPhotoUrl ? (
                <Button
                  type="button"
                  variant="ghost"
                  size="sm"
                  onClick={() => void handlePhotoDelete()}
                  loading={deletePhoto.isPending}
                >
                  <Trash2 className="h-4 w-4" aria-hidden="true" />
                </Button>
              ) : null}
            </div>
            <input
              ref={photoInputRef}
              type="file"
              accept="image/jpeg,image/jpg,image/png,image/webp"
              className="hidden"
              onChange={(event) => {
                void handlePhotoChange(event.target.files?.[0] ?? null);
                event.target.value = '';
              }}
            />
          </div>

          <div className="grid flex-1 gap-3 sm:grid-cols-2">
            <div>
              <p className="text-xs font-medium text-ink-muted">Ad Soyad</p>
              <p className="text-sm font-medium text-ink">{displayName}</p>
            </div>
            <div>
              <p className="text-xs font-medium text-ink-muted">E-posta</p>
              <p className="text-sm text-ink">{user?.email ?? '—'}</p>
            </div>
            <div>
              <p className="text-xs font-medium text-ink-muted">Başlık</p>
              <p className="text-sm text-ink">{profile.headline ?? '—'}</p>
            </div>
            <div>
              <p className="text-xs font-medium text-ink-muted">Hedef Pozisyon</p>
              <p className="text-sm text-ink">{profile.desired_position ?? '—'}</p>
            </div>
            <div>
              <p className="text-xs font-medium text-ink-muted">Konum</p>
              <p className="text-sm text-ink">{formatLocation(profile.city, profile.country)}</p>
            </div>
            <div>
              <p className="text-xs font-medium text-ink-muted">Çalışma Tercihi</p>
              <p className="text-sm text-ink">{formatWorkPreference(profile.work_preference)}</p>
            </div>
            <div>
              <p className="text-xs font-medium text-ink-muted">LinkedIn</p>
              <p className="truncate text-sm text-ink">{profile.linkedin_url ?? '—'}</p>
            </div>
            <div>
              <p className="text-xs font-medium text-ink-muted">GitHub / Portfolio</p>
              <p className="truncate text-sm text-ink">
                {profile.github_url ?? profile.portfolio_url ?? '—'}
              </p>
            </div>
          </div>
        </div>
      </ProfileSectionCard>

      <Modal open={editOpen} title="Profil Bilgilerini Düzenle" onClose={() => setEditOpen(false)}>
        <div className="space-y-4">
          <Input
            label="Başlık"
            name="headline"
            value={form.headline ?? ''}
            onChange={(e) => setForm((prev) => ({ ...prev, headline: e.target.value }))}
            error={formErrors.headline}
          />
          <Input
            label="Hedef Pozisyon"
            name="desired_position"
            value={form.desired_position ?? ''}
            onChange={(e) => setForm((prev) => ({ ...prev, desired_position: e.target.value }))}
            error={formErrors.desired_position}
          />
          <div className="grid gap-4 sm:grid-cols-2">
            <Input
              label="Şehir"
              name="city"
              value={form.city ?? ''}
              onChange={(e) => setForm((prev) => ({ ...prev, city: e.target.value }))}
              error={formErrors.city}
            />
            <Input
              label="Ülke"
              name="country"
              value={form.country ?? ''}
              onChange={(e) => setForm((prev) => ({ ...prev, country: e.target.value }))}
              error={formErrors.country}
            />
          </div>
          <label className="block space-y-2">
            <span className="text-sm font-medium text-ink-muted">Çalışma Tercihi</span>
            <select
              value={form.work_preference ?? ''}
              onChange={(e) =>
                setForm((prev) => ({
                  ...prev,
                  work_preference: e.target.value ? (e.target.value as UpdateCandidateProfilePayload['work_preference']) : null,
                }))
              }
              className={selectClassName}
            >
              <option value="">Seçiniz</option>
              {WORK_PREFERENCE_OPTIONS.map((option) => (
                <option key={option.value} value={option.value}>
                  {option.label}
                </option>
              ))}
            </select>
          </label>
          <Input
            label="LinkedIn URL"
            name="linkedin_url"
            value={form.linkedin_url ?? ''}
            onChange={(e) => setForm((prev) => ({ ...prev, linkedin_url: e.target.value }))}
            error={formErrors.linkedin_url}
          />
          <Input
            label="GitHub URL"
            name="github_url"
            value={form.github_url ?? ''}
            onChange={(e) => setForm((prev) => ({ ...prev, github_url: e.target.value }))}
            error={formErrors.github_url}
          />
          <Input
            label="Portfolio URL"
            name="portfolio_url"
            value={form.portfolio_url ?? ''}
            onChange={(e) => setForm((prev) => ({ ...prev, portfolio_url: e.target.value }))}
            error={formErrors.portfolio_url}
          />
          <label className="flex items-center gap-2 text-sm text-ink">
            <input
              type="checkbox"
              checked={form.open_to_work ?? false}
              onChange={(e) => setForm((prev) => ({ ...prev, open_to_work: e.target.checked }))}
              className="rounded border-surface text-primary focus:ring-primary"
            />
            İş arıyorum
          </label>
          <div className="flex justify-end gap-2 pt-2">
            <Button type="button" variant="outline" onClick={() => setEditOpen(false)}>
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

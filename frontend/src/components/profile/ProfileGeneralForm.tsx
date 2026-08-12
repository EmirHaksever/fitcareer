import { useCallback, useEffect, useRef, useState } from 'react';
import { Camera, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/Button';
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
import { cn } from '@/utils/format';

interface ProfileGeneralFormProps {
  profile: CandidateProfile;
  onUpdated?: (message: string) => void;
  onError?: (message: string) => void;
}

function profileToForm(profile: CandidateProfile): UpdateCandidateProfilePayload {
  return {
    headline: profile.headline,
    summary: profile.summary,
    city: profile.city,
    country: profile.country,
    desired_position: profile.desired_position,
    work_preference: profile.work_preference,
    linkedin_url: profile.linkedin_url,
    github_url: profile.github_url,
    portfolio_url: profile.portfolio_url,
    open_to_work: profile.open_to_work,
    years_of_experience: profile.years_of_experience,
  };
}

function ProfileAvatar({ name, imageUrl }: { name: string; imageUrl: string | null }) {
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
        className="h-16 w-16 rounded-full object-cover ring-2 ring-primary/10"
      />
    );
  }

  return (
    <div
      className={cn(
        'flex h-16 w-16 items-center justify-center rounded-full bg-primary/10 text-lg font-semibold text-primary ring-2 ring-primary/10',
      )}
      aria-label={name}
    >
      {initials}
    </div>
  );
}

export function ProfileGeneralForm({
  profile,
  onUpdated,
  onError,
}: ProfileGeneralFormProps) {
  const { user } = useAuth();
  const { updateProfile, uploadPhoto, deletePhoto } = useCandidateProfileMutations();
  const [form, setForm] = useState<UpdateCandidateProfilePayload>(() => profileToForm(profile));
  const [formErrors, setFormErrors] = useState<Record<string, string>>({});
  const [localPhotoPreview, setLocalPhotoPreview] = useState<string | null>(null);
  const photoInputRef = useRef<HTMLInputElement>(null);
  const displayName = user?.name ?? 'Aday';

  const fetchPhoto = useCallback(() => candidateProfileApi.downloadPhoto(), []);
  const storedPhotoUrl = useAuthenticatedBlob(fetchPhoto, Boolean(profile.profile_photo_path), profile.profile_photo_path);
  const displayPhotoUrl = localPhotoPreview ?? storedPhotoUrl;

  useEffect(() => {
    setForm(profileToForm(profile));
  }, [profile]);

  async function handleSave() {
    try {
      await updateProfile.mutateAsync(sanitizePayload(form));
      onUpdated?.('Genel bilgiler güncellendi.');
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
    <ProfileSectionCard title="Genel Bilgiler">
      <div className="mb-6 flex items-center gap-4">
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

      <div className="space-y-4">
        <div className="grid gap-4 sm:grid-cols-2">
          <label className="block space-y-2">
            <span className="text-sm font-medium text-ink-muted">Ad Soyad</span>
            <input
              value={displayName}
              readOnly
              className="h-11 w-full rounded-xl border border-surface bg-surface/40 px-3 text-sm text-ink-muted outline-none"
            />
          </label>
          <label className="block space-y-2">
            <span className="text-sm font-medium text-ink-muted">E-posta</span>
            <input
              value={user?.email ?? ''}
              readOnly
              className="h-11 w-full rounded-xl border border-surface bg-surface/40 px-3 text-sm text-ink-muted outline-none"
            />
          </label>
        </div>

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
                work_preference: e.target.value
                  ? (e.target.value as UpdateCandidateProfilePayload['work_preference'])
                  : null,
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
          label="Pozisyon Hedefi"
          name="desired_position"
          value={form.desired_position ?? ''}
          onChange={(e) => setForm((prev) => ({ ...prev, desired_position: e.target.value }))}
          error={formErrors.desired_position}
        />

        <Input
          label="Başlık"
          name="headline"
          value={form.headline ?? ''}
          onChange={(e) => setForm((prev) => ({ ...prev, headline: e.target.value }))}
          error={formErrors.headline}
        />

        <label className="block space-y-2">
          <span className="text-sm font-medium text-ink-muted">Kısa Özet</span>
          <textarea
            value={form.summary ?? ''}
            onChange={(e) => setForm((prev) => ({ ...prev, summary: e.target.value }))}
            rows={6}
            className="w-full rounded-xl border border-surface bg-white px-3 py-2 text-sm outline-none focus:border-primary focus:ring-4 focus:ring-primary/10"
          />
          {formErrors.summary ? <span className="text-sm text-danger">{formErrors.summary}</span> : null}
        </label>

        <div className="flex flex-wrap justify-end gap-2 pt-2">
          <Button type="button" onClick={() => void handleSave()} loading={updateProfile.isPending}>
            Kaydet
          </Button>
        </div>
      </div>
    </ProfileSectionCard>
  );
}

import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { authApi } from '@/api/auth';
import { getApiErrorMessage, getValidationErrors } from '@/api/client';
import { resolvePasswordUpdateError } from '@/utils/authValidationMessages';
import { resolveCompanySettingsView } from '@/utils/companyVerification';
import { COMPANY_POST_LOGOUT_PATH } from '@/utils/companyPortal';
import { Button } from '@/components/ui/Button';
import { Card, CardBody, CardHeader } from '@/components/ui/Card';
import { Input } from '@/components/ui/Input';
import { EmptyState, Skeleton } from '@/components/ui/States';
import { useAuth } from '@/hooks/useAuth';
import {
  useCompanyProfile,
  useRequestCompanyVerification,
  useUpdateCompanyProfile,
} from '@/hooks/useCompanyProfile';
import type { CompanyProfile } from '@/types/company';

const COMPANY_SIZE_OPTIONS = [
  { value: '', label: 'Belirtilmedi' },
  { value: '1-10', label: '1-10' },
  { value: '11-50', label: '11-50' },
  { value: '51-200', label: '51-200' },
  { value: '201-500', label: '201-500' },
  { value: '501-1000', label: '501-1000' },
  { value: '1000+', label: '1000+' },
] as const;

function profileToForm(profile: CompanyProfile) {
  return {
    name: profile.name ?? '',
    website: profile.website ?? '',
    industry: profile.industry ?? '',
    company_size: profile.company_size ?? '',
    description: profile.description ?? '',
    city: profile.city ?? '',
    country: profile.country ?? 'Türkiye',
    contact_email: profile.contact_email ?? '',
    contact_phone: profile.contact_phone ?? '',
  };
}

export function CompanySettingsPage() {
  const { user, logout } = useAuth();
  const navigate = useNavigate();
  const { data: profile, isLoading, isError, refetch } = useCompanyProfile();
  const updateProfile = useUpdateCompanyProfile();
  const requestVerification = useRequestCompanyVerification();

  const [form, setForm] = useState<ReturnType<typeof profileToForm> | null>(null);
  const [profileMessage, setProfileMessage] = useState<string | null>(null);
  const [profileError, setProfileError] = useState<string | null>(null);
  const [verificationError, setVerificationError] = useState<string | null>(null);

  const [currentPassword, setCurrentPassword] = useState('');
  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [passwordError, setPasswordError] = useState<string | null>(null);
  const [savingPassword, setSavingPassword] = useState(false);
  const [loggingOut, setLoggingOut] = useState(false);

  const visibleForm = form ?? (profile ? profileToForm(profile) : null);
  const view = resolveCompanySettingsView({
    isLoading,
    isError,
    profile: profile ?? null,
  });

  function updateField<K extends keyof NonNullable<typeof form>>(
    key: K,
    value: NonNullable<typeof form>[K],
  ) {
    setForm((current) => ({
      ...(current ?? profileToForm(profile as CompanyProfile)),
      [key]: value,
    }));
  }

  async function handleProfileSave(event: React.FormEvent) {
    event.preventDefault();
    if (!visibleForm) return;
    setProfileMessage(null);
    setProfileError(null);

    try {
      const updated = await updateProfile.mutateAsync({
        name: visibleForm.name.trim(),
        website: visibleForm.website.trim() || null,
        industry: visibleForm.industry.trim() || null,
        company_size: visibleForm.company_size || null,
        description: visibleForm.description.trim() || null,
        city: visibleForm.city.trim() || null,
        country: visibleForm.country.trim() || null,
        contact_email: visibleForm.contact_email.trim() || null,
        contact_phone: visibleForm.contact_phone.trim() || null,
      });
      setForm(profileToForm(updated));
      setProfileMessage('Şirket profili kaydedildi.');
    } catch (error) {
      const validation = getValidationErrors(error);
      setProfileError(
        Object.values(validation)[0]?.[0] ??
          getApiErrorMessage(error, 'Profil kaydedilemedi.'),
      );
    }
  }

  async function handleVerificationRequest() {
    setVerificationError(null);
    try {
      await requestVerification.mutateAsync();
    } catch (error) {
      const validation = getValidationErrors(error);
      setVerificationError(
        Object.values(validation)[0]?.[0] ??
          getApiErrorMessage(error, 'Doğrulama talebi gönderilemedi.'),
      );
    }
  }

  async function handlePasswordUpdate(event: React.FormEvent) {
    event.preventDefault();
    setSavingPassword(true);
    setPasswordError(null);

    try {
      await authApi.updatePassword({
        current_password: currentPassword,
        password,
        password_confirmation: passwordConfirmation,
      });
      await logout();
      navigate(COMPANY_POST_LOGOUT_PATH, {
        replace: true,
        state: { message: 'Şifren güncellendi. Yeni şifrenle tekrar giriş yap.' },
      });
    } catch (err) {
      const validation = getValidationErrors(err);
      setPasswordError(resolvePasswordUpdateError(validation, getApiErrorMessage(err)));
    } finally {
      setSavingPassword(false);
    }
  }

  async function handleLogout() {
    setLoggingOut(true);
    try {
      await logout();
      navigate(COMPANY_POST_LOGOUT_PATH, { replace: true });
    } finally {
      setLoggingOut(false);
    }
  }

  if (view.kind === 'loading') {
    return (
      <div className="space-y-4">
        <Skeleton className="h-10 w-64" />
        <Skeleton className="h-64" />
      </div>
    );
  }

  if (view.kind === 'error' || !profile || !visibleForm) {
    return (
      <EmptyState
        title="Profil yüklenemedi"
        description="Şirket bilgileri getirilemedi."
        action={
          <Button type="button" onClick={() => void refetch()}>
            Tekrar Dene
          </Button>
        }
      />
    );
  }

  return (
    <div className="space-y-6">
      <header className="space-y-1">
        <p className="text-sm font-medium text-primary">Şirket Ayarları</p>
        <h1 className="text-2xl font-bold tracking-tight text-ink sm:text-3xl">Ayarlar</h1>
        <p className="text-sm text-ink-muted">Şirket profilini, doğrulamayı ve hesabını yönet.</p>
      </header>

      <div className="grid gap-4 lg:grid-cols-2">
        <Card className="lg:col-span-2">
          <CardHeader>
            <h2 className="text-base font-semibold text-ink">Şirket Profili</h2>
            <p className="mt-1 text-sm text-ink-muted">Adayların göreceği şirket bilgileri</p>
          </CardHeader>
          <CardBody>
            <form className="grid gap-4 md:grid-cols-2" onSubmit={(event) => void handleProfileSave(event)}>
              <Input
                label="Şirket adı"
                value={visibleForm.name}
                onChange={(event) => updateField('name', event.target.value)}
                required
              />
              <Input
                label="Web sitesi"
                type="url"
                placeholder="https://"
                value={visibleForm.website}
                onChange={(event) => updateField('website', event.target.value)}
              />
              <Input
                label="Sektör"
                value={visibleForm.industry}
                onChange={(event) => updateField('industry', event.target.value)}
              />
              <label className="block space-y-2">
                <span className="text-sm font-medium text-ink">Şirket büyüklüğü</span>
                <select
                  className="h-11 w-full rounded-xl border border-surface bg-white px-3.5 text-sm text-ink outline-none focus:border-primary focus:ring-4 focus:ring-primary/10"
                  value={visibleForm.company_size}
                  onChange={(event) => updateField('company_size', event.target.value)}
                >
                  {COMPANY_SIZE_OPTIONS.map((option) => (
                    <option key={option.value || 'none'} value={option.value}>
                      {option.label}
                    </option>
                  ))}
                </select>
              </label>
              <Input
                label="Şehir"
                placeholder="İstanbul"
                value={visibleForm.city}
                onChange={(event) => updateField('city', event.target.value)}
              />
              <Input
                label="Ülke"
                value={visibleForm.country}
                onChange={(event) => updateField('country', event.target.value)}
              />
              <Input
                label="İletişim e-postası"
                type="email"
                value={visibleForm.contact_email}
                onChange={(event) => updateField('contact_email', event.target.value)}
              />
              <Input
                label="İletişim telefonu"
                value={visibleForm.contact_phone}
                onChange={(event) => updateField('contact_phone', event.target.value)}
              />
              <label className="block space-y-2 md:col-span-2">
                <span className="text-sm font-medium text-ink">Şirket açıklaması</span>
                <textarea
                  rows={4}
                  value={visibleForm.description}
                  onChange={(event) => updateField('description', event.target.value)}
                  className="w-full rounded-xl border border-surface bg-white px-3.5 py-3 text-sm text-ink outline-none focus:border-primary focus:ring-4 focus:ring-primary/10"
                />
              </label>
              {profileMessage ? (
                <p className="text-sm text-secondary md:col-span-2">{profileMessage}</p>
              ) : null}
              {profileError ? (
                <p className="text-sm text-danger md:col-span-2">{profileError}</p>
              ) : null}
              <div className="md:col-span-2">
                <Button type="submit" size="sm" loading={updateProfile.isPending}>
                  Profili Kaydet
                </Button>
              </div>
            </form>
          </CardBody>
        </Card>

        <Card>
          <CardHeader>
            <h2 className="text-base font-semibold text-ink">{view.title}</h2>
            <p className="mt-1 text-sm text-ink-muted">Şirket doğrulama durumu</p>
          </CardHeader>
          <CardBody className="space-y-3">
            <p className="text-sm text-ink">{view.body}</p>
            {view.showVerifiedBadge ? (
              <p className="text-sm font-medium text-primary">Doğrulanmış şirket</p>
            ) : null}
            {view.canRequest ? (
              <Button
                type="button"
                size="sm"
                variant="secondary"
                loading={requestVerification.isPending}
                onClick={() => void handleVerificationRequest()}
              >
                {view.ctaLabel ?? 'Doğrulama Talep Et'}
              </Button>
            ) : null}
            {verificationError ? <p className="text-sm text-danger">{verificationError}</p> : null}
          </CardBody>
        </Card>

        <Card>
          <CardHeader>
            <h2 className="text-base font-semibold text-ink">Hesap</h2>
            <p className="mt-1 text-sm text-ink-muted">Şifre ve oturum yönetimi</p>
          </CardHeader>
          <CardBody className="space-y-4 text-sm">
            <div className="flex justify-between gap-4">
              <span className="text-ink-muted">E-posta</span>
              <span className="font-medium text-ink">{user?.email ?? '—'}</span>
            </div>
            <form className="space-y-3" onSubmit={(event) => void handlePasswordUpdate(event)}>
              <Input
                type="password"
                label="Mevcut şifre"
                value={currentPassword}
                onChange={(event) => setCurrentPassword(event.target.value)}
                required
              />
              <Input
                type="password"
                label="Yeni şifre"
                value={password}
                onChange={(event) => setPassword(event.target.value)}
                required
              />
              <Input
                type="password"
                label="Yeni şifre tekrar"
                value={passwordConfirmation}
                onChange={(event) => setPasswordConfirmation(event.target.value)}
                required
              />
              {passwordError ? <p className="text-sm text-danger">{passwordError}</p> : null}
              <Button type="submit" size="sm" loading={savingPassword}>
                Şifreyi Güncelle
              </Button>
            </form>
            <Button
              type="button"
              variant="outline"
              size="sm"
              loading={loggingOut}
              onClick={() => void handleLogout()}
            >
              Çıkış yap
            </Button>
          </CardBody>
        </Card>
      </div>
    </div>
  );
}

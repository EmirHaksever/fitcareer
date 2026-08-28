import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { authApi } from '@/api/auth';
import { getApiErrorMessage, getValidationErrors } from '@/api/client';
import { resolvePasswordUpdateError } from '@/utils/authValidationMessages';
import { Button } from '@/components/ui/Button';
import { Card, CardBody, CardHeader } from '@/components/ui/Card';
import { Input } from '@/components/ui/Input';
import { useAuth } from '@/hooks/useAuth';

export function SettingsPage() {
  const { user, logout } = useAuth();
  const navigate = useNavigate();
  const [currentPassword, setCurrentPassword] = useState('');
  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [feedback, setFeedback] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);
  const [loggingOut, setLoggingOut] = useState(false);

  async function handlePasswordUpdate(event: React.FormEvent) {
    event.preventDefault();
    setSaving(true);
    setFeedback(null);
    setError(null);

    try {
      const message = await authApi.updatePassword({
        current_password: currentPassword,
        password,
        password_confirmation: passwordConfirmation,
      });
      setFeedback(message);
      setCurrentPassword('');
      setPassword('');
      setPasswordConfirmation('');
      await logout();
      navigate('/login', {
        replace: true,
        state: { message: 'Şifren güncellendi. Yeni şifrenle tekrar giriş yap.' },
      });
    } catch (err) {
      const validation = getValidationErrors(err);
      setError(resolvePasswordUpdateError(validation, getApiErrorMessage(err)));
    } finally {
      setSaving(false);
    }
  }

  async function handleLogout() {
    setLoggingOut(true);
    try {
      await logout();
      navigate('/login', { replace: true });
    } finally {
      setLoggingOut(false);
    }
  }

  return (
    <div className="space-y-6">
      <header className="space-y-1">
        <h1 className="text-2xl font-bold tracking-tight text-ink sm:text-3xl">Ayarlar</h1>
        <p className="text-sm text-ink-muted">Hesap ve tercihlerini yönet.</p>
      </header>

      <div className="grid gap-4 lg:grid-cols-2">
        <Card>
          <CardHeader>
            <h2 className="text-base font-semibold text-ink">Hesap Bilgileri</h2>
          </CardHeader>
          <CardBody className="space-y-3 text-sm">
            <div className="flex justify-between gap-4">
              <span className="text-ink-muted">Ad Soyad</span>
              <span className="font-medium text-ink">{user?.name ?? '—'}</span>
            </div>
            <div className="flex justify-between gap-4">
              <span className="text-ink-muted">E-posta</span>
              <span className="font-medium text-ink">{user?.email ?? '—'}</span>
            </div>
            <Link to="/profile">
              <Button variant="outline" size="sm" className="mt-2">
                CV Profilini Düzenle
              </Button>
            </Link>
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

        <Card>
          <CardHeader>
            <h2 className="text-base font-semibold text-ink">Şifre Değiştir</h2>
          </CardHeader>
          <CardBody>
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
              {feedback ? <p className="text-sm text-secondary">{feedback}</p> : null}
              {error ? <p className="text-sm text-danger">{error}</p> : null}
              <Button type="submit" size="sm" loading={saving}>
                Şifreyi Güncelle
              </Button>
            </form>
          </CardBody>
        </Card>

        <Card>
          <CardHeader>
            <h2 className="text-base font-semibold text-ink">Dil ve Bölge</h2>
          </CardHeader>
          <CardBody className="space-y-2 text-sm">
            <div className="flex justify-between gap-4">
              <span className="text-ink-muted">Arayüz dili</span>
              <span className="font-medium text-ink">Türkçe</span>
            </div>
            <div className="flex justify-between gap-4">
              <span className="text-ink-muted">Bölge ayarı</span>
              <span className="font-medium text-ink">{user?.locale ?? 'tr'}</span>
            </div>
          </CardBody>
        </Card>

        <Card>
          <CardHeader>
            <h2 className="text-base font-semibold text-ink">Bildirim Tercihleri</h2>
          </CardHeader>
          <CardBody className="space-y-3 text-sm text-ink-muted">
            <p>Bildirim tercihlerin şu an bu ekrandan yönetilemiyor. Yeni özellikler eklendiğinde burada görünecek.</p>
            <Link to="/notifications">
              <Button variant="outline" size="sm">
                Bildirimlere Git
              </Button>
            </Link>
          </CardBody>
        </Card>
      </div>
    </div>
  );
}

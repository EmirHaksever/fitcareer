import { useState, type FormEvent } from 'react';
import { Lock, Mail } from 'lucide-react';
import { Link, useSearchParams } from 'react-router-dom';
import { authApi } from '@/api/auth';
import { getApiErrorMessage, getValidationErrors } from '@/api/client';
import { AuthFormCard } from '@/components/auth/AuthFormCard';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { PasswordInput } from '@/components/ui/PasswordInput';

export function ResetPasswordPage() {
  const [searchParams] = useSearchParams();
  const [email, setEmail] = useState(searchParams.get('email') ?? '');
  const [token, setToken] = useState(searchParams.get('token') ?? '');
  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [message, setMessage] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
  const [loading, setLoading] = useState(false);

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setLoading(true);
    setError(null);
    setMessage(null);
    setFieldErrors({});

    try {
      const responseMessage = await authApi.resetPassword({
        email,
        token,
        password,
        password_confirmation: passwordConfirmation,
      });
      setMessage(responseMessage);
    } catch (submitError) {
      const validation = getValidationErrors(submitError);
      setFieldErrors({
        email: validation.email?.[0] ?? '',
        token: validation.token?.[0] ?? '',
        password: validation.password?.[0] ?? '',
      });
      setError(getApiErrorMessage(submitError));
    } finally {
      setLoading(false);
    }
  }

  return (
    <AuthFormCard>
      <div className="space-y-8">
        <div className="space-y-2">
          <h2 className="text-2xl font-bold tracking-tight text-[#0F172A]">Şifreyi Sıfırla</h2>
          <p className="text-sm text-[#64748B]">Yeni şifreni belirle.</p>
        </div>

        <form className="space-y-5" onSubmit={handleSubmit} noValidate>
          <Input
            label="E-posta"
            name="email"
            type="email"
            autoComplete="email"
            placeholder="ornek@mail.com"
            icon={<Mail className="h-4 w-4" aria-hidden="true" />}
            value={email}
            onChange={(event) => setEmail(event.target.value)}
            error={fieldErrors.email}
            required
          />
          <Input
            label="Token"
            name="token"
            value={token}
            onChange={(event) => setToken(event.target.value)}
            error={fieldErrors.token}
            required
          />
          <PasswordInput
            label="Yeni Şifre"
            name="password"
            autoComplete="new-password"
            placeholder="••••••••"
            icon={<Lock className="h-4 w-4" aria-hidden="true" />}
            value={password}
            onChange={(event) => setPassword(event.target.value)}
            error={fieldErrors.password}
            required
          />
          <PasswordInput
            label="Yeni Şifre Tekrar"
            name="password_confirmation"
            autoComplete="new-password"
            placeholder="••••••••"
            icon={<Lock className="h-4 w-4" aria-hidden="true" />}
            value={passwordConfirmation}
            onChange={(event) => setPasswordConfirmation(event.target.value)}
            required
          />
          {message ? (
            <div className="rounded-xl border border-primary/20 bg-[#D1FAE5]/40 px-4 py-3 text-sm text-primary">
              {message}
            </div>
          ) : null}
          {error ? (
            <div className="rounded-xl border border-red-100 bg-red-50 px-4 py-3 text-sm text-danger">
              {error}
            </div>
          ) : null}
          <Button type="submit" className="h-12 w-full" loading={loading}>
            Şifreyi Güncelle
          </Button>
        </form>

        <Link
          to="/login"
          className="block text-center text-sm font-semibold text-primary hover:text-primary-700 hover:underline"
        >
          Giriş sayfasına dön
        </Link>
      </div>
    </AuthFormCard>
  );
}

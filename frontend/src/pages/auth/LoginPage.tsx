import { useState, type FormEvent } from 'react';
import { ArrowRight, Lock, Mail, ShieldCheck } from 'lucide-react';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import { AuthDivider, AuthFormCard } from '@/components/auth/AuthFormCard';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { PasswordInput } from '@/components/ui/PasswordInput';
import { getApiErrorMessage, getValidationErrors } from '@/api/client';
import {
  mapAuthFieldErrors,
  resolveNetworkErrorMessage,
} from '@/utils/authValidationMessages';
import { useAuth } from '@/hooks/useAuth';
import { getDefaultRouteForRole } from '@/utils/routing';

export function LoginPage() {
  const navigate = useNavigate();
  const location = useLocation();
  const { login } = useAuth();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [formError, setFormError] = useState<string | null>(null);
  const [successMessage] = useState<string | null>(
    () => (location.state as { message?: string } | null)?.message ?? null,
  );
  const [loading, setLoading] = useState(false);

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setLoading(true);
    setFormError(null);
    setErrors({});

    try {
      const user = await login({ email, password });
      navigate(getDefaultRouteForRole(user.role));
    } catch (error) {
      const validation = getValidationErrors(error);
      const mapped = mapAuthFieldErrors(validation, ['email', 'password'], 'login');
      setErrors(mapped);
      const hasFieldErrors = Object.values(mapped).some(Boolean);
      setFormError(
        hasFieldErrors
          ? null
          : resolveNetworkErrorMessage(error) ||
              getApiErrorMessage(error, 'Giriş başarısız. Lütfen tekrar deneyin.'),
      );
    } finally {
      setLoading(false);
    }
  }

  return (
    <AuthFormCard
      footer={
        <p className="mt-5 flex items-center justify-center gap-2 text-xs text-[#64748B]">
          <ShieldCheck className="h-4 w-4 text-primary" aria-hidden="true" />
          Güvenli bağlantı ile korunur
        </p>
      }
    >
      <div className="space-y-7">
        <div className="space-y-1.5">
          <h2 className="text-[1.65rem] font-bold tracking-tight text-[#0F172A]">Giriş Yap</h2>
          <p className="text-sm text-[#64748B]">FitCareer hesabına giriş yap.</p>
        </div>

        <form className="space-y-4" onSubmit={handleSubmit} noValidate>
          {successMessage ? (
            <p className="rounded-lg border border-secondary/30 bg-secondary/10 px-3 py-2 text-sm text-primary-800">
              {successMessage}
            </p>
          ) : null}
          <Input
            label="E-posta"
            name="email"
            type="email"
            autoComplete="email"
            placeholder="ornek@mail.com"
            icon={<Mail className="h-4 w-4" aria-hidden="true" />}
            value={email}
            onChange={(event) => setEmail(event.target.value)}
            error={errors.email}
            required
          />

          <div className="space-y-2">
            <PasswordInput
              label="Şifre"
              name="password"
              autoComplete="current-password"
              placeholder="Şifrenizi girin"
              icon={<Lock className="h-4 w-4" aria-hidden="true" />}
              value={password}
              onChange={(event) => setPassword(event.target.value)}
              error={errors.password}
              required
            />
            <Link
              to="/forgot-password"
              className="inline-block text-sm font-medium text-primary hover:text-primary-700 hover:underline"
            >
              Şifremi unuttum?
            </Link>
          </div>

          {formError ? (
            <div className="rounded-xl border border-red-100 bg-red-50 px-4 py-3 text-sm text-danger">
              {formError}
            </div>
          ) : null}

          <Button
            type="submit"
            className="h-12 w-full px-5"
            loading={loading}
            loadingLabel="Giriş Yapılıyor..."
          >
            <span className="flex-1 text-left">Giriş Yap</span>
            <ArrowRight className="h-4 w-4" aria-hidden="true" />
          </Button>
        </form>

        <AuthDivider />

        <p className="text-center text-sm text-[#64748B]">
          Hesabın yok mu?{' '}
          <Link to="/register" className="font-semibold text-primary hover:text-primary-700 hover:underline">
            Kayıt ol
          </Link>
        </p>
      </div>
    </AuthFormCard>
  );
}

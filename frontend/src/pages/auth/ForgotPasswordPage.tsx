import { useState, type FormEvent } from 'react';
import { Mail } from 'lucide-react';
import { Link } from 'react-router-dom';
import { authApi } from '@/api/auth';
import { getApiErrorMessage, getValidationErrors } from '@/api/client';
import { AuthFormCard } from '@/components/auth/AuthFormCard';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';

export function ForgotPasswordPage() {
  const [email, setEmail] = useState('');
  const [message, setMessage] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [fieldError, setFieldError] = useState<string | undefined>();
  const [loading, setLoading] = useState(false);

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setLoading(true);
    setError(null);
    setMessage(null);
    setFieldError(undefined);

    try {
      const responseMessage = await authApi.forgotPassword({ email });
      setMessage(responseMessage);
    } catch (submitError) {
      const validation = getValidationErrors(submitError);
      setFieldError(validation.email?.[0]);
      setError(getApiErrorMessage(submitError));
    } finally {
      setLoading(false);
    }
  }

  return (
    <AuthFormCard>
      <div className="space-y-8">
        <div className="space-y-2">
          <h2 className="text-2xl font-bold tracking-tight text-[#0F172A]">Şifremi Unuttum</h2>
          <p className="text-sm text-[#64748B]">E-posta adresine sıfırlama bağlantısı gönderilir.</p>
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
            error={fieldError}
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
            Sıfırlama Bağlantısı Gönder
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

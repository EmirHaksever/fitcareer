import { useState, type FormEvent } from 'react';
import { ArrowRight, Building2, Lock, Mail, User } from 'lucide-react';
import { Link, useNavigate } from 'react-router-dom';
import { AuthFormCard } from '@/components/auth/AuthFormCard';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { PasswordInput } from '@/components/ui/PasswordInput';
import { SegmentedControl } from '@/components/ui/SegmentedControl';
import { getApiErrorMessage, getValidationErrors } from '@/api/client';
import { useAuth } from '@/hooks/useAuth';
import { getDefaultRouteForRole } from '@/utils/routing';

export function RegisterPage() {
  const navigate = useNavigate();
  const { register } = useAuth();
  const [role, setRole] = useState<'candidate' | 'company'>('candidate');
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [companyName, setCompanyName] = useState('');
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [formError, setFormError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setLoading(true);
    setFormError(null);
    setErrors({});

    const resolvedName = role === 'company' ? companyName : name;

    try {
      const user = await register({
        name: resolvedName,
        email,
        password,
        password_confirmation: passwordConfirmation,
        role,
        company_name: role === 'company' ? companyName : undefined,
      });
      navigate(getDefaultRouteForRole(user.role));
    } catch (error) {
      const validation = getValidationErrors(error);
      setErrors({
        name: validation.name?.[0] ?? '',
        email: validation.email?.[0] ?? '',
        password: validation.password?.[0] ?? '',
        company_name: validation.company_name?.[0] ?? '',
        role: validation.role?.[0] ?? '',
      });
      setFormError(getApiErrorMessage(error, 'Kayıt başarısız.'));
    } finally {
      setLoading(false);
    }
  }

  return (
    <AuthFormCard>
      <div className="space-y-7">
        <div className="space-y-1.5">
          <h2 className="text-[1.65rem] font-bold tracking-tight text-[#0F172A]">Kayıt Ol</h2>
          <p className="text-sm text-[#64748B]">Aday veya şirket hesabı oluştur.</p>
        </div>

        <form className="space-y-4" onSubmit={handleSubmit} noValidate>
          <SegmentedControl
            ariaLabel="Hesap türü"
            value={role}
            options={[
              {
                value: 'candidate',
                label: 'Aday',
                icon: <User className="h-4 w-4" aria-hidden="true" />,
              },
              {
                value: 'company',
                label: 'Şirket',
                icon: <Building2 className="h-4 w-4" aria-hidden="true" />,
              },
            ]}
            onChange={setRole}
          />

          {role === 'candidate' ? (
            <Input
              label="Ad Soyad"
              name="name"
              autoComplete="name"
              placeholder="Adınızı ve soyadınızı girin"
              icon={<User className="h-4 w-4" aria-hidden="true" />}
              value={name}
              onChange={(event) => setName(event.target.value)}
              error={errors.name}
              required
            />
          ) : (
            <Input
              label="Şirket Adı"
              name="company_name"
              autoComplete="organization"
              placeholder="Şirket adınızı girin"
              icon={<Building2 className="h-4 w-4" aria-hidden="true" />}
              value={companyName}
              onChange={(event) => setCompanyName(event.target.value)}
              error={errors.company_name || errors.name}
              required
            />
          )}

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

          <div className="grid gap-4 sm:grid-cols-2">
            <PasswordInput
              label="Şifre"
              name="password"
              autoComplete="new-password"
              placeholder="Şifrenizi girin"
              icon={<Lock className="h-4 w-4" aria-hidden="true" />}
              value={password}
              onChange={(event) => setPassword(event.target.value)}
              error={errors.password}
              required
            />

            <PasswordInput
              label="Şifre Tekrar"
              name="password_confirmation"
              autoComplete="new-password"
              placeholder="Şifrenizi girin"
              icon={<Lock className="h-4 w-4" aria-hidden="true" />}
              value={passwordConfirmation}
              onChange={(event) => setPasswordConfirmation(event.target.value)}
              required
            />
          </div>

          <label className="flex items-start gap-3 text-xs leading-5 text-[#64748B]">
            <input
              type="checkbox"
              className="mt-0.5 h-4 w-4 rounded border-[#CBD5E1] text-primary focus:ring-primary/20"
              defaultChecked
              readOnly
            />
            <span>
              Kayıt olarak{' '}
              <button type="button" className="font-medium text-primary hover:underline">
                kullanım koşullarını
              </button>{' '}
              ve{' '}
              <button type="button" className="font-medium text-primary hover:underline">
                gizlilik politikasını
              </button>{' '}
              kabul etmiş olursunuz.
            </span>
          </label>

          {formError ? (
            <div className="rounded-xl border border-red-100 bg-red-50 px-4 py-3 text-sm text-danger">
              {formError}
            </div>
          ) : null}

          <Button
            type="submit"
            className="h-12 w-full px-5"
            loading={loading}
            loadingLabel="Hesap oluşturuluyor..."
          >
            <span className="flex-1 text-left">Kayıt Ol</span>
            <ArrowRight className="h-4 w-4" aria-hidden="true" />
          </Button>
        </form>

        <p className="text-center text-sm text-[#64748B]">
          Zaten hesabın var mı?{' '}
          <Link to="/login" className="font-semibold text-primary hover:text-primary-700 hover:underline">
            Giriş yap
          </Link>
        </p>
      </div>
    </AuthFormCard>
  );
}

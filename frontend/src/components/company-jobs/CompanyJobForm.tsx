import { useState } from 'react';
import { Input } from '@/components/ui/Input';
import { Button } from '@/components/ui/Button';
import { Card, CardBody, CardHeader } from '@/components/ui/Card';
import { JOB_CATEGORY_OPTIONS } from '@/components/company-jobs/jobFormOptions';
import { selectClassName } from '@/components/profile/profileFormOptions';
import {
  EMPLOYMENT_TYPE_OPTIONS,
  EXPERIENCE_LEVEL_OPTIONS,
  WORK_TYPE_OPTIONS,
} from '@/components/jobs/jobFilterOptions';
import type { CreateCompanyJobPayload } from '@/types/companyJob';
import { cn } from '@/utils/format';
import { COMPANY_JOB_FORM_DEFAULTS } from '@/utils/companyJobValidation';

export type CompanyJobFormValues = CreateCompanyJobPayload;

interface CompanyJobFormProps {
  initialValues?: Partial<CompanyJobFormValues>;
  submitLabel: string;
  secondaryLabel?: string;
  isSubmitting?: boolean;
  isSecondarySubmitting?: boolean;
  onSubmit: (values: CompanyJobFormValues) => void | Promise<void>;
  onSecondarySubmit?: (values: CompanyJobFormValues) => void | Promise<void>;
  errors?: Record<string, string>;
}

const defaultValues: CompanyJobFormValues = {
  ...COMPANY_JOB_FORM_DEFAULTS,
};

function FieldError({ message }: { message?: string }) {
  if (!message) return null;
  return <span className="text-sm text-danger">{message}</span>;
}

function TextAreaField({
  label,
  name,
  value,
  onChange,
  error,
  rows = 5,
  placeholder,
}: {
  label: string;
  name: string;
  value: string;
  onChange: (value: string) => void;
  error?: string;
  rows?: number;
  placeholder?: string;
}) {
  return (
    <label className="block space-y-2" htmlFor={name}>
      <span className="text-sm font-medium text-ink">{label}</span>
      <textarea
        id={name}
        name={name}
        rows={rows}
        value={value}
        placeholder={placeholder}
        onChange={(event) => onChange(event.target.value)}
        className={cn(
          'w-full rounded-xl border border-surface bg-white px-3.5 py-3 text-sm text-ink outline-none transition placeholder:text-ink-subtle focus:border-primary focus:ring-4 focus:ring-primary/10',
          error && 'border-danger focus:border-danger focus:ring-danger/10',
        )}
      />
      <FieldError message={error} />
    </label>
  );
}

function SelectField({
  label,
  name,
  value,
  onChange,
  options,
  error,
}: {
  label: string;
  name: string;
  value: string;
  onChange: (value: string) => void;
  options: ReadonlyArray<{ value: string; label: string }>;
  error?: string;
}) {
  return (
    <label className="block space-y-2" htmlFor={name}>
      <span className="text-sm font-medium text-ink">{label}</span>
      <select
        id={name}
        name={name}
        value={value}
        onChange={(event) => onChange(event.target.value)}
        className={cn(selectClassName, error && 'border-danger')}
      >
        {options.map((option) => (
          <option key={option.value} value={option.value}>
            {option.label}
          </option>
        ))}
      </select>
      <FieldError message={error} />
    </label>
  );
}

export function CompanyJobForm({
  initialValues,
  submitLabel,
  secondaryLabel,
  isSubmitting = false,
  isSecondarySubmitting = false,
  onSubmit,
  onSecondarySubmit,
  errors = {},
}: CompanyJobFormProps) {
  const [form, setForm] = useState<CompanyJobFormValues>({
    ...defaultValues,
    ...initialValues,
    requirements: initialValues?.requirements ?? '',
    responsibilities: initialValues?.responsibilities ?? '',
    city: initialValues?.city ?? '',
    country: initialValues?.country ?? 'Türkiye',
    contact_email: initialValues?.contact_email ?? '',
    contact_phone: initialValues?.contact_phone ?? '',
  });

  function updateField<K extends keyof CompanyJobFormValues>(key: K, value: CompanyJobFormValues[K]) {
    setForm((current) => ({ ...current, [key]: value }));
  }

  function buildPayload(): CompanyJobFormValues {
    return {
      ...form,
      requirements: form.requirements?.trim() || null,
      responsibilities: form.responsibilities?.trim() || null,
      city: form.city?.trim() || null,
      country: form.country?.trim() || null,
      experience_level: form.experience_level?.trim() || null,
      contact_email: form.contact_email?.trim() || null,
      contact_phone: form.contact_phone?.trim() || null,
      application_deadline: form.application_deadline || null,
      salary_min: form.salary_min ? Number(form.salary_min) : null,
      salary_max: form.salary_max ? Number(form.salary_max) : null,
    };
  }

  return (
    <form
      className="space-y-6"
      onSubmit={(event) => {
        event.preventDefault();
        void onSubmit(buildPayload());
      }}
    >
      <Card>
        <CardHeader>
          <h2 className="text-lg font-semibold text-ink">Temel Bilgiler</h2>
          <p className="text-sm text-ink-muted">İlan başlığı ve pozisyon detaylarını gir.</p>
        </CardHeader>
        <CardBody className="space-y-4">
          <Input
            label="İlan Başlığı"
            name="title"
            value={form.title}
            onChange={(event) => updateField('title', event.target.value)}
            placeholder="Örn. Junior Backend Developer"
            error={errors.title}
            required
          />

          <TextAreaField
            label="İş Tanımı"
            name="description"
            value={form.description}
            onChange={(value) => updateField('description', value)}
            placeholder="Pozisyonun kapsamını ve beklentileri açıkla..."
            error={errors.description}
            rows={6}
          />

          <div className="grid gap-4 md:grid-cols-3">
            <SelectField
              label="Kategori"
              name="category"
              value={form.category ?? 'engineering'}
              onChange={(value) => updateField('category', value)}
              options={JOB_CATEGORY_OPTIONS}
              error={errors.category}
            />
            <SelectField
              label="Çalışma Tipi"
              name="work_type"
              value={form.work_type}
              onChange={(value) => updateField('work_type', value)}
              options={[{ value: '', label: 'Seçiniz' }, ...WORK_TYPE_OPTIONS]}
              error={errors.work_type}
            />
            <SelectField
              label="İstihdam Tipi"
              name="employment_type"
              value={form.employment_type}
              onChange={(value) => updateField('employment_type', value)}
              options={EMPLOYMENT_TYPE_OPTIONS}
              error={errors.employment_type}
            />
          </div>

          <SelectField
            label="Deneyim Seviyesi"
            name="experience_level"
            value={form.experience_level ?? ''}
            onChange={(value) => updateField('experience_level', value || null)}
            options={[{ value: '', label: 'Belirtilmedi' }, ...EXPERIENCE_LEVEL_OPTIONS]}
            error={errors.experience_level}
          />
        </CardBody>
      </Card>

      <Card>
        <CardHeader>
          <h2 className="text-lg font-semibold text-ink">Gereksinimler & Sorumluluklar</h2>
        </CardHeader>
        <CardBody className="space-y-4">
          <TextAreaField
            label="Gereksinimler"
            name="requirements"
            value={form.requirements ?? ''}
            onChange={(value) => updateField('requirements', value)}
            placeholder="Teknik beceriler, eğitim, deneyim..."
            error={errors.requirements}
          />
          <TextAreaField
            label="Sorumluluklar"
            name="responsibilities"
            value={form.responsibilities ?? ''}
            onChange={(value) => updateField('responsibilities', value)}
            placeholder="Günlük görevler ve sorumluluk alanları..."
            error={errors.responsibilities}
          />
        </CardBody>
      </Card>

      <Card>
        <CardHeader>
          <h2 className="text-lg font-semibold text-ink">Konum & Maaş</h2>
        </CardHeader>
        <CardBody className="space-y-4">
          <div className="grid gap-4 md:grid-cols-2">
            <Input
              label="Şehir"
              name="city"
              value={form.city ?? ''}
              onChange={(event) => updateField('city', event.target.value)}
              placeholder="İstanbul"
              error={errors.city}
            />
            <Input
              label="Ülke"
              name="country"
              value={form.country ?? ''}
              onChange={(event) => updateField('country', event.target.value)}
              placeholder="Türkiye"
              error={errors.country}
            />
          </div>

          <div className="grid gap-4 md:grid-cols-3">
            <Input
              label="Min. Maaş"
              name="salary_min"
              type="number"
              min={0}
              value={form.salary_min ?? ''}
              onChange={(event) =>
                updateField('salary_min', event.target.value ? Number(event.target.value) : null)
              }
              error={errors.salary_min}
            />
            <Input
              label="Max. Maaş"
              name="salary_max"
              type="number"
              min={0}
              value={form.salary_max ?? ''}
              onChange={(event) =>
                updateField('salary_max', event.target.value ? Number(event.target.value) : null)
              }
              error={errors.salary_max}
            />
            <Input
              label="Para Birimi"
              name="salary_currency"
              value={form.salary_currency ?? 'TRY'}
              onChange={(event) => updateField('salary_currency', event.target.value.toUpperCase())}
              maxLength={3}
              error={errors.salary_currency}
            />
          </div>

          <label className="flex items-center gap-2 text-sm text-ink">
            <input
              type="checkbox"
              checked={Boolean(form.is_salary_visible)}
              onChange={(event) => updateField('is_salary_visible', event.target.checked)}
              className="h-4 w-4 rounded border-surface text-primary focus:ring-primary/20"
            />
            Maaş bilgisini ilanda göster
          </label>
        </CardBody>
      </Card>

      <Card>
        <CardHeader>
          <h2 className="text-lg font-semibold text-ink">İletişim & Son Tarih</h2>
        </CardHeader>
        <CardBody className="space-y-4">
          <div className="grid gap-4 md:grid-cols-2">
            <Input
              label="İletişim E-postası"
              name="contact_email"
              type="email"
              value={form.contact_email ?? ''}
              onChange={(event) => updateField('contact_email', event.target.value)}
              error={errors.contact_email}
            />
            <Input
              label="İletişim Telefonu"
              name="contact_phone"
              value={form.contact_phone ?? ''}
              onChange={(event) => updateField('contact_phone', event.target.value)}
              error={errors.contact_phone}
            />
          </div>
          <Input
            label="Son Başvuru Tarihi"
            name="application_deadline"
            type="date"
            value={form.application_deadline ?? ''}
            onChange={(event) => updateField('application_deadline', event.target.value || null)}
            error={errors.application_deadline}
          />
        </CardBody>
      </Card>

      <div className="flex flex-wrap justify-end gap-3">
        {secondaryLabel && onSecondarySubmit ? (
          <Button
            type="button"
            variant="secondary"
            disabled={isSubmitting || isSecondarySubmitting}
            onClick={() => void onSecondarySubmit(buildPayload())}
          >
            {isSecondarySubmitting ? 'Yayınlanıyor...' : secondaryLabel}
          </Button>
        ) : null}
        <Button type="submit" disabled={isSubmitting || isSecondarySubmitting}>
          {isSubmitting ? 'Kaydediliyor...' : submitLabel}
        </Button>
      </div>
    </form>
  );
}

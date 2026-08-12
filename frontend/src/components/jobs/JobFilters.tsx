import { SlidersHorizontal, X } from 'lucide-react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Card, CardBody, CardHeader } from '@/components/ui/Card';
import {
  EMPLOYMENT_TYPE_OPTIONS,
  EXPERIENCE_LEVEL_OPTIONS,
  WORK_TYPE_OPTIONS,
} from '@/components/jobs/jobFilterOptions';
import type { JobSearchParams } from '@/types/api';
import { cn } from '@/utils/format';

interface JobFiltersProps {
  values: JobSearchParams;
  onChange: (patch: Partial<JobSearchParams>) => void;
  onReset: () => void;
  className?: string;
  showHeader?: boolean;
}

const selectClassName =
  'h-11 w-full rounded-xl border border-surface bg-white px-3 text-sm text-ink outline-none transition focus:border-primary focus:ring-4 focus:ring-primary/10';

function FilterField({
  label,
  children,
}: {
  label: string;
  children: ReactNode;
}) {
  return (
    <label className="block space-y-2">
      <span className="text-sm font-medium text-ink-muted">{label}</span>
      {children}
    </label>
  );
}

function FiltersForm({ values, onChange, onReset }: Omit<JobFiltersProps, 'className' | 'showHeader'>) {
  return (
    <div className="space-y-4">
      <Input
        label="Konum"
        name="location"
        placeholder="Şehir veya ülke"
        value={values.location ?? ''}
        onChange={(event) => onChange({ location: event.target.value || undefined, page: 1 })}
      />

      <Input
        label="Kategori"
        name="category"
        placeholder="Örn. Yazılım"
        value={values.category ?? ''}
        onChange={(event) => onChange({ category: event.target.value || undefined, page: 1 })}
      />

      <FilterField label="Çalışma şekli">
        <select
          value={values.work_type ?? ''}
          onChange={(event) => onChange({ work_type: event.target.value || undefined, page: 1 })}
          className={selectClassName}
        >
          <option value="">Tümü</option>
          {WORK_TYPE_OPTIONS.map((option) => (
            <option key={option.value} value={option.value}>
              {option.label}
            </option>
          ))}
        </select>
      </FilterField>

      <FilterField label="İstihdam türü">
        <select
          value={values.employment_type ?? ''}
          onChange={(event) => onChange({ employment_type: event.target.value || undefined, page: 1 })}
          className={selectClassName}
        >
          <option value="">Tümü</option>
          {EMPLOYMENT_TYPE_OPTIONS.map((option) => (
            <option key={option.value} value={option.value}>
              {option.label}
            </option>
          ))}
        </select>
      </FilterField>

      <FilterField label="Deneyim seviyesi">
        <select
          value={values.experience_level ?? ''}
          onChange={(event) => onChange({ experience_level: event.target.value || undefined, page: 1 })}
          className={selectClassName}
        >
          <option value="">Tümü</option>
          {EXPERIENCE_LEVEL_OPTIONS.map((option) => (
            <option key={option.value} value={option.value}>
              {option.label}
            </option>
          ))}
        </select>
      </FilterField>

      <Input
        label="Minimum Trust Score"
        name="min_trust_score"
        type="number"
        min={0}
        max={100}
        placeholder="0-100"
        value={values.min_trust_score ?? ''}
        onChange={(event) =>
          onChange({
            min_trust_score: event.target.value ? Number(event.target.value) : undefined,
            page: 1,
          })
        }
      />

      <Input
        label="Minimum Fit Score"
        name="min_fit_score"
        type="number"
        min={0}
        max={100}
        placeholder="0-100"
        value={values.min_fit_score ?? ''}
        onChange={(event) =>
          onChange({
            min_fit_score: event.target.value ? Number(event.target.value) : undefined,
            page: 1,
          })
        }
      />

      <Button type="button" variant="outline" className="w-full" onClick={onReset}>
        Filtreleri Temizle
      </Button>
    </div>
  );
}

export function JobFilters({
  values,
  onChange,
  onReset,
  className,
  showHeader = true,
}: JobFiltersProps) {
  return (
    <Card className={cn('overflow-hidden', className)}>
      {showHeader ? (
        <CardHeader className="flex items-center justify-between">
          <div className="flex items-center gap-2">
            <SlidersHorizontal className="h-4 w-4 text-primary" aria-hidden="true" />
            <h2 className="text-sm font-semibold text-ink">Filtreler</h2>
          </div>
        </CardHeader>
      ) : null}
      <CardBody>
        <FiltersForm values={values} onChange={onChange} onReset={onReset} />
      </CardBody>
    </Card>
  );
}

interface JobFiltersDrawerProps extends JobFiltersProps {
  open: boolean;
  onClose: () => void;
  activeCount: number;
}

export function JobFiltersDrawer({
  open,
  onClose,
  activeCount,
  values,
  onChange,
  onReset,
}: JobFiltersDrawerProps) {
  if (!open) return null;

  return (
    <div className="fixed inset-0 z-50">
      <button
        type="button"
        className="absolute inset-0 bg-black/30"
        onClick={onClose}
        aria-label="Filtreleri kapat"
      />
      <div className="absolute inset-y-0 right-0 flex w-full max-w-md flex-col bg-white shadow-xl">
        <div className="flex items-center justify-between border-b border-surface px-5 py-4">
          <div className="flex items-center gap-2">
            <SlidersHorizontal className="h-4 w-4 text-primary" aria-hidden="true" />
            <h2 className="text-base font-semibold text-ink">Filtreler</h2>
            {activeCount > 0 ? (
              <span className="rounded-full bg-primary/10 px-2 py-0.5 text-xs font-medium text-primary">
                {activeCount}
              </span>
            ) : null}
          </div>
          <button
            type="button"
            onClick={onClose}
            className="inline-flex h-9 w-9 items-center justify-center rounded-lg text-ink-muted hover:bg-background"
            aria-label="Kapat"
          >
            <X className="h-5 w-5" />
          </button>
        </div>
        <div className="flex-1 overflow-y-auto p-5">
          <FiltersForm values={values} onChange={onChange} onReset={onReset} />
        </div>
        <div className="border-t border-surface p-4">
          <Button className="w-full" onClick={onClose}>
            Sonuçları Göster
          </Button>
        </div>
      </div>
    </div>
  );
}

import { useEffect, useMemo, useState } from 'react';
import { Info } from 'lucide-react';
import { getApiErrorMessage, getValidationErrors } from '@/api/client';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Card, CardBody, CardHeader } from '@/components/ui/Card';
import { EmptyState, LoadingState } from '@/components/ui/States';
import {
  useCompanyJobFitScoreSettings,
  useUpdateCompanyJobFitScoreSettings,
} from '@/hooks/useCompanyJobFitScoreSettings';
import type {
  FitScoreWeightKey,
  FitScoreWeights,
} from '@/types/companyJobFitScoreSettings';
import { FIT_SCORE_WEIGHT_SIGNALS } from '@/types/companyJobFitScoreSettings';
import { cn } from '@/utils/format';
import {
  areFitScoreWeightsEqual,
  canSaveFitScoreWeights,
  getSourceBadge,
  getTotalWeightLabel,
  getTotalWeightTone,
  mapFitScoreWeightValidationErrors,
  parseWeightInput,
  sumFitScoreWeights,
  updateWeightValue,
} from '@/utils/fitScoreWeights';

interface JobFitScoreSettingsSectionProps {
  jobId: number;
  readOnly?: boolean;
  onSaved?: (message: string) => void;
  onError?: (message: string) => void;
}

export function JobFitScoreSettingsSection({
  jobId,
  readOnly = false,
  onSaved,
  onError,
}: JobFitScoreSettingsSectionProps) {
  const { data, isLoading, isError, refetch } = useCompanyJobFitScoreSettings(jobId);
  const updateSettings = useUpdateCompanyJobFitScoreSettings();

  const [weights, setWeights] = useState<FitScoreWeights | null>(null);
  const [savedWeights, setSavedWeights] = useState<FitScoreWeights | null>(null);
  const [source, setSource] = useState<'default' | 'custom'>('default');
  const [inputErrors, setInputErrors] = useState<Partial<Record<FitScoreWeightKey, string>>>({});
  const [saveError, setSaveError] = useState<string | null>(null);

  useEffect(() => {
    if (!data) {
      return;
    }

    setWeights(data.weights);
    setSavedWeights(data.weights);
    setSource(data.source);
    setInputErrors({});
    setSaveError(null);
  }, [data]);

  const total = useMemo(
    () => (weights ? sumFitScoreWeights(weights) : 0),
    [weights],
  );

  const isDirty = useMemo(
    () => Boolean(weights && savedWeights && !areFitScoreWeightsEqual(weights, savedWeights)),
    [savedWeights, weights],
  );

  const canSave = useMemo(
    () => (weights ? canSaveFitScoreWeights(weights, isDirty, readOnly) : false),
    [isDirty, readOnly, weights],
  );

  const sourceBadge = getSourceBadge(source);
  const totalTone = getTotalWeightTone(total);

  function handleWeightChange(key: FitScoreWeightKey, rawValue: string) {
    if (readOnly || !weights) {
      return;
    }

    const parsed = parseWeightInput(rawValue);

    if (parsed === null) {
      setInputErrors((current) => ({
        ...current,
        [key]: 'Geçerli bir tam sayı girin (0-100).',
      }));
      return;
    }

    setInputErrors((current) => {
      const next = { ...current };
      delete next[key];
      return next;
    });

    setWeights(updateWeightValue(weights, key, parsed));
  }

  async function handleSave() {
    if (!weights || !canSave) {
      return;
    }

    setSaveError(null);

    try {
      const response = await updateSettings.mutateAsync({
        jobId,
        payload: { weights },
      });

      setWeights(response.weights);
      setSavedWeights(response.weights);
      setSource(response.source);
      onSaved?.('Fit Score ayarları kaydedildi.');
    } catch (error) {
      const validationErrors = getValidationErrors(error);
      const message =
        Object.keys(validationErrors).length > 0
          ? mapFitScoreWeightValidationErrors(validationErrors)
          : getApiErrorMessage(error, 'Fit Score ayarları kaydedilemedi.');

      setSaveError(message);
      onError?.(message);
    }
  }

  if (isLoading) {
    return (
      <Card>
        <CardHeader>
          <h2 className="text-lg font-semibold text-ink">Fit Score Ayarları</h2>
        </CardHeader>
        <CardBody>
          <LoadingState label="Fit Score ayarları yükleniyor..." />
        </CardBody>
      </Card>
    );
  }

  if (isError || !weights) {
    return (
      <Card>
        <CardHeader>
          <h2 className="text-lg font-semibold text-ink">Fit Score Ayarları</h2>
        </CardHeader>
        <CardBody>
          <EmptyState
            title="Fit Score ayarları yüklenemedi"
            description="Bağlantı sorunu olabilir. Tekrar deneyin."
            action={
              <Button type="button" variant="outline" onClick={() => void refetch()}>
                Tekrar Dene
              </Button>
            }
          />
        </CardBody>
      </Card>
    );
  }

  return (
    <Card>
      <CardHeader className="space-y-3">
        <div className="flex flex-wrap items-center gap-2">
          <h2 className="text-lg font-semibold text-ink">Fit Score Ayarları</h2>
          <Badge tone={sourceBadge.tone}>{sourceBadge.label}</Badge>
        </div>
        <p className="text-sm text-ink-muted">
          Fit Score, adayın bu ilana ne kadar uygun olduğunu belirler. İlanınız için hangi
          kriterlerin daha önemli olduğunu belirleyin.
        </p>
        <div className="flex items-start gap-2 rounded-xl border border-surface bg-background px-3 py-2 text-sm text-ink-muted">
          <Info className="mt-0.5 h-4 w-4 shrink-0 text-primary" aria-hidden="true" />
          <p>
            Örneğin Gerekli Yetenekler&apos;i %50 yaparsanız, adayın zorunlu yeteneklerle
            eşleşmesi toplam Fit Score üzerinde daha fazla etkili olur.
          </p>
        </div>
      </CardHeader>

      <CardBody className="space-y-5">
        {readOnly ? (
          <div className="rounded-xl border border-surface bg-background px-4 py-3 text-sm text-ink-muted">
            İlan yayınlandıktan sonra Fit Score ağırlıkları değiştirilemez.
          </div>
        ) : null}

        <ul className="space-y-4">
          {FIT_SCORE_WEIGHT_SIGNALS.map((signal) => {
            const value = weights[signal.key];

            return (
              <li
                key={signal.key}
                className="rounded-xl border border-surface bg-white p-4"
              >
                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                  <div className="min-w-0 flex-1 space-y-1">
                    <p className="font-medium text-ink">{signal.label}</p>
                    <p className="text-sm text-ink-muted">{signal.description}</p>
                    <div
                      className="mt-2 h-2 overflow-hidden rounded-full bg-surface"
                      aria-hidden="true"
                    >
                      <div
                        className="h-full rounded-full bg-primary transition-all duration-200"
                        style={{ width: `${Math.min(value, 100)}%` }}
                      />
                    </div>
                  </div>

                  <div className="w-full sm:w-28">
                    <label className="block space-y-1" htmlFor={`fit-weight-${signal.key}`}>
                      <span className="text-xs font-medium text-ink-muted">Ağırlık (%)</span>
                      <input
                        id={`fit-weight-${signal.key}`}
                        name={`fit_weight_${signal.key}`}
                        type="number"
                        min={0}
                        max={100}
                        step={1}
                        inputMode="numeric"
                        value={value}
                        disabled={readOnly || updateSettings.isPending}
                        onChange={(event) => handleWeightChange(signal.key, event.target.value)}
                        className={cn(
                          'h-11 w-full rounded-xl border border-surface bg-background px-3 text-sm text-ink outline-none transition focus:border-primary focus:bg-white focus:ring-4 focus:ring-primary/10 disabled:cursor-not-allowed disabled:opacity-60',
                          inputErrors[signal.key] && 'border-danger focus:border-danger focus:ring-danger/10',
                        )}
                        aria-invalid={Boolean(inputErrors[signal.key])}
                        aria-describedby={
                          inputErrors[signal.key] ? `fit-weight-error-${signal.key}` : undefined
                        }
                      />
                    </label>
                    {inputErrors[signal.key] ? (
                      <p
                        id={`fit-weight-error-${signal.key}`}
                        className="mt-1 text-xs text-danger"
                      >
                        {inputErrors[signal.key]}
                      </p>
                    ) : null}
                  </div>
                </div>
              </li>
            );
          })}
        </ul>

        <div
          className={cn(
            'rounded-xl border px-4 py-3 text-sm font-medium',
            totalTone === 'success'
              ? 'border-success/30 bg-success/10 text-primary-800'
              : 'border-danger/20 bg-danger/5 text-danger',
          )}
        >
          {getTotalWeightLabel(total)}
        </div>

        {!readOnly ? (
          <div className="space-y-3">
            {saveError ? (
              <div className="rounded-xl border border-danger/20 bg-danger/5 px-4 py-3 text-sm text-danger">
                {saveError}
              </div>
            ) : null}

            <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
              <p className="text-sm text-ink-muted">
                {isDirty ? 'Kaydedilmemiş değişiklikler var.' : 'Tüm değişiklikler kaydedildi.'}
              </p>
              <Button
                type="button"
                onClick={() => void handleSave()}
                loading={updateSettings.isPending}
                disabled={!canSave}
              >
                Fit Score Ayarlarını Kaydet
              </Button>
            </div>
          </div>
        ) : null}
      </CardBody>
    </Card>
  );
}

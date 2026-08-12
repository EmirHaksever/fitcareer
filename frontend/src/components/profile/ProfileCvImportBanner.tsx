import { useMemo, useState } from 'react';
import { Sparkles, X } from 'lucide-react';
import { Button } from '@/components/ui/Button';
import { useCandidateProfileMutations } from '@/hooks/useCandidateProfile';
import type { CandidateProfile, CvMetadata } from '@/types/candidate';
import {
  applyCvImport,
  countImportableItems,
  extractCvImportPlan,
  formatCvImportResult,
  summarizeCvImportPlan,
} from '@/utils/cvImport';
import { getApiErrorMessage } from '@/api/client';
import { formatValidationErrorMessage } from '@/utils/importErrors';

interface ProfileCvImportBannerProps {
  profile: CandidateProfile;
  cvMeta?: CvMetadata;
  visible: boolean;
  onDismiss?: () => void;
  onUpdated?: (message: string) => void;
  onError?: (message: string) => void;
}

export function ProfileCvImportBanner({
  profile,
  cvMeta,
  visible,
  onDismiss,
  onUpdated,
  onError,
}: ProfileCvImportBannerProps) {
  const { invalidate } = useCandidateProfileMutations();
  const [loading, setLoading] = useState(false);

  const parsedCv = cvMeta?.cv_parsed_data;
  const plan = useMemo(() => (parsedCv ? extractCvImportPlan(parsedCv) : null), [parsedCv]);
  const importableCount = plan ? countImportableItems(profile, plan) : 0;
  const summary = plan ? summarizeCvImportPlan(plan) : '';

  if (!visible || !parsedCv || !plan || importableCount === 0) {
    return null;
  }

  async function handleImport(overwriteProfile = false) {
    if (!parsedCv) return;

    setLoading(true);
    try {
      const result = await applyCvImport(profile, parsedCv, { overwriteProfile });
      await invalidate();
      onDismiss?.();
      onUpdated?.(
        result.warnings.length > 0
          ? `${formatCvImportResult(result)} ${result.warnings.join(' ')}`
          : formatCvImportResult(result),
      );
    } catch (error) {
      onError?.(formatValidationErrorMessage(error, getApiErrorMessage(error, 'CV içeriği aktarılamadı.')));
    } finally {
      setLoading(false);
    }
  }

  return (
    <div className="flex flex-col gap-4 rounded-xl border border-primary/20 bg-primary/5 p-4 sm:flex-row sm:items-center sm:justify-between">
      <div className="space-y-1">
        <p className="text-sm font-semibold text-ink">CV&apos;den profili doldur</p>
        <p className="text-sm text-ink-muted">
          CV&apos;de bulunan {summary} ilgili alanlara aktarılabilir.
        </p>
      </div>

      <div className="flex flex-wrap items-center gap-2">
        <Button type="button" size="sm" onClick={() => void handleImport()} loading={loading}>
          <Sparkles className="h-4 w-4" aria-hidden="true" />
          CV&apos;den Doldur
        </Button>
        <Button type="button" variant="outline" size="sm" onClick={() => void handleImport(true)} loading={loading}>
          Tümünü Güncelle
        </Button>
        {onDismiss ? (
          <Button type="button" variant="ghost" size="sm" onClick={onDismiss} aria-label="Kapat">
            <X className="h-4 w-4" aria-hidden="true" />
          </Button>
        ) : null}
      </div>
    </div>
  );
}

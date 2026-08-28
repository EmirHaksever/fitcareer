import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { ArrowLeft } from 'lucide-react';
import { CompanyJobForm, type CompanyJobFormValues } from '@/components/company-jobs/CompanyJobForm';
import { validateCompanyJobPayload } from '@/utils/companyJobValidation';
import { JobSkillsSection } from '@/components/company-jobs/JobSkillsSection';
import { Button } from '@/components/ui/Button';
import { companyJobSkillsApi } from '@/api/companyJobSkills';
import { getApiErrorMessage, getValidationErrors } from '@/api/client';
import { useCreateCompanyJob, usePublishCompanyJob } from '@/hooks/useCompanyJobs';
import type { JobSkillDraft } from '@/types/companyJob';
import { buildSyncPayload } from '@/utils/jobSkills';
import { sanitizePayload } from '@/utils/payload';

function mapValidationErrors(errors: Record<string, string[]>): Record<string, string> {
  return Object.fromEntries(
    Object.entries(errors).map(([key, messages]) => [key, messages[0] ?? 'Geçersiz değer']),
  );
}

export function CompanyJobCreatePage() {
  const navigate = useNavigate();
  const createJob = useCreateCompanyJob();
  const publishJob = usePublishCompanyJob();
  const [formErrors, setFormErrors] = useState<Record<string, string>>({});
  const [bannerError, setBannerError] = useState<string | null>(null);
  const [pendingSkills, setPendingSkills] = useState<JobSkillDraft[]>([]);

  async function handleCreate(payload: CompanyJobFormValues, publish = false) {
    setFormErrors({});
    setBannerError(null);

    const clientErrors = validateCompanyJobPayload(payload);
    if (Object.keys(clientErrors).length > 0) {
      setFormErrors(clientErrors);
      return;
    }

    try {
      const job = await createJob.mutateAsync(sanitizePayload(payload));

      if (pendingSkills.length > 0) {
        await companyJobSkillsApi.sync(job.id, buildSyncPayload(pendingSkills));
      }

      if (publish) {
        await publishJob.mutateAsync(job.id);
      }

      navigate(`/company/jobs/${job.id}/edit`, {
        replace: true,
        state: {
          message: publish
            ? 'İlan oluşturuldu ve yayınlandı.'
            : pendingSkills.length > 0
              ? 'İlan ve aranan yetenekler kaydedildi.'
              : 'İlan taslak olarak kaydedildi.',
        },
      });
    } catch (error) {
      const validationErrors = getValidationErrors(error);
      if (Object.keys(validationErrors).length > 0) {
        setFormErrors(mapValidationErrors(validationErrors));
      } else {
        setBannerError(getApiErrorMessage(error, 'İlan oluşturulamadı.'));
      }
    }
  }

  return (
    <div className="space-y-6">
      <section className="space-y-3">
        <Link
          to="/company/jobs"
          className="inline-flex items-center gap-2 text-sm font-medium text-ink-muted hover:text-ink"
        >
          <ArrowLeft className="h-4 w-4" aria-hidden="true" />
          İlanlara Dön
        </Link>
        <div className="space-y-2">
          <p className="text-sm font-medium text-primary">Yeni İlan</p>
          <h1 className="text-3xl font-bold tracking-tight text-ink">İlan Oluştur</h1>
          <p className="text-sm text-ink-muted">
            İlanını taslak olarak kaydedebilir veya doğrudan yayınlayabilirsin. Çalışma tipi ve
            konum bilinçli seçilmelidir; deneyim seviyesi varsayılan olarak atanmaz.
          </p>
        </div>
      </section>

      {bannerError ? (
        <div className="rounded-xl border border-danger/20 bg-danger/5 px-4 py-3 text-sm text-danger">
          {bannerError}
        </div>
      ) : null}

      <div className="space-y-6">
        <CompanyJobForm
          submitLabel="Taslak Olarak Kaydet"
          secondaryLabel="Kaydet ve Yayınla"
          isSubmitting={createJob.isPending}
          isSecondarySubmitting={createJob.isPending || publishJob.isPending}
          errors={formErrors}
          onSubmit={(payload) => handleCreate(payload, false)}
          onSecondarySubmit={(payload) => handleCreate(payload, true)}
        />

        <JobSkillsSection
          draftSkills={pendingSkills}
          onDraftSkillsChange={setPendingSkills}
          onError={(message) => setBannerError(message)}
        />
      </div>

      <div className="flex justify-start">
        <Link to="/company/jobs">
          <Button type="button" variant="outline">
            Vazgeç
          </Button>
        </Link>
      </div>
    </div>
  );
}

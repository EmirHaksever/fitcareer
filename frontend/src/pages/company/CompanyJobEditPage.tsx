import { useEffect, useState } from 'react';
import { Link, useLocation, useParams } from 'react-router-dom';
import { ArrowLeft, CheckCircle2, ExternalLink, Rocket } from 'lucide-react';
import { CompanyJobForm, type CompanyJobFormValues } from '@/components/company-jobs/CompanyJobForm';
import { JobFitScoreSettingsSection } from '@/components/company-jobs/JobFitScoreSettingsSection';
import { JobSkillsSection } from '@/components/company-jobs/JobSkillsSection';
import { JOB_STATUS_LABELS } from '@/components/company-jobs/jobFormOptions';
import { Button } from '@/components/ui/Button';
import { Card, CardBody } from '@/components/ui/Card';
import { EmptyState, Skeleton } from '@/components/ui/States';
import { getApiErrorMessage, getValidationErrors } from '@/api/client';
import { useCompanyJob, usePublishCompanyJob, useUpdateCompanyJob } from '@/hooks/useCompanyJobs';
import { sanitizePayload } from '@/utils/payload';

function mapValidationErrors(errors: Record<string, string[]>): Record<string, string> {
  return Object.fromEntries(
    Object.entries(errors).map(([key, messages]) => [key, messages[0] ?? 'Geçersiz değer']),
  );
}

function jobToFormValues(job: NonNullable<ReturnType<typeof useCompanyJob>['data']>): CompanyJobFormValues {
  return {
    title: job.title,
    description: job.description,
    requirements: job.requirements,
    responsibilities: job.responsibilities,
    category: job.category,
    employment_type: job.employment_type,
    work_type: job.work_type,
    experience_level: job.experience_level,
    city: job.city,
    country: job.country,
    salary_min: job.salary_min,
    salary_max: job.salary_max,
    salary_currency: job.salary_currency,
    is_salary_visible: job.is_salary_visible,
    application_deadline: job.application_deadline,
    contact_email: job.contact_email,
    contact_phone: job.contact_phone,
  };
}

export function CompanyJobEditPage() {
  const { id } = useParams();
  const jobId = Number(id);
  const location = useLocation();
  const { data: job, isLoading, isError, refetch } = useCompanyJob(jobId);
  const updateJob = useUpdateCompanyJob();
  const publishJob = usePublishCompanyJob();
  const [formErrors, setFormErrors] = useState<Record<string, string>>({});
  const [bannerMessage, setBannerMessage] = useState<string | null>(
    (location.state as { message?: string } | null)?.message ?? null,
  );
  const [bannerError, setBannerError] = useState<string | null>(null);

  useEffect(() => {
    if (bannerMessage) {
      const timer = window.setTimeout(() => setBannerMessage(null), 4000);
      return () => window.clearTimeout(timer);
    }
    return undefined;
  }, [bannerMessage]);

  const isDraft = job?.status === 'draft';
  const isPublished = job?.status === 'published';

  async function handleSave(payload: CompanyJobFormValues) {
    if (!job) return;

    setFormErrors({});
    setBannerError(null);

    try {
      await updateJob.mutateAsync({
        id: job.id,
        payload: sanitizePayload(payload),
      });
      setBannerMessage('Değişiklikler kaydedildi.');
    } catch (error) {
      const validationErrors = getValidationErrors(error);
      if (Object.keys(validationErrors).length > 0) {
        setFormErrors(mapValidationErrors(validationErrors));
      } else {
        setBannerError(getApiErrorMessage(error, 'İlan güncellenemedi.'));
      }
    }
  }

  async function handlePublish() {
    if (!job) return;

    setBannerError(null);

    try {
      await publishJob.mutateAsync(job.id);
      setBannerMessage('İlan başarıyla yayınlandı.');
    } catch (error) {
      setBannerError(getApiErrorMessage(error, 'İlan yayınlanamadı.'));
    }
  }

  if (!Number.isFinite(jobId)) {
    return <EmptyState title="Geçersiz ilan" description="İlan kimliği hatalı." />;
  }

  if (isLoading) {
    return (
      <div className="space-y-4">
        <Skeleton className="h-10 w-64" />
        <Skeleton className="h-96" />
      </div>
    );
  }

  if (isError || !job) {
    return (
      <EmptyState
        title="İlan bulunamadı"
        description="Bu ilana erişilemiyor veya silinmiş olabilir."
        action={
          <Button type="button" onClick={() => void refetch()}>
            Tekrar Dene
          </Button>
        }
      />
    );
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
        <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
          <div className="space-y-2">
            <p className="text-sm font-medium text-primary">İlan Düzenle</p>
            <h1 className="text-3xl font-bold tracking-tight text-ink">{job.title}</h1>
            <p className="text-sm text-ink-muted">
              Durum: {JOB_STATUS_LABELS[job.status] ?? job.status}
            </p>
          </div>

          <div className="flex flex-wrap gap-2">
            {isPublished ? (
              <Link to={`/jobs/${job.slug}`} target="_blank" rel="noreferrer">
                <Button type="button" variant="outline">
                  <ExternalLink className="h-4 w-4" aria-hidden="true" />
                  İlanı Görüntüle
                </Button>
              </Link>
            ) : null}
            <Link to={`/company/applications?job_id=${job.id}`}>
              <Button type="button" variant="secondary">
                Başvuruları Gör
              </Button>
            </Link>
            {isDraft ? (
              <Button
                type="button"
                onClick={() => void handlePublish()}
                disabled={publishJob.isPending || updateJob.isPending}
              >
                <Rocket className="h-4 w-4" aria-hidden="true" />
                {publishJob.isPending ? 'Yayınlanıyor...' : 'Yayınla'}
              </Button>
            ) : null}
          </div>
        </div>
      </section>

      {bannerMessage ? (
        <div className="flex items-center gap-2 rounded-xl border border-success/30 bg-success/10 px-4 py-3 text-sm text-primary-800">
          <CheckCircle2 className="h-4 w-4" aria-hidden="true" />
          {bannerMessage}
        </div>
      ) : null}

      {bannerError ? (
        <div className="rounded-xl border border-danger/20 bg-danger/5 px-4 py-3 text-sm text-danger">
          {bannerError}
        </div>
      ) : null}

      {isPublished ? (
        <>
          <Card className="border-primary/20 bg-primary/5">
            <CardBody className="text-sm text-ink-muted">
              Bu ilan yayında olduğu için düzenlenemez. Başvuruları inceleyebilir veya yeni bir ilan
              oluşturabilirsin.
            </CardBody>
          </Card>

          <JobFitScoreSettingsSection jobId={job.id} readOnly />
        </>
      ) : (
        <div className="space-y-6">
          <CompanyJobForm
            key={job.updated_at ?? job.id}
            initialValues={jobToFormValues(job)}
            submitLabel="Değişiklikleri Kaydet"
            isSubmitting={updateJob.isPending}
            errors={formErrors}
            onSubmit={handleSave}
          />

          <JobSkillsSection
            key={`${job.id}-${job.updated_at ?? 'skills'}`}
            jobId={job.id}
            initialSkills={job.skills}
            onSaved={(message) => setBannerMessage(message)}
            onError={(message) => setBannerError(message)}
          />

          <JobFitScoreSettingsSection
            jobId={job.id}
            onSaved={(message) => setBannerMessage(message)}
            onError={(message) => setBannerError(message)}
          />
        </div>
      )}
    </div>
  );
}

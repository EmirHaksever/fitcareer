import { ChevronRight } from 'lucide-react';
import { Link, useParams } from 'react-router-dom';
import { ApplicationStatusBadge } from '@/components/applications/ApplicationStatusBadge';
import { ApplicationStatusTimeline } from '@/components/applications/ApplicationStatusTimeline';
import { JobCompanyAvatar } from '@/components/jobs/JobCompanyAvatar';
import { JobMetaTag } from '@/components/jobs/JobMetaTag';
import { Button } from '@/components/ui/Button';
import { Card, CardBody } from '@/components/ui/Card';
import { EmptyState, Skeleton } from '@/components/ui/States';
import { useApplication } from '@/hooks/useApplications';
import {
  formatApplicationDate,
  formatApplicationScore,
} from '@/utils/applicationStatus';
import { formatEmploymentType, formatLocation, formatWorkType } from '@/utils/format';

export function ApplicationDetailPage() {
  const { id } = useParams<{ id: string }>();
  const applicationId = Number(id);
  const enabled = Number.isFinite(applicationId) && applicationId > 0;

  const { data: application, isLoading, isError, refetch } = useApplication(enabled ? applicationId : undefined);

  if (!enabled) {
    return (
      <EmptyState
        title="Başvuru bulunamadı"
        description="Geçersiz başvuru bağlantısı."
        action={
          <Link to="/applications">
            <Button type="button">Başvurularıma Dön</Button>
          </Link>
        }
      />
    );
  }

  if (isLoading) {
    return (
      <div className="space-y-6">
        <Skeleton className="h-6 w-56" />
        <Skeleton className="h-44" />
        <Skeleton className="h-72" />
      </div>
    );
  }

  if (isError || !application) {
    return (
      <EmptyState
        title="Başvuru yüklenemedi"
        description="Başvuru bulunamadı veya geçici bir hata oluştu."
        action={
          <div className="flex flex-wrap justify-center gap-3">
            <Button type="button" variant="outline" onClick={() => void refetch()}>
              Tekrar Dene
            </Button>
            <Link to="/applications">
              <Button type="button">Başvurularıma Dön</Button>
            </Link>
          </div>
        }
      />
    );
  }

  const job = application.job;
  const companyName = job?.company?.name ?? 'Şirket bilgisi yok';

  return (
    <div className="space-y-5">
      <nav className="flex items-center gap-1.5 text-sm text-ink-muted" aria-label="Breadcrumb">
        <Link to="/applications" className="font-medium transition hover:text-primary">
          Başvurularım
        </Link>
        <ChevronRight className="h-4 w-4" aria-hidden="true" />
        <span className="truncate font-medium text-ink">{job?.title ?? 'Başvuru Detayı'}</span>
      </nav>

      <Card>
        <CardBody className="space-y-5">
          <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div className="flex items-start gap-4">
              <JobCompanyAvatar name={companyName} size="lg" className="rounded-xl" />
              <div className="space-y-2">
                <h1 className="text-2xl font-bold tracking-tight text-ink sm:text-3xl">
                  {job?.title ?? 'İlan bilgisi yok'}
                </h1>
                <p className="text-base text-ink-muted">{companyName}</p>
                <div className="flex flex-wrap gap-2">
                  <JobMetaTag>{formatWorkType(job?.work_type ?? null)}</JobMetaTag>
                  <JobMetaTag>{formatEmploymentType(job?.employment_type ?? null)}</JobMetaTag>
                  <JobMetaTag>{formatLocation(job?.city ?? null, job?.country ?? null)}</JobMetaTag>
                </div>
              </div>
            </div>

            <ApplicationStatusBadge status={application.status} />
          </div>

          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div className="rounded-xl border border-surface bg-background px-4 py-3">
              <p className="text-xs text-ink-subtle">Başvuru Tarihi</p>
              <p className="mt-1 text-sm font-medium text-ink">{formatApplicationDate(application.applied_at)}</p>
            </div>
            <div className="rounded-xl border border-surface bg-background px-4 py-3">
              <p className="text-xs text-ink-subtle">Son Güncelleme</p>
              <p className="mt-1 text-sm font-medium text-ink">
                {formatApplicationDate(application.status_updated_at)}
              </p>
            </div>
            <div className="rounded-xl border border-surface bg-background px-4 py-3">
              <p className="text-xs text-ink-subtle">Uyum Skoru</p>
              <p className="mt-1 text-sm font-medium text-ink">
                {formatApplicationScore(application.match_score)}
              </p>
            </div>
            <div className="rounded-xl border border-surface bg-background px-4 py-3">
              <p className="text-xs text-ink-subtle">Güven Skoru</p>
              <p className="mt-1 text-sm font-medium text-ink">
                {formatApplicationScore(application.trust_score)}
              </p>
            </div>
          </div>

          {job?.slug ? (
            <Link to={`/jobs/${job.slug}`}>
              <Button type="button" variant="outline">
                İlanı Gör
              </Button>
            </Link>
          ) : null}
        </CardBody>
      </Card>

      {application.cover_letter ? (
        <Card>
          <CardBody className="space-y-3">
            <h2 className="text-lg font-semibold text-ink">Ön Yazı</h2>
            <p className="whitespace-pre-wrap text-sm leading-7 text-ink-muted">{application.cover_letter}</p>
          </CardBody>
        </Card>
      ) : null}

      <Card>
        <CardBody className="space-y-5">
          <h2 className="text-lg font-semibold text-ink">Başvuru Geçmişi</h2>
          <ApplicationStatusTimeline history={application.status_history ?? []} />
        </CardBody>
      </Card>
    </div>
  );
}

import { ChevronRight } from 'lucide-react';
import { useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { CompanyApplicationStatusBadge } from '@/components/company-applications/CompanyApplicationStatusBadge';
import { CompanyApplicationStatusModal } from '@/components/company-applications/CompanyApplicationStatusModal';
import { CompanyApplicationStatusTimeline } from '@/components/company-applications/CompanyApplicationStatusTimeline';
import { JobCompanyAvatar } from '@/components/jobs/JobCompanyAvatar';
import { JobMetaTag } from '@/components/jobs/JobMetaTag';
import { Button } from '@/components/ui/Button';
import { Card, CardBody } from '@/components/ui/Card';
import { EmptyState, Skeleton } from '@/components/ui/States';
import { useCompanyApplication } from '@/hooks/useCompanyApplications';
import {
  formatApplicationDate,
  formatApplicationScore,
} from '@/utils/applicationStatus';
import { getAllowedNextStatuses } from '@/utils/applicationTransitions';
import { formatLocation } from '@/utils/format';

export function CompanyApplicationDetailPage() {
  const { id } = useParams<{ id: string }>();
  const applicationId = Number(id);
  const enabled = Number.isFinite(applicationId) && applicationId > 0;
  const [statusModalOpen, setStatusModalOpen] = useState(false);

  const { data: application, isLoading, isError, refetch } = useCompanyApplication(
    enabled ? applicationId : undefined,
  );

  if (!enabled) {
    return (
      <EmptyState
        title="Başvuru bulunamadı"
        description="Geçersiz başvuru bağlantısı."
        action={
          <Link to="/company/applications">
            <Button type="button">Başvurulara Dön</Button>
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
            <Link to="/company/applications">
              <Button type="button">Başvurulara Dön</Button>
            </Link>
          </div>
        }
      />
    );
  }

  const candidate = application.candidate;
  const candidateName = candidate?.user?.name ?? 'Aday';
  const canTransition = getAllowedNextStatuses(application.status).length > 0;

  return (
    <div className="space-y-5">
      <nav className="flex items-center gap-1.5 text-sm text-ink-muted" aria-label="Breadcrumb">
        <Link to="/company/applications" className="font-medium transition hover:text-primary">
          Başvurular
        </Link>
        <ChevronRight className="h-4 w-4" aria-hidden="true" />
        <span className="truncate font-medium text-ink">{candidateName}</span>
      </nav>

      <Card>
        <CardBody className="space-y-5">
          <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div className="flex items-start gap-4">
              <JobCompanyAvatar name={candidateName} size="lg" className="rounded-xl" />
              <div className="space-y-2">
                <h1 className="text-2xl font-bold tracking-tight text-ink sm:text-3xl">{candidateName}</h1>
                <p className="text-base text-ink-muted">{candidate?.headline ?? '—'}</p>
                <p className="text-sm text-ink-subtle">{candidate?.user?.email ?? '—'}</p>
                <div className="flex flex-wrap gap-2">
                  <JobMetaTag>
                    {formatLocation(candidate?.city ?? null, candidate?.country ?? null)}
                  </JobMetaTag>
                  {candidate?.years_of_experience != null ? (
                    <JobMetaTag>{`${candidate.years_of_experience} yıl deneyim`}</JobMetaTag>
                  ) : null}
                  <JobMetaTag>{`Profil gücü: %${candidate?.profile_strength_score ?? 0}`}</JobMetaTag>
                </div>
              </div>
            </div>

            <div className="flex flex-col items-start gap-3 sm:items-end">
              <CompanyApplicationStatusBadge status={application.status} />
              {canTransition ? (
                <Button type="button" size="sm" onClick={() => setStatusModalOpen(true)}>
                  Durumu Güncelle
                </Button>
              ) : null}
            </div>
          </div>
        </CardBody>
      </Card>

      <Card>
        <CardBody className="space-y-4">
          <h2 className="text-lg font-semibold text-ink">İlan Bilgileri</h2>
          <div className="space-y-2">
            <p className="text-base font-medium text-ink">{application.job?.title ?? '—'}</p>
            <p className="text-sm text-ink-muted">
              {formatLocation(application.job?.city ?? null, application.job?.country ?? null)}
            </p>
            {application.job?.status ? (
              <JobMetaTag>{`Durum: ${application.job.status}`}</JobMetaTag>
            ) : null}
          </div>
        </CardBody>
      </Card>

      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <Card>
          <CardBody className="space-y-1">
            <p className="text-xs text-ink-subtle">Başvuru Tarihi</p>
            <p className="text-sm font-medium text-ink">{formatApplicationDate(application.applied_at)}</p>
          </CardBody>
        </Card>
        <Card>
          <CardBody className="space-y-1">
            <p className="text-xs text-ink-subtle">Son Güncelleme</p>
            <p className="text-sm font-medium text-ink">
              {formatApplicationDate(application.status_updated_at)}
            </p>
          </CardBody>
        </Card>
        <Card>
          <CardBody className="space-y-1">
            <p className="text-xs text-ink-subtle">Uyum Skoru</p>
            <p className="text-sm font-medium text-ink">{formatApplicationScore(application.match_score)}</p>
          </CardBody>
        </Card>
        <Card>
          <CardBody className="space-y-1">
            <p className="text-xs text-ink-subtle">Güven Skoru</p>
            <p className="text-sm font-medium text-ink">{formatApplicationScore(application.trust_score)}</p>
          </CardBody>
        </Card>
      </div>

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
          <CompanyApplicationStatusTimeline history={application.status_history ?? []} />
        </CardBody>
      </Card>

      <CompanyApplicationStatusModal
        application={application}
        open={statusModalOpen}
        onClose={() => setStatusModalOpen(false)}
        onSuccess={() => void refetch()}
      />
    </div>
  );
}

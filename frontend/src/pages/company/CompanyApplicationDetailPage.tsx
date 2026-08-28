import { ChevronRight } from 'lucide-react';
import { useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { CandidateMatchAnalysisCard } from '@/components/company-applications/CandidateMatchAnalysisCard';
import { CompanyApplicationStatusBadge } from '@/components/company-applications/CompanyApplicationStatusBadge';
import { CompanyApplicationStatusModal } from '@/components/company-applications/CompanyApplicationStatusModal';
import { CompanyApplicationStatusTimeline } from '@/components/company-applications/CompanyApplicationStatusTimeline';
import { MatchScoreDisplay } from '@/components/company-applications/MatchScoreDisplay';
import { JobCompanyAvatar } from '@/components/jobs/JobCompanyAvatar';
import { JobMetaTag } from '@/components/jobs/JobMetaTag';
import { Button } from '@/components/ui/Button';
import { Card, CardBody } from '@/components/ui/Card';
import { EmptyState, Skeleton } from '@/components/ui/States';
import { JOB_STATUS_LABELS } from '@/components/company-jobs/jobFormOptions';
import { useCompanyApplication } from '@/hooks/useCompanyApplications';
import { formatApplicationDate, formatApplicationScore } from '@/utils/applicationStatus';
import { getAllowedNextStatuses } from '@/utils/applicationTransitions';
import { formatEmploymentType, formatExperienceLevel, formatLocation, formatWorkType } from '@/utils/format';

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
  const jobStatus = application.job?.status
    ? (JOB_STATUS_LABELS[application.job.status] ?? application.job.status)
    : null;

  return (
    <div className="min-w-0 space-y-5">
      <nav className="flex min-w-0 items-center gap-1.5 text-sm text-ink-muted" aria-label="Breadcrumb">
        <Link to="/company/applications" className="font-medium transition hover:text-primary">
          Başvurular
        </Link>
        <ChevronRight className="h-4 w-4 shrink-0" aria-hidden="true" />
        <span className="truncate font-medium text-ink">{candidateName}</span>
      </nav>

      <Card>
        <CardBody className="space-y-5">
          <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div className="flex min-w-0 items-start gap-4">
              <JobCompanyAvatar name={candidateName} size="lg" className="rounded-xl" />
              <div className="min-w-0 space-y-2">
                <h1 className="text-2xl font-bold tracking-tight text-ink sm:text-3xl">{candidateName}</h1>
                <p className="truncate text-base text-ink-muted">{candidate?.headline ?? '—'}</p>
                <p className="break-all text-sm text-ink-subtle">{candidate?.user?.email ?? '—'}</p>
                <div className="flex flex-wrap gap-2">
                  {candidate?.city || candidate?.country ? (
                    <JobMetaTag>
                      {formatLocation(candidate?.city ?? null, candidate?.country ?? null)}
                    </JobMetaTag>
                  ) : null}
                  {candidate?.years_of_experience != null ? (
                    <JobMetaTag>{`${candidate.years_of_experience} yıl deneyim`}</JobMetaTag>
                  ) : null}
                  <JobMetaTag>{`Profil gücü: %${candidate?.profile_strength_score ?? 0}`}</JobMetaTag>
                </div>
              </div>
            </div>
          </div>
        </CardBody>
      </Card>

      <div className="grid min-w-0 gap-4 lg:grid-cols-2">
        <Card>
          <CardBody className="min-w-0 space-y-3">
            <h2 className="text-lg font-semibold text-ink">İlan</h2>
            <p className="text-base font-medium text-ink">{application.job?.title ?? '—'}</p>
            <p className="text-sm text-ink-muted">
              {formatLocation(application.job?.city ?? null, application.job?.country ?? null)}
            </p>
            <div className="flex flex-wrap gap-2">
              {jobStatus ? <JobMetaTag>{jobStatus}</JobMetaTag> : null}
              {application.job?.employment_type ? (
                <JobMetaTag>{formatEmploymentType(application.job.employment_type)}</JobMetaTag>
              ) : null}
              {application.job?.work_type ? (
                <JobMetaTag>{formatWorkType(application.job.work_type)}</JobMetaTag>
              ) : null}
              {application.job?.experience_level ? (
                <JobMetaTag>{formatExperienceLevel(application.job.experience_level)}</JobMetaTag>
              ) : null}
            </div>
          </CardBody>
        </Card>

        <Card>
          <CardBody className="min-w-0 space-y-3">
            <h2 className="text-lg font-semibold text-ink">Başvuru</h2>
            <div className="flex flex-wrap items-center gap-2">
              <CompanyApplicationStatusBadge status={application.status} />
              {canTransition ? (
                <Button type="button" size="sm" onClick={() => setStatusModalOpen(true)}>
                  Durumu Güncelle
                </Button>
              ) : null}
            </div>
            <p className="text-sm text-ink-muted">
              Başvuru tarihi: {formatApplicationDate(application.applied_at)}
            </p>
            <p className="text-sm text-ink-muted">
              Son güncelleme: {formatApplicationDate(application.status_updated_at)}
            </p>
          </CardBody>
        </Card>
      </div>

      <Card className="border-primary/20 bg-primary/5">
        <CardBody className="flex min-w-0 flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <div className="min-w-0">
            <p className="text-sm font-medium text-primary">Uyum / Eşleşme</p>
            <p className="mt-1 text-sm text-ink-muted">Bu adayın bu ilanla uyumu</p>
          </div>
          <MatchScoreDisplay
            score={application.match_score}
            status={application.match_analysis_status}
            variant="hero"
          />
        </CardBody>
      </Card>

      <CandidateMatchAnalysisCard application={application} />

      {application.cover_letter ? (
        <Card>
          <CardBody className="space-y-3">
            <h2 className="text-lg font-semibold text-ink">Ön Yazı</h2>
            <p className="whitespace-pre-wrap break-words text-sm leading-7 text-ink-muted">
              {application.cover_letter}
            </p>
          </CardBody>
        </Card>
      ) : null}

      <div className="grid gap-4 sm:grid-cols-2">
        <Card>
          <CardBody className="space-y-1">
            <p className="text-xs text-ink-subtle">Güven Skoru</p>
            <p className="text-sm font-medium text-ink">{formatApplicationScore(application.trust_score)}</p>
          </CardBody>
        </Card>
        <Card>
          <CardBody className="space-y-1">
            <p className="text-xs text-ink-subtle">Son Durum Güncellemesi</p>
            <p className="text-sm font-medium text-ink">
              {formatApplicationDate(application.status_updated_at)}
            </p>
          </CardBody>
        </Card>
      </div>

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

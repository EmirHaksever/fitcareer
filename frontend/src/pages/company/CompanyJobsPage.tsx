import { Link } from 'react-router-dom';
import { BriefcaseBusiness, ClipboardList, ExternalLink, Plus } from 'lucide-react';
import { JOB_STATUS_LABELS } from '@/components/company-jobs/jobFormOptions';
import { JobMetaTag } from '@/components/jobs/JobMetaTag';
import { Button } from '@/components/ui/Button';
import { Card, CardBody } from '@/components/ui/Card';
import { EmptyState, Skeleton } from '@/components/ui/States';
import { useCompanyJobs } from '@/hooks/useCompanyJobs';
import {
  formatEmploymentType,
  formatLocation,
  formatWorkType,
} from '@/utils/format';
import { formatApplicationDate } from '@/utils/applicationStatus';
import { companyPublicJobPath } from '@/utils/companyPortal';

export function CompanyJobsPage() {
  const { data, isLoading, isError, refetch } = useCompanyJobs({ per_page: 50 });

  const jobs = data?.items ?? [];
  const total = data?.pagination.total ?? 0;

  return (
    <div className="space-y-6">
      <section className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div className="space-y-2">
          <p className="text-sm font-medium text-primary">İlan Yönetimi</p>
          <h1 className="text-3xl font-bold tracking-tight text-ink">İlanlarım</h1>
          <p className="text-sm text-ink-muted">
            Yeni ilan oluştur, taslakları düzenle ve yayınlanan ilanların başvurularını takip et.
          </p>
        </div>
        <Link to="/company/jobs/new">
          <Button type="button">
            <Plus className="h-4 w-4" aria-hidden="true" />
            Yeni İlan Oluştur
          </Button>
        </Link>
      </section>

      {isLoading ? (
        <div className="space-y-3">
          <Skeleton className="h-24" />
          <Skeleton className="h-24" />
        </div>
      ) : null}

      {isError ? (
        <EmptyState
          title="İlanlar yüklenemedi"
          description="İlan listesi getirilemedi. Lütfen tekrar dene."
          action={
            <Button type="button" onClick={() => void refetch()}>
              Tekrar Dene
            </Button>
          }
        />
      ) : null}

      {!isLoading && !isError && jobs.length === 0 ? (
        <EmptyState
          title="Henüz ilan yok"
          description="İlk ilanını oluşturarak adaylardan başvuru almaya başla."
          action={
            <Link to="/company/jobs/new">
              <Button type="button">İlan Oluştur</Button>
            </Link>
          }
        />
      ) : null}

      {!isLoading && !isError && jobs.length > 0 ? (
        <div className="space-y-3">
          <p className="text-sm text-ink-muted">{total.toLocaleString('tr-TR')} ilan listeleniyor</p>
          {jobs.map((job) => {
            const listingPath = companyPublicJobPath(job.status, job.slug);

            return (
              <Card key={job.id} className="transition hover:border-primary/20">
              <CardBody className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div className="min-w-0 space-y-2">
                  <div className="flex flex-wrap items-center gap-2">
                    <h2 className="truncate text-lg font-semibold text-ink">{job.title}</h2>
                    <span className="rounded-full bg-background px-2.5 py-1 text-xs font-medium text-ink-muted">
                      {JOB_STATUS_LABELS[job.status] ?? job.status}
                    </span>
                  </div>
                  <p className="text-sm text-ink-muted">
                    {formatLocation(job.city, job.country)} · {formatWorkType(job.work_type)} ·{' '}
                    {formatEmploymentType(job.employment_type)}
                  </p>
                  <div className="flex flex-wrap gap-2">
                    {job.category ? <JobMetaTag>{job.category}</JobMetaTag> : null}
                    {job.published_at ? (
                      <JobMetaTag>{`Yayın: ${formatApplicationDate(job.published_at)}`}</JobMetaTag>
                    ) : (
                      <JobMetaTag>Taslak</JobMetaTag>
                    )}
                  </div>
                </div>

                <div className="flex flex-wrap gap-2">
                  {listingPath ? (
                    <Link to={listingPath}>
                      <Button type="button" variant="outline" size="sm">
                        <ExternalLink className="h-4 w-4" aria-hidden="true" />
                        İlanı Görüntüle
                      </Button>
                    </Link>
                  ) : null}
                  {job.status === 'draft' ? (
                    <Link to={`/company/jobs/${job.id}/edit`}>
                      <Button type="button" variant="outline" size="sm">
                        <BriefcaseBusiness className="h-4 w-4" aria-hidden="true" />
                        Düzenle
                      </Button>
                    </Link>
                  ) : null}
                  <Link to={`/company/applications?job_id=${job.id}`}>
                    <Button type="button" variant="secondary" size="sm">
                      <ClipboardList className="h-4 w-4" aria-hidden="true" />
                      Başvurular
                    </Button>
                  </Link>
                </div>
              </CardBody>
            </Card>
            );
          })}
        </div>
      ) : null}
    </div>
  );
}

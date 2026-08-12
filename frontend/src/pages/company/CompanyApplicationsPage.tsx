import { useMemo } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { CompanyApplicationCard } from '@/components/company-applications/CompanyApplicationCard';
import { CompanyApplicationStatusBadge } from '@/components/company-applications/CompanyApplicationStatusBadge';
import { JobCompanyAvatar } from '@/components/jobs/JobCompanyAvatar';
import { Button } from '@/components/ui/Button';
import { Card, CardBody } from '@/components/ui/Card';
import { EmptyState, Skeleton } from '@/components/ui/States';
import { useCompanyApplications, useCompanyJobsForFilter } from '@/hooks/useCompanyApplications';
import type { ApplicationStatus } from '@/types/application';
import type { CompanyApplication } from '@/types/companyApplication';
import { APPLICATION_STATUS_LABELS, formatApplicationDate, formatApplicationScore } from '@/utils/applicationStatus';
import { cn, formatLocation } from '@/utils/format';

const DEFAULT_PER_PAGE = 10;

function CompanyApplicationTableRow({ application }: { application: CompanyApplication }) {
  const candidateName = application.candidate?.user?.name ?? 'Aday';

  return (
    <tr className="border-b border-surface last:border-b-0">
      <td className="px-4 py-4">
        <Link to={`/company/applications/${application.id}`} className="flex items-center gap-3">
          <JobCompanyAvatar name={candidateName} size="sm" />
          <div className="min-w-0">
            <p className="truncate font-medium text-ink">{candidateName}</p>
            <p className="truncate text-sm text-ink-muted">{application.candidate?.headline ?? '—'}</p>
            <p className="text-xs text-ink-subtle">
              {formatLocation(application.candidate?.city ?? null, application.candidate?.country ?? null)}
            </p>
          </div>
        </Link>
      </td>
      <td className="px-4 py-4 text-sm text-ink">{application.job?.title ?? '—'}</td>
      <td className="px-4 py-4 text-sm text-ink">{formatApplicationScore(application.match_score)}</td>
      <td className="px-4 py-4 text-sm text-ink">{formatApplicationScore(application.trust_score)}</td>
      <td className="px-4 py-4 text-sm text-ink-muted">{formatApplicationDate(application.applied_at)}</td>
      <td className="px-4 py-4">
        <CompanyApplicationStatusBadge status={application.status} />
      </td>
      <td className="px-4 py-4 text-right">
        <Link to={`/company/applications/${application.id}`}>
          <Button type="button" variant="outline" size="sm">
            Detay
          </Button>
        </Link>
      </td>
    </tr>
  );
}

export function CompanyApplicationsPage() {
  const [searchParams, setSearchParams] = useSearchParams();
  const { data: jobsData } = useCompanyJobsForFilter();

  const page = Number(searchParams.get('page') ?? '1');
  const perPage = Number(searchParams.get('per_page') ?? String(DEFAULT_PER_PAGE));
  const jobId = searchParams.get('job_id');
  const status = searchParams.get('status');

  const queryParams = useMemo(
    () => ({
      page: Number.isFinite(page) && page > 0 ? page : 1,
      per_page: Number.isFinite(perPage) && perPage > 0 ? perPage : DEFAULT_PER_PAGE,
      job_id: jobId ? Number(jobId) : undefined,
      status: (status as ApplicationStatus | null) || undefined,
    }),
    [page, perPage, jobId, status],
  );

  const { data, isLoading, isError, refetch } = useCompanyApplications(queryParams);

  const pagination = data?.pagination;
  const currentPage = pagination?.current_page ?? 1;
  const lastPage = pagination?.last_page ?? 1;

  function updateParams(patch: Record<string, string | undefined>) {
    const params = new URLSearchParams(searchParams);

    Object.entries(patch).forEach(([key, value]) => {
      if (!value) {
        params.delete(key);
      } else {
        params.set(key, value);
      }
    });

    if (!patch.page) {
      params.set('page', '1');
    }

    setSearchParams(params, { replace: true });
  }

  const selectClassName = cn(
    'h-11 w-full rounded-xl border border-surface bg-white px-3.5 text-sm text-ink outline-none transition',
    'focus:border-primary focus:ring-4 focus:ring-primary/10',
  );

  return (
    <div className="space-y-5">
      <header className="space-y-1">
        <h1 className="text-2xl font-bold tracking-tight text-ink sm:text-3xl">Başvurular</h1>
        <p className="text-sm text-ink-muted">İlanlarına gelen aday başvurularını görüntüle ve yönet.</p>
        {pagination ? (
          <p className="text-sm font-medium text-ink">
            Toplam {pagination.total.toLocaleString('tr-TR')} başvuru
          </p>
        ) : null}
      </header>

      <Card>
        <CardBody className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          <label className="block space-y-2">
            <span className="text-sm font-medium text-ink">İlan</span>
            <select
              value={jobId ?? ''}
              onChange={(event) => updateParams({ job_id: event.target.value || undefined })}
              className={selectClassName}
            >
              <option value="">Tüm ilanlar</option>
              {jobsData?.items.map((job) => (
                <option key={job.id} value={job.id}>
                  {job.title}
                </option>
              ))}
            </select>
          </label>

          <label className="block space-y-2">
            <span className="text-sm font-medium text-ink">Durum</span>
            <select
              value={status ?? ''}
              onChange={(event) => updateParams({ status: event.target.value || undefined })}
              className={selectClassName}
            >
              <option value="">Tüm durumlar</option>
              {Object.entries(APPLICATION_STATUS_LABELS).map(([value, label]) => (
                <option key={value} value={value}>
                  {label}
                </option>
              ))}
            </select>
          </label>
        </CardBody>
      </Card>

      {isLoading ? (
        <div className="space-y-4">
          <Skeleton className="h-32" />
          <Skeleton className="h-32" />
        </div>
      ) : null}

      {isError ? (
        <EmptyState
          title="Başvurular yüklenemedi"
          description="Başvurular yüklenirken bir hata oluştu. Lütfen tekrar dene."
          action={
            <Button type="button" onClick={() => void refetch()}>
              Tekrar Dene
            </Button>
          }
        />
      ) : null}

      {!isLoading && !isError && data?.items.length === 0 ? (
        <EmptyState
          title="Henüz başvuru bulunmuyor."
          description="İlanlarına yapılan başvurular burada listelenecek."
        />
      ) : null}

      {!isLoading && !isError && data && data.items.length > 0 ? (
        <>
          <div className="hidden lg:block">
            <Card>
              <CardBody className="overflow-x-auto p-0">
                <table className="min-w-full text-left">
                  <thead className="border-b border-surface bg-background text-xs uppercase tracking-wide text-ink-subtle">
                    <tr>
                      <th className="px-4 py-3 font-medium">Aday</th>
                      <th className="px-4 py-3 font-medium">İlan</th>
                      <th className="px-4 py-3 font-medium">Uyum</th>
                      <th className="px-4 py-3 font-medium">Güven</th>
                      <th className="px-4 py-3 font-medium">Tarih</th>
                      <th className="px-4 py-3 font-medium">Durum</th>
                      <th className="px-4 py-3 font-medium text-right">İşlem</th>
                    </tr>
                  </thead>
                  <tbody>
                    {data.items.map((application) => (
                      <CompanyApplicationTableRow key={application.id} application={application} />
                    ))}
                  </tbody>
                </table>
              </CardBody>
            </Card>
          </div>

          <div className="space-y-4 lg:hidden">
            {data.items.map((application) => (
              <CompanyApplicationCard key={application.id} application={application} />
            ))}
          </div>
        </>
      ) : null}

      {pagination && lastPage > 1 ? (
        <div className="flex flex-col items-center justify-between gap-3 rounded-xl border border-surface bg-white px-4 py-3 sm:flex-row">
          <p className="text-sm text-ink-muted">
            Sayfa {currentPage} / {lastPage}
          </p>
          <div className="flex items-center gap-2">
            <Button
              type="button"
              variant="outline"
              size="sm"
              disabled={currentPage <= 1}
              onClick={() => updateParams({ page: String(currentPage - 1) })}
            >
              Önceki
            </Button>
            <Button
              type="button"
              variant="outline"
              size="sm"
              disabled={currentPage >= lastPage}
              onClick={() => updateParams({ page: String(currentPage + 1) })}
            >
              Sonraki
            </Button>
          </div>
        </div>
      ) : null}
    </div>
  );
}

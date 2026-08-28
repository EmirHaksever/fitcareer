import { useQueries } from '@tanstack/react-query';
import {
  ArrowRight,
  BriefcaseBusiness,
  ClipboardList,
  Clock3,
  Plus,
  Sparkles,
  TrendingUp,
  Users,
} from 'lucide-react';
import { Link } from 'react-router-dom';
import { ApplicationTrendChart } from '@/components/company-dashboard/ApplicationTrendChart';
import { JobStatusChart } from '@/components/company-dashboard/JobStatusChart';
import { PipelineBarChart } from '@/components/company-dashboard/PipelineBarChart';
import { CompanyApplicationStatusBadge } from '@/components/company-applications/CompanyApplicationStatusBadge';
import { MatchScoreDisplay } from '@/components/company-applications/MatchScoreDisplay';
import { JobCompanyAvatar } from '@/components/jobs/JobCompanyAvatar';
import { JobMetaTag } from '@/components/jobs/JobMetaTag';
import { Button } from '@/components/ui/Button';
import { Card, CardBody, CardHeader } from '@/components/ui/Card';
import { EmptyState, Skeleton } from '@/components/ui/States';
import { useCompanyApplications, useCompanyJobsForFilter, COMPANY_APPLICATIONS_KEY } from '@/hooks/useCompanyApplications';
import { useCompanyProfile } from '@/hooks/useCompanyProfile';
import { useAuth } from '@/hooks/useAuth';
import { companyApplicationsApi } from '@/api/companyApplications';
import type { ApplicationStatus } from '@/types/application';
import type { CompanyApplication } from '@/types/companyApplication';
import { formatApplicationDate } from '@/utils/applicationStatus';
import { formatEmploymentType, formatExperienceLevel, formatLocation, formatWorkType, getFirstName } from '@/utils/format';
import {
  buildApplicationTrend,
  buildJobStatusSegments,
  buildPipelineSegments,
} from '@/utils/dashboardCharts';
import { companyVerificationHeadline } from '@/utils/companyVerification';
import {
  applicationCountForJob,
  averageCompletedMatchScore,
  averageMatchScoreForJob,
  highestCompletedMatch,
  selectPriorityApplications,
} from '@/utils/companyDashboardMatch';

const statToneClasses = {
  primary: 'border-primary/20 bg-primary/5 text-primary',
  warning: 'border-warning/20 bg-amber-50 text-amber-700',
  neutral: 'border-surface bg-background text-ink',
  success: 'border-success bg-success text-primary-800',
} as const;

const JOB_STATUS_LABELS: Record<string, string> = {
  draft: 'Taslak',
  pending_review: 'İncelemede',
  published: 'Yayında',
  expired: 'Süresi doldu',
  closed: 'Kapalı',
  flagged: 'İşaretlendi',
};

const PIPELINE_STATUSES: ApplicationStatus[] = [
  'submitted',
  'under_review',
  'shortlisted',
  'interview',
  'offered',
];

function DashboardStatCard({
  label,
  value,
  helper,
  tone,
}: {
  label: string;
  value: string;
  helper: string;
  tone: keyof typeof statToneClasses;
}) {
  return (
    <Card className={statToneClasses[tone]}>
      <CardBody className="min-w-0 space-y-2">
        <p className="text-sm font-medium">{label}</p>
        <p className="truncate text-3xl font-bold">{value}</p>
        <p className="text-xs opacity-80">{helper}</p>
      </CardBody>
    </Card>
  );
}

function PriorityCandidateRow({ application }: { application: CompanyApplication }) {
  const candidateName = application.candidate?.user?.name ?? 'Aday';

  return (
    <div className="flex min-w-0 flex-col gap-3 rounded-xl border border-surface px-4 py-3 sm:flex-row sm:items-center">
      <JobCompanyAvatar name={candidateName} size="sm" />
      <div className="min-w-0 flex-1 space-y-1">
        <p className="truncate text-sm font-semibold text-ink">{candidateName}</p>
        <p className="truncate text-xs text-ink-muted">{application.job?.title ?? 'İlan bilgisi yok'}</p>
        <div className="flex flex-wrap items-center gap-2">
          <MatchScoreDisplay
            score={application.match_score}
            status={application.match_analysis_status}
            variant="inline"
          />
          <CompanyApplicationStatusBadge status={application.status} />
        </div>
        <p className="text-xs text-ink-subtle">{formatApplicationDate(application.applied_at)}</p>
      </div>
      <Link to={`/company/applications/${application.id}`} className="shrink-0">
        <Button type="button" variant="outline" size="sm" className="w-full sm:w-auto">
          İncele
        </Button>
      </Link>
    </div>
  );
}

export function CompanyDashboardPage() {
  const { user } = useAuth();
  const { data: profile, isLoading: profileLoading } = useCompanyProfile();
  const { data: jobsData, isLoading: jobsLoading } = useCompanyJobsForFilter();
  const {
    data: applicationsData,
    isLoading: applicationsLoading,
    isError: applicationsError,
    refetch: refetchApplications,
  } = useCompanyApplications({ per_page: 50, sort: 'attention' });

  const { data: submittedData } = useCompanyApplications({ status: 'submitted', per_page: 1 });
  const { data: reviewData } = useCompanyApplications({ status: 'under_review', per_page: 1 });

  const pipelineQueries = useQueries({
    queries: PIPELINE_STATUSES.map((status) => ({
      queryKey: [...COMPANY_APPLICATIONS_KEY, 'pipeline', status],
      queryFn: () => companyApplicationsApi.listCompanyApplications({ status, per_page: 1 }),
    })),
  });

  const pipelineCounts = PIPELINE_STATUSES.map((status, index) => ({
    status,
    count: pipelineQueries[index]?.data?.pagination.total ?? 0,
  }));

  const trendPoints = buildApplicationTrend(applicationsData?.items ?? []);
  const hasTrendData = trendPoints.some((point) => point.count > 0);
  const pipelineSegments = buildPipelineSegments(
    pipelineCounts.map(({ status, count }) => ({ status, count })),
  );
  const jobStatusSegments = buildJobStatusSegments(jobsData?.items ?? []);

  const jobs = jobsData?.items ?? [];
  const publishedJobs = jobs.filter((job) => job.status === 'published').length;
  const totalJobs = jobsData?.pagination.total ?? jobs.length;
  const totalApplications = applicationsData?.pagination.total ?? 0;
  const pendingApplications =
    (submittedData?.pagination.total ?? 0) + (reviewData?.pagination.total ?? 0);
  const averageMatch = averageCompletedMatchScore(applicationsData?.items ?? []);
  const priorityCandidates = selectPriorityApplications(applicationsData?.items ?? []);
  const topMatch = highestCompletedMatch(applicationsData?.items ?? []);
  const firstPublishedJob = jobs.find((job) => job.status === 'published');

  const companyName = profile?.name ?? user?.name ?? 'Şirket';
  const isLoading = profileLoading || jobsLoading || applicationsLoading;

  return (
    <div className="space-y-8">
      <section className="space-y-3">
        <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
          <div className="space-y-2">
            <p className="text-sm font-medium text-primary">Şirket Paneli</p>
            <h1 className="text-3xl font-bold tracking-tight text-ink">
              Merhaba, {getFirstName(companyName)}
            </h1>
            <p className="max-w-2xl text-sm text-ink-muted">
              İlanlarını yönet, başvuruları takip et ve aday süreçlerini tek ekrandan kontrol et.
            </p>
          </div>

          <div className="flex flex-wrap gap-2">
            <Link to="/company/jobs/new">
              <Button type="button" variant="secondary">
                <Plus className="h-4 w-4" aria-hidden="true" />
                Yeni İlan
              </Button>
            </Link>
            <Link to="/company/applications">
              <Button type="button">
                <Users className="h-4 w-4" aria-hidden="true" />
                Başvuruları Gör
              </Button>
            </Link>
          </div>
        </div>

        {profile ? (
          <div className="flex flex-wrap items-center gap-2">
            <JobMetaTag>{companyVerificationHeadline(profile.verification_status)}</JobMetaTag>
            {profile.is_verified ? <JobMetaTag>Doğrulanmış şirket</JobMetaTag> : null}
            {profile.city || profile.country ? (
              <JobMetaTag>{formatLocation(profile.city, profile.country)}</JobMetaTag>
            ) : null}
            {profile.industry ? <JobMetaTag>{profile.industry}</JobMetaTag> : null}
          </div>
        ) : null}
      </section>

      {isLoading ? (
        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
          <Skeleton className="h-28" />
          <Skeleton className="h-28" />
          <Skeleton className="h-28" />
          <Skeleton className="h-28" />
        </div>
      ) : (
        <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
          <DashboardStatCard
            label="Aktif İlan"
            value={publishedJobs.toLocaleString('tr-TR')}
            helper="Adaylara açık yayınlanmış ilanlar"
            tone="primary"
          />
          <DashboardStatCard
            label="Toplam Başvuru"
            value={totalApplications.toLocaleString('tr-TR')}
            helper="Tüm ilanlarına gelen başvurular"
            tone="success"
          />
          <DashboardStatCard
            label="İncelenmesi Gereken"
            value={pendingApplications.toLocaleString('tr-TR')}
            helper="Başvuruldu ve inceleniyor durumları"
            tone="warning"
          />
          <DashboardStatCard
            label="Ortalama Aday Uyumu"
            value={averageMatch !== null ? `${averageMatch}%` : '—'}
            helper={
              averageMatch !== null
                ? 'Tamamlanmış uyum analizleri üzerinden'
                : 'Henüz yeterli veri yok'
            }
            tone="neutral"
          />
        </section>
      )}

      <section className="grid min-w-0 gap-4 xl:grid-cols-3">
        <Card className="min-w-0 xl:col-span-2">
          <CardHeader className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div className="min-w-0">
              <h2 className="text-lg font-semibold text-ink">Öncelikli Adaylar</h2>
              <p className="mt-1 text-sm text-ink-muted">İnceleme bekleyen ve yüksek uyumlu başvurular</p>
            </div>
            <Link to="/company/applications" className="shrink-0 text-sm font-medium text-primary hover:underline">
              Tüm Başvuruları Gör
            </Link>
          </CardHeader>
          <CardBody className="space-y-3">
            {applicationsLoading ? (
              <>
                <Skeleton className="h-20" />
                <Skeleton className="h-20" />
              </>
            ) : null}

            {applicationsError ? (
              <EmptyState
                title="Başvurular yüklenemedi"
                description="Öncelikli adaylar getirilemedi. Lütfen tekrar dene."
                action={
                  <Button type="button" onClick={() => void refetchApplications()}>
                    Tekrar Dene
                  </Button>
                }
              />
            ) : null}

            {!applicationsLoading && !applicationsError && priorityCandidates.length === 0 ? (
              <p className="rounded-xl bg-background px-4 py-3 text-sm text-ink-muted">
                İncelenecek aday bulunmuyor.
                <span className="mt-1 block text-ink-subtle">
                  Yeni başvurular geldiğinde burada en uygun adayları göreceksiniz.
                </span>
              </p>
            ) : null}

            {priorityCandidates.map((application) => (
              <PriorityCandidateRow key={application.id} application={application} />
            ))}
          </CardBody>
        </Card>

        <div className="space-y-4">
          <Card>
            <CardHeader>
              <h2 className="text-lg font-semibold text-ink">İlan Durumları</h2>
              <p className="text-sm text-ink-muted">İlanlarının yayın durumu dağılımı</p>
            </CardHeader>
            <CardBody>
              {jobsLoading ? <Skeleton className="h-32" /> : <JobStatusChart segments={jobStatusSegments} />}
            </CardBody>
          </Card>

          <Card>
            <CardHeader>
              <div className="flex items-center gap-2">
                <TrendingUp className="h-4 w-4 text-primary" aria-hidden="true" />
                <h2 className="text-lg font-semibold text-ink">Başvuru Trendi</h2>
              </div>
              <p className="mt-1 text-sm text-ink-muted">Son 14 günlük başvuru hareketi</p>
            </CardHeader>
            <CardBody>
              {applicationsLoading ? (
                <Skeleton className="h-16" />
              ) : hasTrendData ? (
                <ApplicationTrendChart points={trendPoints} />
              ) : (
                <p className="text-sm text-ink-muted">
                  Henüz yeterli başvuru verisi yok.
                  <span className="mt-1 block text-ink-subtle">
                    İlanınız başvuru aldıkça burada başvuru trendini göreceksiniz.
                  </span>
                </p>
              )}
            </CardBody>
          </Card>
        </div>
      </section>

      <section className="grid min-w-0 gap-6 xl:grid-cols-[2fr_1fr]">
        <Card>
          <CardHeader>
            <h2 className="text-lg font-semibold text-ink">Başvuru Hunisi</h2>
            <p className="text-sm text-ink-muted">Aktif süreçteki aday dağılımı</p>
          </CardHeader>
          <CardBody className="space-y-4">
            <PipelineBarChart segments={pipelineSegments} />
            <div className="grid gap-2 sm:grid-cols-2">
              {pipelineCounts.map(({ status, count }) => (
                <Link
                  key={status}
                  to={`/company/applications?status=${status}`}
                  className="flex items-center justify-between gap-3 rounded-lg px-1 py-1 transition hover:bg-background"
                >
                  <CompanyApplicationStatusBadge status={status} />
                  <span className="text-sm font-semibold text-ink">{count.toLocaleString('tr-TR')}</span>
                </Link>
              ))}
            </div>
          </CardBody>
        </Card>

        <Card className="bg-gradient-to-br from-primary/5 to-secondary/5">
          <CardBody className="space-y-3">
            <div className="flex items-center gap-2 text-primary">
              <Sparkles className="h-4 w-4" aria-hidden="true" />
              <h3 className="text-lg font-semibold text-ink">Aday Sürecini Hızlandır</h3>
            </div>
            {pendingApplications > 0 ? (
              <>
                <p className="text-sm font-medium text-ink">
                  {pendingApplications.toLocaleString('tr-TR')} aday inceleme bekliyor
                </p>
                {topMatch?.candidate?.user?.name && topMatch.match_score !== null ? (
                  <p className="text-sm text-ink-muted">
                    En yüksek uyumlu aday:
                    <span className="mt-1 block font-medium text-ink">
                      {topMatch.candidate.user.name} — {topMatch.match_score}%
                    </span>
                  </p>
                ) : null}
                <Link to="/company/applications">
                  <Button variant="secondary" className="w-full">
                    Başvuruları İncele
                    <ArrowRight className="h-4 w-4" aria-hidden="true" />
                  </Button>
                </Link>
              </>
            ) : totalApplications === 0 ? (
              <>
                <p className="text-sm font-medium text-ink">Henüz başvuru yok</p>
                <p className="text-sm text-ink-muted">
                  İlanınızı paylaşarak daha fazla adaya ulaşabilirsiniz.
                </p>
                <Link to={firstPublishedJob ? `/jobs/${firstPublishedJob.slug}` : '/company/jobs'}>
                  <Button variant="secondary" className="w-full">
                    İlanı Görüntüle
                    <ArrowRight className="h-4 w-4" aria-hidden="true" />
                  </Button>
                </Link>
              </>
            ) : (
              <>
                <p className="text-sm text-ink-muted">
                  İnceleme bekleyen başvuru yok. Mevcut aday süreçlerini takip edebilirsiniz.
                </p>
                <Link to="/company/applications">
                  <Button variant="secondary" className="w-full">
                    Başvuruları Gör
                    <ArrowRight className="h-4 w-4" aria-hidden="true" />
                  </Button>
                </Link>
              </>
            )}
          </CardBody>
        </Card>
      </section>

      <section className="space-y-4">
        <div className="flex items-center justify-between">
          <h2 className="text-xl font-semibold text-ink">İlanların</h2>
          <div className="flex items-center gap-3">
            <div className="flex items-center gap-2 text-sm text-ink-muted">
              <BriefcaseBusiness className="h-4 w-4" aria-hidden="true" />
              {totalJobs.toLocaleString('tr-TR')} ilan
            </div>
            <Link to="/company/jobs">
              <Button type="button" variant="outline" size="sm">
                Tümünü Gör
              </Button>
            </Link>
          </div>
        </div>

        {!jobsLoading && jobs.length === 0 ? (
          <EmptyState
            title="Henüz ilan oluşturulmamış"
            description="İlk ilanını oluşturduğunda burada listelenecek."
            action={
              <Link to="/company/jobs/new">
                <Button type="button">
                  <Plus className="h-4 w-4" aria-hidden="true" />
                  İlan Oluştur
                </Button>
              </Link>
            }
          />
        ) : null}

        {jobsLoading ? (
          <div className="grid gap-4 md:grid-cols-2">
            <Skeleton className="h-28" />
            <Skeleton className="h-28" />
          </div>
        ) : null}

        <div className="grid gap-4 md:grid-cols-2">
          {jobs.slice(0, 4).map((job) => {
            const applicationCount = applicationCountForJob(
              applicationsData?.items ?? [],
              job.id,
              job.applications_count,
            );
            const jobAverage = averageMatchScoreForJob(applicationsData?.items ?? [], job.id);

            return (
              <Card key={job.id} className="min-w-0 transition hover:border-primary/25">
                <CardBody className="space-y-3">
                  <div className="flex items-start justify-between gap-3">
                    <div className="min-w-0">
                      <p className="truncate text-base font-semibold text-ink">{job.title}</p>
                      <p className="text-sm text-ink-muted">
                        {formatLocation(job.city ?? null, job.country ?? null)}
                      </p>
                    </div>
                    <span className="shrink-0 rounded-full bg-background px-2.5 py-1 text-xs font-medium text-ink-muted">
                      {JOB_STATUS_LABELS[job.status] ?? job.status}
                    </span>
                  </div>
                  <div className="flex flex-wrap gap-2">
                    <JobMetaTag>{formatWorkType(job.work_type ?? null)}</JobMetaTag>
                    <JobMetaTag>{formatEmploymentType(job.employment_type ?? null)}</JobMetaTag>
                    <JobMetaTag>{formatExperienceLevel(job.experience_level ?? null)}</JobMetaTag>
                  </div>
                  <div className="space-y-1 text-sm text-ink-muted">
                    {applicationCount > 0 ? (
                      <p>{applicationCount.toLocaleString('tr-TR')} Başvuru</p>
                    ) : (
                      <p>Henüz başvuru yok</p>
                    )}
                    {jobAverage !== null ? <p>Ortalama Uyum: {jobAverage}%</p> : null}
                  </div>
                  <div className="flex items-center gap-2 text-xs text-ink-subtle">
                    <Clock3 className="h-3.5 w-3.5" aria-hidden="true" />
                    {job.published_at
                      ? `Yayın: ${formatApplicationDate(job.published_at)}`
                      : 'Henüz yayınlanmadı'}
                  </div>
                  <Link to={`/company/applications?job_id=${job.id}`}>
                    <Button type="button" variant="outline" size="sm" className="w-full">
                      <ClipboardList className="h-4 w-4" aria-hidden="true" />
                      Başvuruları Gör
                    </Button>
                  </Link>
                </CardBody>
              </Card>
            );
          })}
        </div>
      </section>
    </div>
  );
}

import { Link } from 'react-router-dom';
import { TrustDistributionChart } from '@/components/dashboard/TrustDistributionChart';
import { JobCard } from '@/components/jobs/JobCard';
import { Button } from '@/components/ui/Button';
import { Card, CardBody } from '@/components/ui/Card';
import { EmptyState, Skeleton } from '@/components/ui/States';
import { useAuth } from '@/hooks/useAuth';
import { useCanViewFitScore } from '@/hooks/useCanViewFitScore';
import { useDashboardStats } from '@/hooks/useDashboardStats';
import { useSavedJobIds } from '@/hooks/useSavedJobs';
import { mapDashboardStats } from '@/utils/dashboardStats';
import { getFirstName } from '@/utils/format';

const statToneClasses = {
  primary: 'border-primary/20 bg-primary/5 text-primary',
  warning: 'border-warning/20 bg-amber-50 text-amber-700',
  neutral: 'border-surface bg-background text-ink',
  success: 'border-success bg-success text-primary-800',
};

export function DashboardPage() {
  const { user } = useAuth();
  const showFitScore = useCanViewFitScore();
  const { data, isLoading, isError, refetch } = useDashboardStats();
  const { data: savedJobIds = [] } = useSavedJobIds();

  const stats = data ? mapDashboardStats(data) : [];
  const assistant = data?.career_assistant;

  return (
    <div className="space-y-8">
      <section className="space-y-2">
        <h1 className="text-3xl font-bold text-ink">Merhaba, {getFirstName(user?.name ?? 'Aday')}</h1>
        <p className="text-ink-muted">
          Güvenilir ilanları keşfet, uyum skorlarını takip et ve kariyer yolculuğunu yönet.
        </p>
      </section>

      {isError ? (
        <EmptyState
          title="Dashboard yüklenemedi"
          description="Özet veriler şu anda getirilemedi."
          action={
            <Button type="button" variant="outline" onClick={() => void refetch()}>
              Tekrar Dene
            </Button>
          }
        />
      ) : null}

      <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        {isLoading
          ? Array.from({ length: 4 }).map((_, index) => <Skeleton key={index} className="h-28" />)
          : stats.map((stat) => (
              <Card key={stat.id} className={statToneClasses[stat.tone]}>
                <CardBody className="space-y-2">
                  <p className="text-sm font-medium">{stat.label}</p>
                  <p className="text-3xl font-bold">{stat.value}</p>
                  <p className="text-xs opacity-80">{stat.helper}</p>
                </CardBody>
              </Card>
            ))}
      </section>

      <section className="grid gap-6 xl:grid-cols-[2fr_1fr]">
        <div className="space-y-4">
          <div className="flex items-center justify-between">
            <h2 className="text-xl font-semibold text-ink">Sana Önerilen İlanlar</h2>
            <Link to="/jobs" className="text-sm font-medium text-primary hover:underline">
              Tümünü Gör
            </Link>
          </div>

          {isLoading ? (
            <div className="space-y-4">
              <Skeleton className="h-32" />
              <Skeleton className="h-32" />
            </div>
          ) : null}

          {!isLoading && !isError && (data?.recommended_jobs.length ?? 0) === 0 ? (
            <EmptyState
              title="Henüz öneri yok"
              description="CV profilini tamamlayıp ilanları inceledikçe öneriler burada görünecek."
              action={
                <Link to="/profile?cv=1">
                  <Button variant="outline">CV Profilime Git</Button>
                </Link>
              }
            />
          ) : null}

          <div className="space-y-4">
            {data?.recommended_jobs.map((job) => (
              <JobCard
                key={job.id}
                job={job}
                showFitScore={showFitScore}
                isSaved={savedJobIds.includes(job.id)}
                canSave={showFitScore}
              />
            ))}
          </div>
        </div>

        <div className="space-y-4">
          <Card>
            <CardBody className="space-y-4">
              <h2 className="text-lg font-semibold text-ink">Piyasa Güvenilirlik Dağılımı</h2>
              {isLoading ? (
                <Skeleton className="h-40" />
              ) : (
                <TrustDistributionChart
                  buckets={data?.trust_distribution ?? []}
                  totalJobs={data?.stats.total_jobs ?? 0}
                />
              )}
            </CardBody>
          </Card>

          <Card className="bg-gradient-to-br from-primary/5 to-secondary/5">
            <CardBody className="space-y-3">
              <h3 className="text-lg font-semibold text-ink">Kariyer İpucu</h3>
              {assistant?.has_cv && assistant.analyzed_job_count > 0 ? (
                <p className="text-sm text-ink-muted">
                  {assistant.analyzed_job_count} ilan için uyum analizin mevcut.
                  {assistant.average_fit_score !== null ? (
                    <>
                      {' '}
                      Ortalama uyum skorun{' '}
                      <span className="font-semibold text-ink">%{assistant.average_fit_score}</span>.
                    </>
                  ) : null}
                </p>
              ) : (
                <p className="text-sm text-ink-muted">
                  CV profilini güncel tutarak Fit Score hesaplamalarının daha doğru olmasını sağlayabilirsin.
                </p>
              )}
              <Link to={assistant?.has_cv ? '/fit-analysis' : '/profile?cv=1'}>
                <Button variant="secondary">
                  {assistant?.has_cv ? 'Uyum Analizini Gör' : "CV'ni Analiz Et"}
                </Button>
              </Link>
            </CardBody>
          </Card>
        </div>
      </section>
    </div>
  );
}

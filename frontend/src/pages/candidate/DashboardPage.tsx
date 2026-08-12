import { Link } from 'react-router-dom';
import { getDashboardStatsPlaceholder } from '@/api/dashboard';
import { JobCard } from '@/components/jobs/JobCard';
import { Button } from '@/components/ui/Button';
import { Card, CardBody, CardHeader } from '@/components/ui/Card';
import { EmptyState, Skeleton } from '@/components/ui/States';
import { useAuth } from '@/hooks/useAuth';
import { useJobs } from '@/hooks/useJobs';
import { getFirstName } from '@/utils/format';

const statToneClasses = {
  primary: 'border-primary/20 bg-primary/5 text-primary',
  warning: 'border-warning/20 bg-amber-50 text-amber-700',
  neutral: 'border-surface bg-background text-ink',
  success: 'border-success bg-success text-primary-800',
};

export function DashboardPage() {
  const { user } = useAuth();
  const { data, isLoading, isError } = useJobs({ per_page: 4, sort: 'published_at' });
  const stats = getDashboardStatsPlaceholder();

  return (
    <div className="space-y-8">
      <section className="space-y-2">
        <h1 className="text-3xl font-bold text-ink">Merhaba, {getFirstName(user?.name ?? 'Aday')}</h1>
        <p className="text-ink-muted">
          Güvenilir ilanları keşfet, uyum skorlarını takip et ve kariyer yolculuğunu yönet.
        </p>
      </section>

      <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        {stats.map((stat) => (
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

          {isError ? (
            <EmptyState
              title="İlanlar yüklenemedi"
              description="İş ilanları şu anda getirilemedi. Lütfen daha sonra tekrar dene."
            />
          ) : null}

          {!isLoading && !isError && data?.items.length === 0 ? (
            <EmptyState
              title="Henüz yayınlanmış ilan yok"
              description="Yeni ilanlar eklendiğinde burada görünecek."
              action={
                <Link to="/jobs">
                  <Button variant="outline">İş İlanlarına Göz At</Button>
                </Link>
              }
            />
          ) : null}

          <div className="space-y-4">
            {data?.items.map((job) => <JobCard key={job.id} job={job} />)}
          </div>
        </div>

        <div className="space-y-4">
          <Card>
            <CardHeader>
              <h2 className="text-lg font-semibold text-ink">Piyasa Güvenilirlik Dağılımı</h2>
            </CardHeader>
            <CardBody>
              <EmptyState
                title="Grafik verisi bekleniyor"
                description="TODO(mock): Dashboard analytics endpoint henüz backend'de yok."
              />
            </CardBody>
          </Card>

          <Card className="bg-gradient-to-br from-primary/5 to-secondary/5">
            <CardBody className="space-y-3">
              <h3 className="text-lg font-semibold text-ink">Kariyer İpucu</h3>
              <p className="text-sm text-ink-muted">
                CV profilini güncel tutarak Fit Score hesaplamalarının daha doğru olmasını sağlayabilirsin.
              </p>
              <Link to="/profile">
                <Button variant="secondary">CV&apos;ni Analiz Et</Button>
              </Link>
            </CardBody>
          </Card>
        </div>
      </section>
    </div>
  );
}

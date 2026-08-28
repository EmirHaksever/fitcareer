import { Link } from 'react-router-dom';
import { JobCard } from '@/components/jobs/JobCard';
import { Button } from '@/components/ui/Button';
import { Card, CardBody } from '@/components/ui/Card';
import { EmptyState, Skeleton } from '@/components/ui/States';
import { useCanViewFitScore } from '@/hooks/useCanViewFitScore';
import { useDashboardStats } from '@/hooks/useDashboardStats';
import { useCandidateProfile } from '@/hooks/useCandidateProfile';
import { useSavedJobIds } from '@/hooks/useSavedJobs';

export function FitAnalysisPage() {
  const showFitScore = useCanViewFitScore();
  const { data: profile } = useCandidateProfile();
  const { data, isLoading, isError, refetch } = useDashboardStats();
  const { data: savedJobIds = [] } = useSavedJobIds();

  const analyzedJobs = data?.analyzed_jobs ?? [];
  const stats = data?.stats;

  if (!showFitScore) {
    return (
      <EmptyState
        title="Uyum analizi için giriş gerekli"
        description="Fit Score yalnızca aday hesapları için kullanılabilir."
        action={
          <Link to="/login">
            <Button>Giriş Yap</Button>
          </Link>
        }
      />
    );
  }

  return (
    <div className="space-y-6">
      <header className="space-y-1">
        <h1 className="text-2xl font-bold tracking-tight text-ink sm:text-3xl">Uyum Analizi</h1>
        <p className="text-sm text-ink-muted">
          CV profiline göre ilanlarla uyumunu incele ve profilini güçlendir.
        </p>
      </header>

      {isError ? (
        <EmptyState
          title="Analiz verileri yüklenemedi"
          description="Uyum analizi özeti şu anda getirilemedi."
          action={
            <Button type="button" variant="outline" onClick={() => void refetch()}>
              Tekrar Dene
            </Button>
          }
        />
      ) : null}

      <section className="grid gap-4 md:grid-cols-3">
        <Card className="border-primary/20 bg-primary/5">
          <CardBody className="space-y-1">
            <p className="text-sm text-ink-muted">Ortalama Uyum</p>
            <p className="text-3xl font-bold text-primary">
              {stats?.average_fit_score !== null && stats?.average_fit_score !== undefined
                ? `%${stats.average_fit_score}`
                : '—'}
            </p>
            <p className="text-xs text-ink-muted">
              {(stats?.analyzed_job_count ?? 0) > 0
                ? `${stats?.analyzed_job_count} ilan analiz edildi`
                : 'Henüz analiz yok'}
            </p>
          </CardBody>
        </Card>
        <Card>
          <CardBody className="space-y-1">
            <p className="text-sm text-ink-muted">Profil Gücü</p>
            <p className="text-3xl font-bold text-ink">
              {profile?.profile_strength_score !== undefined
                ? `%${profile.profile_strength_score}`
                : '—'}
            </p>
            <p className="text-xs text-ink-muted">CV profil tamamlama</p>
          </CardBody>
        </Card>
        <Card className="bg-gradient-to-br from-secondary/5 to-primary/5">
          <CardBody className="space-y-3">
            <p className="text-sm font-medium text-ink">Profilini güçlendir</p>
            <p className="text-xs text-ink-muted">
              {profile?.has_cv
                ? 'Beceri ve deneyimlerini güncelleyerek skorunu artır.'
                : 'CV yüklemeden uyum analizi başlatılamaz.'}
            </p>
            <Link to={profile?.has_cv ? '/profile' : '/profile?cv=1'}>
              <Button size="sm" className="w-full">
                {profile?.has_cv ? 'Profili Düzenle' : 'CV Yükle'}
              </Button>
            </Link>
          </CardBody>
        </Card>
      </section>

      {!isLoading && (stats?.analyzed_job_count ?? 0) === 0 ? (
        <EmptyState
          title="Henüz uyum analizi yok"
          description="CV profilini tamamlayıp ilan detaylarını görüntüledikçe uyum skorların hesaplanır."
          action={
            <Link to="/profile?cv=1">
              <Button>CV Profilime Git</Button>
            </Link>
          }
        />
      ) : (
        <section className="space-y-4">
          <h2 className="text-lg font-semibold text-ink">En Yüksek Uyumlu İlanlar</h2>

          {isLoading ? (
            <div className="space-y-4">
              <Skeleton className="h-32" />
              <Skeleton className="h-32" />
            </div>
          ) : null}

          <div className="space-y-4">
            {analyzedJobs.map((job) => (
              <JobCard
                key={job.id}
                job={job}
                showFitScore={showFitScore}
                isSaved={savedJobIds.includes(job.id)}
                canSave={showFitScore}
              />
            ))}
          </div>
        </section>
      )}
    </div>
  );
}

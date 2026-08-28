import { useState } from 'react';
import { Link } from 'react-router-dom';
import { JobList } from '@/components/jobs/JobList';
import { Button } from '@/components/ui/Button';
import { EmptyState, Skeleton } from '@/components/ui/States';
import { useCanViewFitScore } from '@/hooks/useCanViewFitScore';
import { useSavedJobs } from '@/hooks/useSavedJobs';

export function SavedJobsPage() {
  const [page, setPage] = useState(1);
  const showFitScore = useCanViewFitScore();
  const { data, isLoading, isError, refetch } = useSavedJobs(page);

  if (isLoading) {
    return (
      <div className="space-y-4">
        <Skeleton className="h-10 w-48" />
        <Skeleton className="h-40" />
        <Skeleton className="h-40" />
      </div>
    );
  }

  if (isError) {
    return (
      <EmptyState
        title="Kaydedilen ilanlar yüklenemedi"
        description="Bağlantınızı kontrol edip tekrar deneyin."
        action={
          <Button type="button" onClick={() => void refetch()}>
            Tekrar Dene
          </Button>
        }
      />
    );
  }

  const pagination = data?.pagination;
  const savedIds = (data?.items ?? []).map((job) => job.id);
  const isEmpty = (pagination?.total ?? 0) === 0;

  if (isEmpty) {
    return (
      <div className="space-y-5">
        <header className="space-y-1">
          <h1 className="text-2xl font-bold tracking-tight text-ink sm:text-3xl">Kaydedilen İlanlar</h1>
          <p className="text-sm text-ink-muted">Henüz kayıtlı ilan yok.</p>
        </header>
        <EmptyState
          title="Kayıtlı ilan bulunmuyor"
          description="İlan detayında veya listede kaydet butonuna basarak ilgini çeken ilanları burada toplayabilirsin."
          action={
            <Link to="/jobs">
              <Button>İlanları Keşfet</Button>
            </Link>
          }
        />
      </div>
    );
  }

  return (
    <div className="space-y-5">
      <header className="space-y-1">
        <h1 className="text-2xl font-bold tracking-tight text-ink sm:text-3xl">Kaydedilen İlanlar</h1>
        <p className="text-sm text-ink-muted">
          {(pagination?.total ?? 0).toLocaleString('tr-TR')} kayıtlı ilan
        </p>
      </header>

      <JobList
        jobs={data?.items ?? []}
        isLoading={false}
        isError={false}
        onRetry={() => void refetch()}
        showFitScore={showFitScore}
        savedJobIds={savedIds}
        canSave={showFitScore}
      />

      {pagination && pagination.last_page > 1 ? (
        <div className="flex items-center justify-between rounded-xl border border-surface bg-white px-4 py-3">
          <p className="text-sm text-ink-muted">
            Sayfa {pagination.current_page} / {pagination.last_page}
          </p>
          <div className="flex gap-2">
            <Button
              type="button"
              variant="outline"
              size="sm"
              disabled={pagination.current_page <= 1}
              onClick={() => setPage((current) => current - 1)}
            >
              Önceki
            </Button>
            <Button
              type="button"
              variant="outline"
              size="sm"
              disabled={pagination.current_page >= pagination.last_page}
              onClick={() => setPage((current) => current + 1)}
            >
              Sonraki
            </Button>
          </div>
        </div>
      ) : null}
    </div>
  );
}

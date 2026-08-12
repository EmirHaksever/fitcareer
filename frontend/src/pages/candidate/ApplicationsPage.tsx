import { useMemo } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { ApplicationCard } from '@/components/applications/ApplicationCard';
import { Button } from '@/components/ui/Button';
import { EmptyState, Skeleton } from '@/components/ui/States';
import { useApplications } from '@/hooks/useApplications';

const DEFAULT_PER_PAGE = 10;

export function ApplicationsPage() {
  const [searchParams, setSearchParams] = useSearchParams();

  const page = Number(searchParams.get('page') ?? '1');
  const perPage = Number(searchParams.get('per_page') ?? String(DEFAULT_PER_PAGE));

  const queryParams = useMemo(
    () => ({
      page: Number.isFinite(page) && page > 0 ? page : 1,
      per_page: Number.isFinite(perPage) && perPage > 0 ? perPage : DEFAULT_PER_PAGE,
    }),
    [page, perPage],
  );

  const { data, isLoading, isError, refetch } = useApplications(queryParams);

  const pagination = data?.pagination;
  const currentPage = pagination?.current_page ?? 1;
  const lastPage = pagination?.last_page ?? 1;

  function updatePage(nextPage: number) {
    const params = new URLSearchParams(searchParams);
    params.set('page', String(nextPage));
    setSearchParams(params, { replace: true });
  }

  return (
    <div className="space-y-5">
      <header className="space-y-1">
        <h1 className="text-2xl font-bold tracking-tight text-ink sm:text-3xl">Başvurularım</h1>
        <p className="text-sm text-ink-muted">Başvurduğun iş ilanlarını ve süreçlerini takip et.</p>
        {pagination ? (
          <p className="text-sm font-medium text-ink">
            Toplam {pagination.total.toLocaleString('tr-TR')} başvuru
          </p>
        ) : null}
      </header>

      {isLoading ? (
        <div className="space-y-4">
          <Skeleton className="h-32" />
          <Skeleton className="h-32" />
          <Skeleton className="h-32" />
        </div>
      ) : null}

      {isError ? (
        <EmptyState
          title="Başvurular yüklenemedi"
          description="Başvuruların yüklenirken bir hata oluştu. Lütfen tekrar dene."
          action={
            <Button type="button" onClick={() => void refetch()}>
              Tekrar Dene
            </Button>
          }
        />
      ) : null}

      {!isLoading && !isError && data?.items.length === 0 ? (
        <EmptyState
          title="Henüz bir ilana başvurmadın."
          description="İlanları keşfederek uygun pozisyonlara başvurabilirsin."
          action={
            <Link to="/jobs">
              <Button type="button">İlanları Keşfet</Button>
            </Link>
          }
        />
      ) : null}

      {!isLoading && !isError && data && data.items.length > 0 ? (
        <div className="space-y-4">
          {data.items.map((application) => (
            <ApplicationCard key={application.id} application={application} />
          ))}
        </div>
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
              onClick={() => updatePage(currentPage - 1)}
            >
              Önceki
            </Button>
            <Button
              type="button"
              variant="outline"
              size="sm"
              disabled={currentPage >= lastPage}
              onClick={() => updatePage(currentPage + 1)}
            >
              Sonraki
            </Button>
          </div>
        </div>
      ) : null}
    </div>
  );
}

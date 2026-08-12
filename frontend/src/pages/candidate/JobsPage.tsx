import { useMemo, useState, type FormEvent } from 'react';
import { Search, SlidersHorizontal } from 'lucide-react';
import { useSearchParams } from 'react-router-dom';
import { JobFiltersDrawer } from '@/components/jobs/JobFilters';
import { JobList } from '@/components/jobs/JobList';
import { JobSort } from '@/components/jobs/JobSort';
import { Button } from '@/components/ui/Button';
import { useJobs } from '@/hooks/useJobs';
import { useCanViewFitScore } from '@/hooks/useCanViewFitScore';
import { useSavedJobIds } from '@/hooks/useSavedJobs';
import type { JobSearchParams, JobSortValue } from '@/types/api';
import {
  buildJobSearchParams,
  countActiveFilters,
  DEFAULT_JOB_SEARCH,
  parseJobSearchParams,
} from '@/utils/jobSearch';
import { cn } from '@/utils/format';

export function JobsPage() {
  const [searchParams, setSearchParams] = useSearchParams();
  const [filtersOpen, setFiltersOpen] = useState(false);
  const [keywordDraft, setKeywordDraft] = useState(() => searchParams.get('keyword') ?? '');

  const queryParams = useMemo(() => parseJobSearchParams(searchParams), [searchParams]);
  const activeFilterCount = countActiveFilters(queryParams);

  const showFitScore = useCanViewFitScore();
  const { data: savedJobIds = [] } = useSavedJobIds();
  const { data, isLoading, isError, refetch } = useJobs(queryParams);

  function updateParams(patch: Partial<JobSearchParams>) {
    const next = { ...queryParams, ...patch };
    setSearchParams(buildJobSearchParams(next), { replace: true });
  }

  function resetFilters() {
    setKeywordDraft('');
    setSearchParams(
      buildJobSearchParams({
        sort: queryParams.sort ?? DEFAULT_JOB_SEARCH.sort,
        page: 1,
        per_page: queryParams.per_page ?? DEFAULT_JOB_SEARCH.per_page,
      }),
      { replace: true },
    );
  }

  function handleSearchSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    updateParams({ keyword: keywordDraft.trim() || undefined, page: 1 });
  }

  const pagination = data?.pagination;
  const currentPage = pagination?.current_page ?? 1;
  const lastPage = pagination?.last_page ?? 1;

  return (
    <div className="space-y-5">
      <header className="space-y-1">
        <h1 className="text-2xl font-bold tracking-tight text-ink sm:text-3xl">İş İlanları</h1>
        {pagination ? (
          <p className="text-sm text-ink-muted">
            {pagination.total.toLocaleString('tr-TR')} ilan bulundu
          </p>
        ) : (
          <p className="text-sm text-ink-muted">Size uygun ve güvenilir iş fırsatlarını keşfedin.</p>
        )}
      </header>

      <form onSubmit={handleSearchSubmit} className="space-y-3">
        <div className="relative">
          <input
            name="keyword"
            type="search"
            placeholder="Pozisyon, şirket veya anahtar kelime..."
            value={keywordDraft}
            onChange={(event) => setKeywordDraft(event.target.value)}
            className={cn(
              'h-12 w-full rounded-xl border border-surface bg-white pl-4 pr-12 text-sm text-ink outline-none transition',
              'placeholder:text-ink-subtle focus:border-primary focus:ring-4 focus:ring-primary/10',
            )}
          />
          <button
            type="submit"
            className="absolute right-1.5 top-1/2 inline-flex h-9 w-9 -translate-y-1/2 items-center justify-center rounded-lg text-ink-muted transition hover:bg-background hover:text-primary"
            aria-label="Ara"
          >
            <Search className="h-4 w-4" aria-hidden="true" />
          </button>
        </div>

        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <Button
            type="button"
            variant="outline"
            className="h-10 w-full justify-center sm:w-auto"
            onClick={() => setFiltersOpen(true)}
          >
            <SlidersHorizontal className="h-4 w-4" aria-hidden="true" />
            Filtrele
            {activeFilterCount > 0 ? (
              <span className="rounded-full bg-primary/10 px-2 py-0.5 text-xs font-semibold text-primary">
                {activeFilterCount}
              </span>
            ) : null}
          </Button>

          <JobSort
            value={(queryParams.sort ?? DEFAULT_JOB_SEARCH.sort) as JobSortValue}
            onChange={(sort) => updateParams({ sort, page: 1 })}
            className="sm:justify-end"
          />
        </div>
      </form>

      <JobList
        jobs={data?.items ?? []}
        isLoading={isLoading}
        isError={isError}
        onRetry={() => void refetch()}
        showFitScore={showFitScore}
        savedJobIds={savedJobIds}
        canSave={showFitScore}
      />

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
              onClick={() => updateParams({ page: currentPage - 1 })}
            >
              Önceki
            </Button>
            <Button
              type="button"
              variant="outline"
              size="sm"
              disabled={currentPage >= lastPage}
              onClick={() => updateParams({ page: currentPage + 1 })}
            >
              Sonraki
            </Button>
          </div>
        </div>
      ) : null}

      <JobFiltersDrawer
        open={filtersOpen}
        onClose={() => setFiltersOpen(false)}
        activeCount={activeFilterCount}
        values={queryParams}
        onChange={updateParams}
        onReset={resetFilters}
      />
    </div>
  );
}

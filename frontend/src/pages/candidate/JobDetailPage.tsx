import { useMemo, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { useJob } from '@/hooks/useJob';
import { useCanViewFitScore } from '@/hooks/useCanViewFitScore';
import { Button } from '@/components/ui/Button';
import { EmptyState, Skeleton } from '@/components/ui/States';
import {
  JobDetailBreadcrumb,
  JobDetailContent,
  JobDetailHero,
  JobDetailSidebar,
  JOB_DETAIL_TABS,
  type DetailTab,
} from '@/components/jobs/JobDetailSections';
import { cn } from '@/utils/format';

export function JobDetailPage() {
  const { slug } = useParams<{ slug: string }>();
  const { data: job, isLoading, isError, refetch } = useJob(slug);
  const showFitScore = useCanViewFitScore();
  const [activeTab, setActiveTab] = useState<DetailTab>('detail');

  const tabs = useMemo(
    () => JOB_DETAIL_TABS.filter((tab) => !tab.candidateOnly || showFitScore),
    [showFitScore],
  );

  if (isLoading) {
    return (
      <div className="space-y-6">
        <Skeleton className="h-6 w-56" />
        <Skeleton className="h-44" />
        <Skeleton className="h-10 w-full" />
        <Skeleton className="h-72" />
      </div>
    );
  }

  if (isError || !job) {
    return (
      <EmptyState
        title="İlan yüklenemedi"
        description="İlan bulunamadı veya geçici bir hata oluştu."
        action={
          <div className="flex flex-wrap justify-center gap-3">
            <Button type="button" variant="outline" onClick={() => void refetch()}>
              Tekrar Dene
            </Button>
            <Link to="/jobs">
              <Button type="button">İlanlara Dön</Button>
            </Link>
          </div>
        }
      />
    );
  }

  return (
    <div className="space-y-5">
      <JobDetailBreadcrumb title={job.title} />
      <JobDetailHero job={job} showFitScore={showFitScore} />

      <div className="border-b border-surface">
        <div className="flex gap-1 overflow-x-auto">
          {tabs.map((tab) => (
            <button
              key={tab.id}
              type="button"
              onClick={() => setActiveTab(tab.id)}
              className={cn(
                'shrink-0 border-b-2 px-4 py-3 text-sm font-medium transition',
                activeTab === tab.id
                  ? 'border-primary text-primary'
                  : 'border-transparent text-ink-muted hover:text-ink',
              )}
            >
              {tab.label}
            </button>
          ))}
        </div>
      </div>

      <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_320px]">
        <JobDetailContent job={job} activeTab={activeTab} showFitScore={showFitScore} />
        <JobDetailSidebar
          job={job}
          showFitScore={showFitScore}
          onShowFit={() => setActiveTab('fit')}
          onShowTrust={() => setActiveTab('trust')}
        />
      </div>
    </div>
  );
}

import { JobCard } from '@/components/jobs/JobCard';
import { Button } from '@/components/ui/Button';
import { EmptyState, Skeleton } from '@/components/ui/States';
import type { JobListItem } from '@/types/api';

interface JobListProps {
  jobs: JobListItem[];
  isLoading: boolean;
  isError: boolean;
  onRetry: () => void;
  showFitScore?: boolean;
  savedJobIds?: number[];
  canSave?: boolean;
}

export function JobList({
  jobs,
  isLoading,
  isError,
  onRetry,
  showFitScore = false,
  savedJobIds = [],
  canSave = false,
}: JobListProps) {
  if (isLoading) {
    return (
      <div className="space-y-4">
        <Skeleton className="h-40" />
        <Skeleton className="h-40" />
        <Skeleton className="h-40" />
      </div>
    );
  }

  if (isError) {
    return (
      <EmptyState
        title="İlanlar yüklenirken bir sorun oluştu"
        description="Bağlantınızı kontrol edip tekrar deneyebilirsiniz."
        action={
          <Button type="button" onClick={onRetry}>
            Tekrar Dene
          </Button>
        }
      />
    );
  }

  if (jobs.length === 0) {
    return (
      <EmptyState
        title="Aradığınız kriterlere uygun ilan bulunamadı"
        description="Filtreleri değiştirerek veya arama terimini güncelleyerek tekrar deneyin."
      />
    );
  }

  return (
    <div className="space-y-4">
      {jobs.map((job) => (
        <JobCard
          key={job.id}
          job={job}
          variant="expanded"
          showFitScore={showFitScore}
          isSaved={savedJobIds.includes(job.id)}
          canSave={canSave}
        />
      ))}
    </div>
  );
}

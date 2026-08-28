import { Bookmark } from 'lucide-react';
import { Link, useLocation } from 'react-router-dom';
import { JobCompanyAvatar } from '@/components/jobs/JobCompanyAvatar';
import { JobMetaTag } from '@/components/jobs/JobMetaTag';
import { JobSourceBadge } from '@/components/jobs/JobSourceBadge';
import { FitScoreBadge } from '@/components/jobs/FitScoreBadge';
import { TrustScore } from '@/components/ui/TrustScore';
import { Card, CardBody } from '@/components/ui/Card';
import { formatEmploymentType, formatLocation, formatWorkType, cn } from '@/utils/format';
import { getJobCompanyName, isExternalJob, isVerifiedCompany } from '@/utils/jobSource';
import { useToggleSavedJob } from '@/hooks/useSavedJobs';
import { shouldShowFitScoreBadge } from '@/utils/fitScoreBreakdown';
import type { JobListItem } from '@/types/api';

interface JobCardProps {
  job: JobListItem;
  showFitScore?: boolean;
  variant?: 'default' | 'expanded';
  isSaved?: boolean;
  canSave?: boolean;
}

export function JobCard({
  job,
  showFitScore = true,
  variant = 'default',
  isSaved = false,
  canSave = false,
}: JobCardProps) {
  const isExpanded = variant === 'expanded';
  const companyName = getJobCompanyName(job);
  const locationLabel = formatLocation(job.city, job.country);
  const location = useLocation();
  const toggleSaved = useToggleSavedJob();

  return (
    <Card className="transition hover:border-primary/25 hover:shadow-[0_4px_20px_rgba(15,23,42,0.06)]">
      <CardBody className={cn('p-4', isExpanded && 'sm:p-5')}>
        <div className="flex flex-col gap-4 sm:flex-row sm:items-center">
          <div className="flex min-w-0 flex-1 items-start gap-3 sm:items-center">
            <JobCompanyAvatar name={companyName} size={isExpanded ? 'md' : 'sm'} />

            <div className="min-w-0 flex-1 space-y-2">
              <JobSourceBadge job={job} prominent />

              <div>
                <Link
                  to={`/jobs/${job.slug}`}
                  state={{ jobsListSearch: location.search }}
                  className="line-clamp-2 text-base font-semibold text-ink transition hover:text-primary sm:text-lg"
                >
                  {job.title}
                </Link>
                <p className="text-sm text-ink-muted">{companyName}</p>
                {locationLabel ? (
                  <p className="text-sm text-ink-subtle">{locationLabel}</p>
                ) : null}
              </div>

              <div className="flex flex-wrap items-center gap-2">
                <JobMetaTag>{formatWorkType(job.work_type)}</JobMetaTag>
                <JobMetaTag>{formatEmploymentType(job.employment_type)}</JobMetaTag>
                {isExternalJob(job) ? (
                  <JobMetaTag className="text-primary">İlana Git</JobMetaTag>
                ) : (
                  <JobMetaTag className="text-primary">Başvur</JobMetaTag>
                )}
                {isVerifiedCompany(job) ? (
                  <JobMetaTag className="text-primary">Doğrulanmış şirket</JobMetaTag>
                ) : null}
              </div>
            </div>
          </div>

          <div className="flex items-end justify-between gap-3 border-t border-surface pt-4 sm:justify-end sm:border-t-0 sm:pt-0">
            <div className="flex items-end gap-4 sm:gap-5">
              {shouldShowFitScoreBadge(showFitScore) ? (
                <FitScoreBadge
                  score={job.fit_score}
                  status={job.fit_analysis_status}
                  size={isExpanded ? 'md' : 'sm'}
                />
              ) : null}
              <TrustScore
                score={job.trust_score}
                status={job.trust_analysis_status}
                size={isExpanded ? 'md' : 'sm'}
              />
            </div>

            <button
              type="button"
              disabled={!canSave || toggleSaved.isPending}
              onClick={() => {
                if (!canSave) return;
                toggleSaved.mutate({ jobId: job.id, saved: isSaved });
              }}
              title={canSave ? (isSaved ? 'Kaydedildi' : 'İlanı kaydet') : 'Kaydetmek için giriş yapın'}
              className={cn(
                'inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg transition hover:bg-background disabled:cursor-not-allowed disabled:opacity-50',
                isSaved ? 'text-primary' : 'text-ink-subtle',
              )}
              aria-label={isSaved ? 'Kaydedilen ilandan çıkar' : 'İlanı kaydet'}
            >
              <Bookmark className={cn('h-5 w-5', isSaved && 'fill-current')} aria-hidden="true" />
            </button>
          </div>
        </div>
      </CardBody>
    </Card>
  );
}

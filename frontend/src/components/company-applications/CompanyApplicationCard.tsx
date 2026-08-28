import { ChevronRight } from 'lucide-react';
import { Link } from 'react-router-dom';
import { CompanyApplicationStatusBadge } from '@/components/company-applications/CompanyApplicationStatusBadge';
import { MatchScoreDisplay } from '@/components/company-applications/MatchScoreDisplay';
import { JobCompanyAvatar } from '@/components/jobs/JobCompanyAvatar';
import { Card, CardBody } from '@/components/ui/Card';
import type { CompanyApplication } from '@/types/companyApplication';
import { formatApplicationDate, formatApplicationScore } from '@/utils/applicationStatus';
import { formatLocation } from '@/utils/format';

interface CompanyApplicationCardProps {
  application: CompanyApplication;
}

export function CompanyApplicationCard({ application }: CompanyApplicationCardProps) {
  const candidateName = application.candidate?.user?.name ?? 'Aday';
  const headline = application.candidate?.headline ?? '—';
  const city = formatLocation(
    application.candidate?.city ?? null,
    application.candidate?.country ?? null,
  );

  return (
    <Link to={`/company/applications/${application.id}`} className="block">
      <Card className="transition hover:border-primary/25 hover:shadow-[0_4px_20px_rgba(15,23,42,0.06)]">
        <CardBody className="space-y-4 p-4 sm:p-5">
          <div className="flex items-start justify-between gap-3">
            <div className="flex min-w-0 items-start gap-3">
              <JobCompanyAvatar name={candidateName} size="md" />
              <div className="min-w-0 space-y-1">
                <p className="truncate text-base font-semibold text-ink">{candidateName}</p>
                <p className="truncate text-sm text-ink-muted">{headline}</p>
                <p className="text-sm text-ink-subtle">{city}</p>
              </div>
            </div>
            <ChevronRight className="h-5 w-5 shrink-0 text-ink-subtle" aria-hidden="true" />
          </div>

          <div className="space-y-2 border-t border-surface pt-4">
            <p className="text-sm font-medium text-ink">{application.job?.title ?? 'İlan bilgisi yok'}</p>
            <div className="flex flex-wrap items-center gap-3 text-sm">
              <CompanyApplicationStatusBadge status={application.status} />
              <span className="text-ink-muted">
                Uyum:{' '}
                <MatchScoreDisplay
                  score={application.match_score}
                  status={application.match_analysis_status}
                  variant="inline"
                />
              </span>
              <span className="text-ink-muted">Güven: {formatApplicationScore(application.trust_score)}</span>
            </div>
            <p className="text-xs text-ink-subtle">
              Başvuru: {formatApplicationDate(application.applied_at)}
            </p>
          </div>
        </CardBody>
      </Card>
    </Link>
  );
}

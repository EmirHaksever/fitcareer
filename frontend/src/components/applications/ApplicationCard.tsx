import { Link } from 'react-router-dom';
import { ApplicationStatusBadge } from '@/components/applications/ApplicationStatusBadge';
import { JobCompanyAvatar } from '@/components/jobs/JobCompanyAvatar';
import { Button } from '@/components/ui/Button';
import { Card, CardBody } from '@/components/ui/Card';
import type { Application } from '@/types/application';
import {
  formatApplicationDate,
  formatApplicationScore,
} from '@/utils/applicationStatus';
import { formatLocation } from '@/utils/format';

interface ApplicationCardProps {
  application: Application;
}

export function ApplicationCard({ application }: ApplicationCardProps) {
  const job = application.job;
  const companyName = job?.company?.name ?? 'Şirket bilgisi yok';
  const location = formatLocation(job?.city ?? null, job?.country ?? null);

  return (
    <Card className="transition hover:border-primary/25 hover:shadow-[0_4px_20px_rgba(15,23,42,0.06)]">
      <CardBody className="p-4 sm:p-5">
        <div className="flex flex-col gap-4 lg:grid lg:grid-cols-[minmax(0,1fr)_auto_auto_auto] lg:items-center lg:gap-6">
          <div className="flex min-w-0 items-start gap-3">
            <JobCompanyAvatar name={companyName} size="md" />
            <div className="min-w-0 space-y-1">
              <p className="line-clamp-2 text-base font-semibold text-ink sm:text-lg">
                {job?.title ?? 'İlan bilgisi yok'}
              </p>
              <p className="text-sm text-ink-muted">{companyName}</p>
              <p className="text-sm text-ink-subtle">{location}</p>
              <p className="text-xs text-ink-subtle lg:hidden">
                Başvuru: {formatApplicationDate(application.applied_at)}
              </p>
            </div>
          </div>

          <div className="flex flex-wrap items-center gap-3 lg:justify-center">
            <ApplicationStatusBadge status={application.status} />
            <p className="hidden text-sm text-ink-muted lg:block">
              {formatApplicationDate(application.applied_at)}
            </p>
          </div>

          <div className="grid grid-cols-2 gap-3 text-sm sm:flex sm:items-center sm:gap-6">
            <div>
              <p className="text-xs text-ink-subtle">Uyum</p>
              <p className="font-medium text-ink">{formatApplicationScore(application.match_score)}</p>
            </div>
            <div>
              <p className="text-xs text-ink-subtle">Güven</p>
              <p className="font-medium text-ink">{formatApplicationScore(application.trust_score)}</p>
            </div>
          </div>

          <div className="flex flex-col gap-2 sm:flex-row lg:justify-end">
            {job?.slug ? (
              <Link to={`/jobs/${job.slug}`} className="w-full sm:w-auto">
                <Button type="button" variant="outline" className="w-full">
                  İlanı Gör
                </Button>
              </Link>
            ) : null}
            <Link to={`/applications/${application.id}`} className="w-full sm:w-auto">
              <Button type="button" className="w-full">
                Detay
              </Button>
            </Link>
          </div>
        </div>
      </CardBody>
    </Card>
  );
}

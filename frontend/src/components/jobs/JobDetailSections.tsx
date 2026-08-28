import { CheckCircle2, ChevronRight } from 'lucide-react';
import { useState } from 'react';
import { Link, useLocation } from 'react-router-dom';
import { ApplyJobModal } from '@/components/applications/ApplyJobModal';
import { FitScoreBreakdown } from '@/components/jobs/FitScoreBreakdown';
import { FitScoreCard } from '@/components/jobs/FitScoreCard';
import { JobCompanyAvatar } from '@/components/jobs/JobCompanyAvatar';
import { JobDescriptionContent } from '@/components/jobs/JobDescriptionContent';
import { JobMetaTag } from '@/components/jobs/JobMetaTag';
import { JobSourceBadge } from '@/components/jobs/JobSourceBadge';
import { ScoreSummaryCard } from '@/components/jobs/ScoreSummaryCard';
import { TrustExplanationPanel } from '@/components/jobs/TrustExplanationPanel';
import { Button } from '@/components/ui/Button';
import { Card, CardBody } from '@/components/ui/Card';
import type { JobDetail } from '@/types/api';
import { isLikelyEnglishText } from '@/utils/detectTextLanguage';
import {
  formatEmploymentType,
  formatExperienceLevel,
  formatLocation,
  formatRelativeTime,
  formatSalary,
  formatTrustLabel,
  formatWorkType,
} from '@/utils/format';
import { isFitPending, isTrustPending } from '@/utils/scores';
import { useAuth } from '@/hooks/useAuth';
import {
  formatJobSourceLabel,
  getExternalJobUrl,
  getJobCompanyName,
  isExternalJob,
  isInternalJob,
  isVerifiedCompany,
  openExternalJobUrl,
} from '@/utils/jobSource';

type DetailTab = 'detail' | 'company' | 'fit' | 'trust';

interface JobDetailContentProps {
  job: JobDetail;
  activeTab: DetailTab;
  showFitScore: boolean;
}

function InsightCard({
  title,
  items,
  actionLabel,
  onAction,
}: {
  title: string;
  items: string[];
  actionLabel: string;
  onAction: () => void;
}) {
  return (
    <Card>
      <CardBody className="space-y-4">
        <h3 className="text-base font-semibold text-ink">{title}</h3>
        <ul className="space-y-3">
          {items.map((item) => (
            <li key={item} className="flex items-start gap-2.5 text-sm text-ink-muted">
              <CheckCircle2 className="mt-0.5 h-4 w-4 shrink-0 text-primary" aria-hidden="true" />
              <span>{item}</span>
            </li>
          ))}
        </ul>
        <button
          type="button"
          onClick={onAction}
          className="inline-flex text-sm font-medium text-primary hover:underline"
        >
          {actionLabel} →
        </button>
      </CardBody>
    </Card>
  );
}

function buildFitInsights(job: JobDetail, showFitScore: boolean): string[] {
  if (!showFitScore) {
    return [];
  }

  const items: string[] = [];

  if (isFitPending(job.fit_analysis_status)) {
    items.push('Uyum analizi devam ediyor.');
  } else if (job.fit_score !== null) {
    items.push(`Uyum skorunuz bu ilan için %${job.fit_score} olarak hesaplandı.`);
  }

  if (job.skills && job.skills.length > 0) {
    items.push(`İlan ${job.skills.length} yetenek gerektiriyor.`);
  }

  if (job.experience_level) {
    items.push(`Deneyim seviyesi: ${formatExperienceLevel(job.experience_level)}.`);
  }

  if (items.length === 0) {
    items.push('Uyum analizi henüz tamamlanmadı.');
  }

  return items;
}

function buildTrustInsights(job: JobDetail): string[] {
  const items: string[] = [];

  if (isExternalJob(job)) {
    items.push(`Kaynak: ${formatJobSourceLabel(job)}.`);
  } else if (isInternalJob(job)) {
    items.push('Doğrudan işveren ilanı.');
  }

  if (isVerifiedCompany(job)) {
    items.push('Doğrulanmış şirket.');
  } else if (isExternalJob(job)) {
    items.push('Dış kaynak ilanı; şirket doğrulaması FitCareer tarafından yapılmaz.');
  }

  if (isTrustPending(job.trust_analysis_status)) {
    items.push('Güvenilirlik analizi devam ediyor.');
  } else if (job.trust_score !== null) {
    items.push(`Güven skoru: %${job.trust_score} (${formatTrustLabel(job.trust_label)}).`);
  }

  if (job.published_at) {
    items.push(`Yayın tarihi: ${formatRelativeTime(job.published_at) ?? 'Kayıtlı'}.`);
  }

  if (items.length === 0) {
    items.push('Güvenilirlik analizi henüz tamamlanmadı.');
  }

  return items;
}

export function JobDetailContent({ job, activeTab, showFitScore }: JobDetailContentProps) {
  const companyName = getJobCompanyName(job);

  if (activeTab === 'company') {
    return (
      <Card>
        <CardBody className="space-y-4">
          <h2 className="text-lg font-semibold text-ink">Şirket Hakkında</h2>
          <div className="flex items-center gap-3">
            <JobCompanyAvatar name={companyName} size="lg" />
            <div className="space-y-1">
              <p className="text-base font-semibold text-ink">{companyName}</p>
              <JobSourceBadge job={job} prominent />
            </div>
          </div>

          {isExternalJob(job) ? (
            <div className="space-y-2 text-sm text-ink-muted">
              <p>Bu şirket bilgisi dış kaynaktan alınmıştır.</p>
              <p>{formatLocation(job.city, job.country)}</p>
              {getExternalJobUrl(job) ? (
                <button
                  type="button"
                  onClick={() => openExternalJobUrl(getExternalJobUrl(job)!)}
                  className="inline-flex font-medium text-primary hover:underline"
                >
                  Orijinal ilanı görüntüle
                </button>
              ) : null}
            </div>
          ) : (
            <div className="space-y-2 text-sm text-ink-muted">
              <p>Doğrudan işveren ilanı.</p>
              {isVerifiedCompany(job) ? (
                <p className="text-primary">Doğrulanmış şirket</p>
              ) : (
                <p>Bu şirket henüz FitCareer üzerinde doğrulanmadı.</p>
              )}
            </div>
          )}
        </CardBody>
      </Card>
    );
  }

  if (activeTab === 'fit') {
    if (!showFitScore) {
      return null;
    }

    return (
      <Card>
        <CardBody className="space-y-5">
          <h2 className="text-lg font-semibold text-ink">Uyum Analizi</h2>
          <div className="flex justify-center sm:justify-start">
            <FitScoreCard score={job.fit_score} status={job.fit_analysis_status} />
          </div>
          <ul className="space-y-3">
            {buildFitInsights(job, showFitScore).map((item) => (
              <li key={item} className="flex items-start gap-2.5 text-sm text-ink-muted">
                <CheckCircle2 className="mt-0.5 h-4 w-4 shrink-0 text-secondary" aria-hidden="true" />
                <span>{item}</span>
              </li>
            ))}
          </ul>
          <FitScoreBreakdown details={job.fit_details} />
        </CardBody>
      </Card>
    );
  }

  if (activeTab === 'trust') {
    return (
      <Card>
        <CardBody className="space-y-5">
          <h2 className="text-lg font-semibold text-ink">Güvenilirlik Analizi</h2>
          <div className="flex justify-center sm:justify-start">
            <ScoreSummaryCard type="trust" score={job.trust_score} status={job.trust_analysis_status} />
          </div>

          <TrustExplanationPanel job={job} />

          <ul className="space-y-3">
            {buildTrustInsights(job)
              .filter((item) => !item.includes('Güven skoru:'))
              .map((item) => (
              <li key={item} className="flex items-start gap-2.5 text-sm text-ink-muted">
                <CheckCircle2 className="mt-0.5 h-4 w-4 shrink-0 text-primary" aria-hidden="true" />
                <span>{item}</span>
              </li>
            ))}
          </ul>
        </CardBody>
      </Card>
    );
  }

  return (
    <Card>
      <CardBody className="space-y-8">
        <section className="space-y-3">
          <h2 className="text-lg font-semibold text-ink">İş Tanımı</h2>
          {isLikelyEnglishText(job.description) ? (
            <p className="rounded-lg border border-surface bg-background px-3 py-2 text-xs text-ink-muted">
              İlan açıklaması kaynak tarafından İngilizce sağlanmıştır. Metin orijinal haliyle
              gösterilmektedir.
            </p>
          ) : null}
          <JobDescriptionContent content={job.description} />
        </section>

        {job.responsibilities ? (
          <section className="space-y-3">
            <h2 className="text-lg font-semibold text-ink">Sorumluluklar</h2>
            <JobDescriptionContent content={job.responsibilities} />
          </section>
        ) : null}

        {job.requirements ? (
          <section className="space-y-3">
            <h2 className="text-lg font-semibold text-ink">Aranan Nitelikler</h2>
            <JobDescriptionContent content={job.requirements} />
          </section>
        ) : null}
      </CardBody>
    </Card>
  );
}

export function JobDetailSidebar({
  job,
  onShowFit,
  onShowTrust,
  showFitScore,
}: {
  job: JobDetail;
  onShowFit: () => void;
  onShowTrust: () => void;
  showFitScore: boolean;
}) {
  const [applyOpen, setApplyOpen] = useState(false);
  const { user } = useAuth();
  const canApply = user?.role === 'candidate';
  const fitInsights = buildFitInsights(job, showFitScore);
  const externalUrl = getExternalJobUrl(job);
  const externalJob = isExternalJob(job);

  return (
    <aside className="space-y-4">
      {showFitScore && fitInsights.length > 0 ? (
        <InsightCard
          title="Neden Sana Uygun?"
          items={fitInsights}
          actionLabel="Detaylı Uyum Analizini Gör"
          onAction={onShowFit}
        />
      ) : null}
      <InsightCard
        title="Güvenilirlik Analizi"
        items={buildTrustInsights(job)}
        actionLabel="Güvenilirlik Detaylarını Gör"
        onAction={onShowTrust}
      />

      {canApply || (externalJob && externalUrl) ? (
        <Card>
          <CardBody className="space-y-3">
            {externalJob && externalUrl ? (
              <Button className="w-full" onClick={() => openExternalJobUrl(externalUrl)}>
                İlana Git
              </Button>
            ) : (
              <Button className="w-full" onClick={() => setApplyOpen(true)}>
                Başvur
              </Button>
            )}
            <p className="text-center text-xs text-ink-subtle">
              {externalJob
                ? `${formatJobSourceLabel(job)} üzerindeki orijinal ilana yönlendirileceksiniz.`
                : 'Başvurunu göndermeden önce profilini güncel tutmanı öneririz.'}
            </p>
          </CardBody>
        </Card>
      ) : null}

      {canApply && !externalJob ? (
        <ApplyJobModal
          jobId={job.id}
          jobTitle={job.title}
          companyName={getJobCompanyName(job)}
          open={applyOpen}
          onClose={() => setApplyOpen(false)}
        />
      ) : null}
    </aside>
  );
}

export function JobDetailHero({
  job,
  showFitScore,
}: {
  job: JobDetail;
  showFitScore: boolean;
}) {
  const salary = formatSalary(job.salary_min, job.salary_max, job.salary_currency, job.is_salary_visible);
  const publishedLabel = formatRelativeTime(job.published_at);
  const companyName = getJobCompanyName(job);

  return (
    <Card>
      <CardBody className="space-y-5">
        <div className="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
          <div className="space-y-4">
            <div className="flex items-start gap-4">
              <JobCompanyAvatar name={companyName} size="lg" className="rounded-xl" />
              <div className="space-y-1">
                <h1 className="text-2xl font-bold tracking-tight text-ink sm:text-3xl">{job.title}</h1>
                <p className="text-base text-ink-muted">{companyName}</p>
              </div>
            </div>

            <div className="flex flex-wrap items-center gap-2">
              <JobSourceBadge job={job} prominent />
              {isVerifiedCompany(job) ? (
                <JobMetaTag className="text-primary">Doğrulanmış şirket</JobMetaTag>
              ) : null}
              <JobMetaTag>{formatWorkType(job.work_type)}</JobMetaTag>
              <JobMetaTag>{formatEmploymentType(job.employment_type)}</JobMetaTag>
              <JobMetaTag>{formatLocation(job.city, job.country)}</JobMetaTag>
              {salary ? <JobMetaTag>{salary}</JobMetaTag> : null}
            </div>

            {publishedLabel ? <p className="text-sm text-ink-muted">{publishedLabel}</p> : null}
          </div>

          <div className="flex w-full gap-3 lg:w-auto lg:min-w-[320px]">
            {showFitScore ? (
              <FitScoreCard score={job.fit_score} status={job.fit_analysis_status} />
            ) : null}
            <ScoreSummaryCard type="trust" score={job.trust_score} status={job.trust_analysis_status} />
          </div>
        </div>
      </CardBody>
    </Card>
  );
}

export function JobDetailBreadcrumb({ title }: { title: string }) {
  const location = useLocation();
  const { user } = useAuth();
  const jobsListSearch =
    (location.state as { jobsListSearch?: string } | null)?.jobsListSearch ?? '';
  const jobsHref = user?.role === 'company' ? '/company/jobs' : `/jobs${jobsListSearch}`;
  const jobsLabel = user?.role === 'company' ? 'İlanlarım' : 'İş İlanları';

  return (
    <nav className="flex items-center gap-1.5 text-sm text-ink-muted" aria-label="Breadcrumb">
      <Link to={jobsHref} className="font-medium transition hover:text-primary">
        {jobsLabel}
      </Link>
      <ChevronRight className="h-4 w-4" aria-hidden="true" />
      <span className="truncate font-medium text-ink">{title}</span>
    </nav>
  );
}

export type { DetailTab };

export const JOB_DETAIL_TABS: { id: DetailTab; label: string; candidateOnly?: boolean }[] = [
  { id: 'detail', label: 'İlan Detayı' },
  { id: 'company', label: 'Şirket Hakkında' },
  { id: 'fit', label: 'Uyum Analizi', candidateOnly: true },
  { id: 'trust', label: 'Güvenilirlik Analizi' },
];

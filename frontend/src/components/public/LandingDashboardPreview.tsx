import { ArrowUpRight, Database, ShieldCheck, Sparkles } from 'lucide-react';
import { Link } from 'react-router-dom';
import { useJobs } from '@/hooks/useJobs';
import { usePublicStats } from '@/hooks/usePublicStats';
import { JobSourceBadge } from '@/components/jobs/JobSourceBadge';
import { JobCompanyAvatar } from '@/components/jobs/JobCompanyAvatar';
import { TrustScore } from '@/components/ui/TrustScore';
import { Skeleton } from '@/components/ui/States';
import { getJobCompanyName } from '@/utils/jobSource';
import type { JobListItem } from '@/types/api';

function PreviewJob({ job }: { job: JobListItem }) {
  return (
    <div className="flex items-center gap-3 rounded-2xl border border-[#E2E8F0] bg-white px-3 py-3">
      <JobCompanyAvatar name={getJobCompanyName(job)} size="sm" />
      <div className="min-w-0 flex-1">
        <p className="truncate text-sm font-semibold text-[#0F172A]">{job.title}</p>
        <p className="truncate text-xs text-[#64748B]">{getJobCompanyName(job)}</p>
        <div className="mt-2 flex flex-wrap items-center gap-2">
          <JobSourceBadge job={job} />
          {job.work_type ? (
            <span className="rounded-full bg-[#F1F5F9] px-2 py-1 text-[11px] font-medium text-[#64748B]">
              {job.work_type === 'remote' ? 'Uzaktan' : job.work_type === 'hybrid' ? 'Hibrit' : 'Ofisten'}
            </span>
          ) : null}
        </div>
      </div>
      <TrustScore score={job.trust_score} status={job.trust_analysis_status} size="sm" />
    </div>
  );
}

export function LandingDashboardPreview() {
  const { data, isLoading, isError } = useJobs({
    per_page: 4,
    sort: 'published_at',
  });

  const { data: stats } = usePublicStats();

  const jobs = data?.items ?? [];

  return (
    <div className="bg-[#F8FAFC] p-4 sm:p-6">
      <div className="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <div className="flex items-center gap-2">
            <span className="inline-flex h-8 w-8 items-center justify-center rounded-xl bg-[#D1FAE5] text-primary">
              <Sparkles className="h-4 w-4" aria-hidden="true" />
            </span>
            <p className="text-sm font-semibold text-[#0F172A]">Canlı ilan önizlemesi</p>
          </div>
          <p className="mt-1 text-xs text-[#64748B]">En yeni ilanlar; her biri Trust Score ile değerlendirildi.</p>
        </div>
        <Link to="/register" className="inline-flex items-center gap-1 text-xs font-semibold text-primary hover:underline">
          Kendi panelini aç
          <ArrowUpRight className="h-3.5 w-3.5" aria-hidden="true" />
        </Link>
      </div>

      <div className="mb-5 grid gap-3 sm:grid-cols-3">
        <div className="rounded-2xl border border-[#E2E8F0] bg-white px-4 py-3">
          <p className="text-xs text-[#64748B]">Yayındaki ilan</p>
          <p className="mt-1 text-xl font-bold text-[#0F172A]">{stats?.published_jobs ?? '—'}</p>
        </div>
        <div className="rounded-2xl border border-[#E2E8F0] bg-white px-4 py-3">
          <p className="text-xs text-[#64748B]">Güven analizi</p>
          <p className="mt-1 text-xl font-bold text-[#0F172A]">{stats ? `${stats.trust_analyzed_jobs}/${stats.published_jobs}` : '—'}</p>
        </div>
        <div className="rounded-2xl border border-[#E2E8F0] bg-white px-4 py-3">
          <p className="text-xs text-[#64748B]">Aktif kaynak</p>
          <p className="mt-1 text-xl font-bold text-[#0F172A]">{stats?.active_sources ?? '—'}</p>
        </div>
      </div>

      <div className="grid gap-4 lg:grid-cols-[1fr_220px]">
        <div className="space-y-2">
          {isLoading ? (
            <>
              <Skeleton className="h-24 rounded-2xl" />
              <Skeleton className="h-24 rounded-2xl" />
              <Skeleton className="h-24 rounded-2xl" />
            </>
          ) : isError || jobs.length === 0 ? (
            <div className="rounded-2xl border border-dashed border-[#CBD5E1] bg-white px-4 py-8 text-center text-sm text-[#64748B]">
              Canlı ilan verisi şu an yenileniyor.
            </div>
          ) : (
            jobs.slice(0, 3).map((job) => <PreviewJob key={job.id} job={job} />)
          )}
        </div>

        <div className="rounded-2xl border border-primary/15 bg-primary/5 p-4">
          <div className="flex items-center gap-2 text-primary">
            <ShieldCheck className="h-4 w-4" aria-hidden="true" />
            <p className="text-sm font-semibold">İlan güveni</p>
          </div>
          <p className="mt-3 text-sm leading-6 text-[#475569]">
            Kaynak, güncellik, şirket ve içerik sinyalleri tek bir güven skorunda birleşir.
          </p>
          <div className="mt-5 flex items-center gap-2 text-xs text-[#64748B]">
            <Database className="h-3.5 w-3.5" aria-hidden="true" />
            <span>Kaynak adı ve güncellik takip edilir</span>
          </div>
        </div>
      </div>
    </div>
  );
}

import { Activity, AlertTriangle, CheckCircle2, Clock3, Database, XCircle } from 'lucide-react';
import { Card, CardBody, CardHeader } from '@/components/ui/Card';
import { EmptyState, Skeleton } from '@/components/ui/States';
import { Button } from '@/components/ui/Button';
import { useAdminSourceHealth } from '@/hooks/useAdminSources';
import type { AdminJobSourceHealth, AdminSourceHealthStatus } from '@/types/adminSource';
import { formatApplicationDateTime } from '@/utils/applicationStatus';

const STATUS_LABELS: Record<AdminSourceHealthStatus, string> = {
  healthy: 'Sağlıklı',
  degraded: 'Kısmi başarı',
  pending: 'İlk senkron bekleniyor',
  stale: 'Güncelleme gecikti',
  error: 'Hata',
  inactive: 'Pasif',
};

const STATUS_CLASSES: Record<AdminSourceHealthStatus, string> = {
  healthy: 'border-success/30 bg-success/10 text-primary-800',
  degraded: 'border-warning/30 bg-amber-50 text-amber-700',
  pending: 'border-primary/20 bg-primary/5 text-primary',
  stale: 'border-warning/30 bg-amber-50 text-amber-700',
  error: 'border-danger/20 bg-danger/5 text-danger',
  inactive: 'border-surface bg-background text-ink-muted',
};

function StatusIcon({ status }: { status: AdminSourceHealthStatus }) {
  if (status === 'healthy') return <CheckCircle2 className="h-4 w-4" aria-hidden="true" />;
  if (status === 'error') return <XCircle className="h-4 w-4" aria-hidden="true" />;
  if (status === 'stale' || status === 'degraded') return <AlertTriangle className="h-4 w-4" aria-hidden="true" />;
  return <Clock3 className="h-4 w-4" aria-hidden="true" />;
}

function SourceHealthRow({ source }: { source: AdminJobSourceHealth }) {
  return (
    <div data-testid="source-health-row" className="grid gap-4 border-b border-surface px-5 py-4 last:border-b-0 lg:grid-cols-[1.3fr_0.8fr_0.8fr_1fr_1fr] lg:items-center">
      <div className="min-w-0">
        <p className="truncate font-semibold text-ink">{source.source_name}</p>
        <p className="mt-1 truncate text-xs text-ink-subtle">
          {source.provider ?? 'Sağlayıcı belirtilmemiş'}{source.base_url ? ` · ${source.base_url}` : ''}
        </p>
      </div>
      <span className={`inline-flex w-fit items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium ${STATUS_CLASSES[source.health_status]}`}>
        <StatusIcon status={source.health_status} />
        {STATUS_LABELS[source.health_status]}
      </span>
      <div className="text-sm text-ink-muted">
        <p className="font-medium text-ink">{(source.active_published_jobs_count ?? 0).toLocaleString('tr-TR')} ilan</p>
        <p className="text-xs">{source.consecutive_failures ?? 0} ardışık hata</p>
      </div>
      <div className="text-sm text-ink-muted">
        <p>Son başarılı: {formatApplicationDateTime(source.last_success_at)}</p>
        <p className="text-xs">Son çalışma: {formatApplicationDateTime(source.last_run_at)}</p>
      </div>
      <div className="text-sm text-ink-muted">
        <p>Bulunan: {(source.last_items_found ?? 0).toLocaleString('tr-TR')}</p>
        <p className="text-xs">Yeni {source.last_items_created ?? 0} · Güncellenen {source.last_items_updated ?? 0}</p>
        {source.last_error ? <p className="mt-1 line-clamp-2 text-xs text-danger">{source.last_error}</p> : null}
      </div>
    </div>
  );
}

export function AdminSourceHealthPage() {
  const { data, isLoading, isError, refetch } = useAdminSourceHealth();
  const sources = data?.items ?? [];

  return (
    <div className="space-y-6">
      <section className="space-y-2">
        <p className="text-sm font-medium text-primary">Admin</p>
        <h1 className="text-3xl font-bold tracking-tight text-ink">İlan Kaynakları</h1>
        <p className="max-w-2xl text-sm text-ink-muted">
          Greenhouse, Lever, Ashby, Workable, Recruitee ve diğer kaynakların senkronizasyon durumunu izle.
        </p>
      </section>

      {isLoading ? (
        <div className="grid gap-4 md:grid-cols-4">
          <Skeleton className="h-24" />
          <Skeleton className="h-24" />
          <Skeleton className="h-24" />
          <Skeleton className="h-24" />
        </div>
      ) : null}

      {!isLoading && !isError && data ? (
        <div className="grid gap-4 md:grid-cols-4">
          <Card><CardBody><p className="text-sm text-ink-muted">Toplam kaynak</p><p className="mt-1 text-3xl font-bold text-ink">{data.summary.total}</p></CardBody></Card>
          <Card><CardBody><p className="text-sm text-ink-muted">Aktif</p><p className="mt-1 text-3xl font-bold text-primary">{data.summary.active}</p></CardBody></Card>
          <Card><CardBody><p className="text-sm text-ink-muted">Sağlıklı</p><p className="mt-1 text-3xl font-bold text-primary">{data.summary.healthy}</p></CardBody></Card>
          <Card><CardBody><p className="text-sm text-ink-muted">Dikkat gereken</p><p className="mt-1 text-3xl font-bold text-amber-700">{data.summary.attention}</p></CardBody></Card>
        </div>
      ) : null}

      {isError ? (
        <EmptyState
          title="Kaynak sağlık verisi yüklenemedi"
          description="Admin kaynak listesi getirilemedi."
          action={<Button type="button" onClick={() => void refetch()}>Tekrar Dene</Button>}
        />
      ) : null}

      {!isLoading && !isError && sources.length === 0 ? (
        <EmptyState title="Henüz kaynak tanımlanmamış" description="İlan kaynakları eklendiğinde sağlık durumları burada görünür." />
      ) : null}

      {!isLoading && !isError && sources.length > 0 ? (
        <Card>
          <CardHeader className="flex items-center gap-2">
            <Activity className="h-4 w-4 text-primary" aria-hidden="true" />
            <div>
              <h2 className="text-lg font-semibold text-ink">Kaynak sağlık durumu</h2>
              <p className="mt-1 text-sm text-ink-muted">{data?.summary.total} kaynak · 6 saatte bir yenileme hedefi</p>
            </div>
          </CardHeader>
          <CardBody className="p-0">
            <div className="hidden border-b border-surface bg-background px-5 py-3 text-xs font-medium uppercase tracking-wide text-ink-subtle lg:grid lg:grid-cols-[1.3fr_0.8fr_0.8fr_1fr_1fr] lg:gap-4">
              <span>Kaynak</span><span>Durum</span><span>İlan havuzu</span><span>Çalışma zamanı</span><span>Sonuç</span>
            </div>
            {sources.map((source) => <SourceHealthRow key={source.source_id} source={source} />)}
          </CardBody>
        </Card>
      ) : null}

      <div className="flex items-center gap-2 text-xs text-ink-subtle">
        <Database className="h-3.5 w-3.5" aria-hidden="true" />
        <span>Duplicate kontrolü external_id + içerik hash üzerinden yapılır; süresi geçen ilanlar lifecycle servisiyle kapatılır.</span>
      </div>
    </div>
  );
}

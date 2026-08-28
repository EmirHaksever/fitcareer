import type { DashboardData, DashboardStat } from '@/types/api';

export function mapDashboardStats(data: DashboardData): DashboardStat[] {
  const { stats } = data;
  const total = stats.total_jobs;

  return [
    {
      id: 'trusted',
      label: 'Güvenilir İlanlar',
      value: stats.trusted_jobs.toLocaleString('tr-TR'),
      helper: total > 0 ? `Toplam ${total.toLocaleString('tr-TR')} ilanın içinde` : 'Henüz ilan yok',
      tone: 'primary',
    },
    {
      id: 'suspicious',
      label: 'Şüpheli İlanlar',
      value: stats.suspicious_jobs.toLocaleString('tr-TR'),
      helper:
        stats.suspicious_jobs > 0 ? 'Dikkatle incelemen önerilir' : 'Şüpheli ilan tespit edilmedi',
      tone: 'warning',
    },
    {
      id: 'applications',
      label: 'Başvurduğun İlanlar',
      value: stats.application_count.toLocaleString('tr-TR'),
      helper: stats.application_count > 0 ? 'Başvurularını takip et' : 'Henüz başvuru yok',
      tone: 'neutral',
    },
    {
      id: 'avg-fit',
      label: 'Ortalama Uyum Skoru',
      value: stats.average_fit_score !== null ? `%${stats.average_fit_score}` : '—',
      helper:
        stats.analyzed_job_count > 0
          ? `${stats.analyzed_job_count} analiz edilmiş ilan üzerinden`
          : 'CV profilini tamamla ve ilanları incele',
      tone: 'success',
    },
  ];
}

const bucketColors: Record<string, string> = {
  verified: 'bg-secondary',
  unrated: 'bg-primary',
  suspicious: 'bg-warning',
  low_trust: 'bg-danger',
  pending_analysis: 'bg-surface',
};

export function colorClassForTrustBucket(id: string): string {
  return bucketColors[id] ?? 'bg-surface';
}

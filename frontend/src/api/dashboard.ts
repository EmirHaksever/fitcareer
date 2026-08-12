import type { DashboardStat } from '@/types/api';

/**
 * TODO(mock): Backend does not expose a dashboard stats endpoint yet.
 * Replace with real API adapter when available.
 */
export function getDashboardStatsPlaceholder(): DashboardStat[] {
  return [
    {
      id: 'trusted',
      label: 'Güvenilir İlanlar',
      value: '—',
      helper: 'Dashboard endpoint bekleniyor',
      tone: 'primary',
    },
    {
      id: 'suspicious',
      label: 'Şüpheli İlanlar',
      value: '—',
      helper: 'Dashboard endpoint bekleniyor',
      tone: 'warning',
    },
    {
      id: 'applications',
      label: 'Başvurduğun İlanlar',
      value: '—',
      helper: 'Applications modülü bekleniyor',
      tone: 'neutral',
    },
    {
      id: 'avg-fit',
      label: 'Ortalama Uyum Skoru',
      value: '—',
      helper: 'Fit analysis endpoint bekleniyor',
      tone: 'success',
    },
  ];
}

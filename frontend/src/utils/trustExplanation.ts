import type { JobDetail } from '@/types/api';
import { formatJobSourceLabel, isExternalJob, isInternalJob, isVerifiedCompany } from '@/utils/jobSource';

export type TrustFactorStatus = 'supported' | 'unsupported' | 'neutral';

export interface TrustFactor {
  id: string;
  label: string;
  status: TrustFactorStatus;
}

export function buildTrustFactors(job: JobDetail): TrustFactor[] {
  const factors: TrustFactor[] = [];

  if (isExternalJob(job)) {
    factors.push({
      id: 'known_source',
      label: `İlan bilinen bir kaynaktan alındı (${formatJobSourceLabel(job)}).`,
      status: 'supported',
    });
  } else if (isInternalJob(job)) {
    factors.push({
      id: 'direct_employer',
      label: 'Doğrudan işveren ilanı.',
      status: 'supported',
    });
  }

  if (isVerifiedCompany(job)) {
    factors.push({
      id: 'verified_company',
      label: 'Şirket FitCareer üzerinde doğrulanmış.',
      status: 'supported',
    });
  }

  if (job.published_at) {
    const publishedAt = new Date(job.published_at);
    const days = Math.floor((Date.now() - publishedAt.getTime()) / (1000 * 60 * 60 * 24));

    if (days <= 30) {
      factors.push({
        id: 'recent_publication',
        label: 'İlan yakın zamanda yayımlandı.',
        status: 'supported',
      });
    } else if (days <= 90) {
      factors.push({
        id: 'moderate_publication',
        label: 'İlan son birkaç ay içinde yayımlanmış.',
        status: 'neutral',
      });
    } else {
      factors.push({
        id: 'older_publication',
        label: 'İlan uzun süredir yayımda; güncelliği ayrıca kontrol edin.',
        status: 'neutral',
      });
    }
  }

  if (job.description && job.description.trim().length >= 100) {
    factors.push({
      id: 'content_complete',
      label: 'İlan açıklaması yeterli detay içeriyor.',
      status: 'supported',
    });
  } else {
    factors.push({
      id: 'content_complete',
      label: 'İlan açıklaması sınırlı; ek doğrulama faydalı olabilir.',
      status: 'unsupported',
    });
  }

  if (isExternalJob(job) && !isVerifiedCompany(job)) {
    factors.push({
      id: 'company_verification',
      label: 'Şirket doğrulaması için yeterli platform içi veri yok.',
      status: 'unsupported',
    });
  }

  return factors;
}

export const TRUST_SCORE_DISCLAIMER =
  'FitCareer Güven Skoru, ilanın kaynak, güncellik ve mevcut veri sinyallerine göre yapılan bir değerlendirmedir. Şirketin veya işverenin kesin olarak güvenilir olduğunu garanti etmez.';

import { Badge } from '@/components/ui/Badge';
import { getApplicationStatusLabel, getApplicationStatusTone } from '@/utils/applicationStatus';
import type { ApplicationStatus } from '@/types/application';

interface CompanyApplicationStatusBadgeProps {
  status: ApplicationStatus;
}

export function CompanyApplicationStatusBadge({ status }: CompanyApplicationStatusBadgeProps) {
  return (
    <Badge tone={getApplicationStatusTone(status)}>{getApplicationStatusLabel(status)}</Badge>
  );
}

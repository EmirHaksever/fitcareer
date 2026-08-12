import { Badge } from '@/components/ui/Badge';
import { getApplicationStatusLabel, getApplicationStatusTone } from '@/utils/applicationStatus';
import type { ApplicationStatus } from '@/types/application';

interface ApplicationStatusBadgeProps {
  status: ApplicationStatus;
}

export function ApplicationStatusBadge({ status }: ApplicationStatusBadgeProps) {
  return (
    <Badge tone={getApplicationStatusTone(status)}>{getApplicationStatusLabel(status)}</Badge>
  );
}

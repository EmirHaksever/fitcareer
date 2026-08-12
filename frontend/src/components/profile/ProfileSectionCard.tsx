import type { ReactNode } from 'react';
import { Card, CardBody, CardHeader } from '@/components/ui/Card';
import { cn } from '@/utils/format';

interface ProfileSectionCardProps {
  title: string;
  action?: ReactNode;
  children: ReactNode;
  className?: string;
}

export function ProfileSectionCard({ title, action, children, className }: ProfileSectionCardProps) {
  return (
    <Card className={cn(className)}>
      <CardHeader className="flex items-center justify-between gap-3">
        <h2 className="text-base font-semibold text-ink">{title}</h2>
        {action}
      </CardHeader>
      <CardBody>{children}</CardBody>
    </Card>
  );
}

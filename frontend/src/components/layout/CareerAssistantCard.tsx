import { Link } from 'react-router-dom';
import { Sparkles } from 'lucide-react';
import { Button } from '@/components/ui/Button';
import { Card, CardBody } from '@/components/ui/Card';
import { Skeleton } from '@/components/ui/States';
import { useDashboardStats } from '@/hooks/useDashboardStats';

interface CareerAssistantCardProps {
  onNavigate?: () => void;
}

export function CareerAssistantCard({ onNavigate }: CareerAssistantCardProps) {
  const { data, isLoading } = useDashboardStats();
  const assistant = data?.career_assistant;

  return (
    <Card className="overflow-hidden border-primary/10 bg-gradient-to-br from-primary/5 to-secondary/5">
      <CardBody className="space-y-3">
        <div className="flex items-center gap-2 text-primary">
          <Sparkles className="h-4 w-4" aria-hidden="true" />
          <p className="text-sm font-semibold">Kariyer Asistanı</p>
        </div>

        {isLoading ? (
          <Skeleton className="h-10" />
        ) : assistant?.has_cv && assistant.analyzed_job_count > 0 ? (
          <p className="text-xs leading-5 text-ink-muted">
            {assistant.analyzed_job_count} ilan için uyum analizin hazır.
            {assistant.average_fit_score !== null ? (
              <>
                {' '}
                Ortalama skorun{' '}
                <span className="font-semibold text-ink">%{assistant.average_fit_score}</span>.
              </>
            ) : null}
          </p>
        ) : (
          <p className="text-xs leading-5 text-ink-muted">
            CV&apos;ni analiz ederek sana daha uygun ilanları keşfet.
          </p>
        )}

        <Link to={assistant?.has_cv ? '/fit-analysis' : '/profile?cv=1'} onClick={onNavigate}>
          <Button className="w-full" size="sm">
            {assistant?.has_cv ? 'Analizi Görüntüle' : 'Analiz Başlat'}
          </Button>
        </Link>
      </CardBody>
    </Card>
  );
}

import { ArrowRight, CheckCircle2, FileUp, Sparkles } from 'lucide-react';
import { Link } from 'react-router-dom';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/Button';
import { Card, CardBody } from '@/components/ui/Card';

interface CandidateCvActivationCardProps {
  action?: ReactNode;
  compact?: boolean;
  profileStrength?: number | null;
}

export function CandidateCvActivationCard({
  action,
  compact = false,
  profileStrength,
}: CandidateCvActivationCardProps) {
  return (
    <Card className="overflow-hidden border-primary/20 bg-gradient-to-br from-primary/5 via-white to-secondary/5">
      <CardBody className={compact ? 'p-5' : 'p-5 sm:p-6'}>
        <div className="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
          <div className="flex items-start gap-4">
            <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-primary text-white shadow-sm">
              <FileUp className="h-6 w-6" aria-hidden="true" />
            </div>
            <div className="space-y-2">
              <div className="flex flex-wrap items-center gap-2">
                <h2 className="text-lg font-semibold text-ink">
                  CV&apos;ni yükle, sana uygun ilanları gör
                </h2>
                {profileStrength !== undefined && profileStrength !== null ? (
                  <span className="rounded-full bg-white px-2.5 py-1 text-xs font-medium text-ink-muted ring-1 ring-surface">
                    Profil gücü %{profileStrength}
                  </span>
                ) : null}
              </div>
              <p className="max-w-2xl text-sm leading-6 text-ink-muted">
                CV&apos;ndeki deneyim ve becerileri analiz edelim; ilanları senin için eşleştirelim ve her fırsat için kişisel Fit Score oluşturalım.
              </p>
              <div className="flex flex-wrap gap-x-4 gap-y-2 text-xs text-ink-muted">
                <span className="inline-flex items-center gap-1.5">
                  <CheckCircle2 className="h-4 w-4 text-primary" aria-hidden="true" />
                  CV&apos;yi yükle
                </span>
                <span className="inline-flex items-center gap-1.5">
                  <Sparkles className="h-4 w-4 text-secondary" aria-hidden="true" />
                  Profili kontrol et
                </span>
                <span className="inline-flex items-center gap-1.5">
                  <ArrowRight className="h-4 w-4 text-primary" aria-hidden="true" />
                  Uyum skorunu keşfet
                </span>
              </div>
            </div>
          </div>

          {action ?? (
            <Link to="/profile?cv=1" className="shrink-0">
              <Button size={compact ? 'md' : 'lg'}>
                CV&apos;ni yükle
                <ArrowRight className="h-4 w-4" aria-hidden="true" />
              </Button>
            </Link>
          )}
        </div>
      </CardBody>
    </Card>
  );
}

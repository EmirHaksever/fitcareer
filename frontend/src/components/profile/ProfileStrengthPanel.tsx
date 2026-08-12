import { CheckCircle2, Circle } from 'lucide-react';
import { ScoreRing } from '@/components/ui/ScoreRing';
import { Card, CardBody } from '@/components/ui/Card';
import type { CandidateProfile } from '@/types/candidate';
import {
  getProfileStrengthBand,
  getProfileStrengthLabel,
  getProfileStrengthMessage,
  getProfileStrengthSuggestions,
} from '@/utils/profileStrength';

interface ProfileStrengthPanelProps {
  profile: CandidateProfile;
}

export function ProfileStrengthPanel({ profile }: ProfileStrengthPanelProps) {
  const score = profile.profile_strength_score;
  const band = getProfileStrengthBand(score);
  const suggestions = getProfileStrengthSuggestions(profile);
  const message = getProfileStrengthMessage(profile);

  return (
    <Card>
      <CardBody className="space-y-5">
        <div className="flex flex-col items-center gap-2 sm:flex-row sm:items-start sm:gap-6">
          <ScoreRing
            value={score}
            label="Profil Gücü"
            band={band}
            size="lg"
          />
          <div className="flex-1 space-y-2 text-center sm:text-left">
            <p className="text-lg font-semibold text-ink">{getProfileStrengthLabel(score)}</p>
            {message ? <p className="text-sm text-ink-muted">{message}</p> : null}
          </div>
        </div>

        <ul className="space-y-2.5">
          {suggestions.map((item) => (
            <li key={item.id} className="flex items-center gap-2.5 text-sm">
              {item.completed ? (
                <CheckCircle2 className="h-4 w-4 shrink-0 text-primary" aria-hidden="true" />
              ) : (
                <Circle className="h-4 w-4 shrink-0 text-ink-subtle" aria-hidden="true" />
              )}
              <span className={item.completed ? 'text-ink-muted line-through' : 'text-ink'}>
                {item.label}
              </span>
            </li>
          ))}
        </ul>
      </CardBody>
    </Card>
  );
}

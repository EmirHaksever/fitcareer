import { Check, CircleAlert } from 'lucide-react';
import { Card, CardBody } from '@/components/ui/Card';
import { MatchScoreDisplay } from '@/components/company-applications/MatchScoreDisplay';
import type { CompanyApplication } from '@/types/companyApplication';
import {
  buildCompanyMatchExplanation,
  insufficientMatchDataMessage,
} from '@/utils/matchExplanation';
import { resolveMatchDisplay } from '@/utils/matchDisplay';

interface CandidateMatchAnalysisCardProps {
  application: CompanyApplication;
}

export function CandidateMatchAnalysisCard({ application }: CandidateMatchAnalysisCardProps) {
  const display = resolveMatchDisplay(application.match_score, application.match_analysis_status);
  const explanation = buildCompanyMatchExplanation(application.match_details, {
    jobExperienceLevel: application.job?.experience_level,
    candidateYears: application.candidate?.years_of_experience,
  });

  return (
    <Card>
      <CardBody className="space-y-5">
        <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
          <div className="min-w-0">
            <h2 className="text-lg font-semibold text-ink">Aday Uyumluluğu</h2>
            <p className="mt-1 text-sm text-ink-muted">
              Bu ilan için mevcut profil ve eşleşme verilerine dayalı özet.
            </p>
          </div>
          <MatchScoreDisplay
            score={application.match_score}
            status={application.match_analysis_status}
            variant="hero"
          />
        </div>

        {display.state === 'pending' ? (
          <p className="rounded-xl bg-background px-4 py-3 text-sm text-ink-muted">
            Analiz hazırlanıyor. Skor hesaplanmadan yüzde gösterilmez.
          </p>
        ) : null}

        {display.state === 'unavailable' ? (
          <p className="rounded-xl bg-background px-4 py-3 text-sm text-ink-muted">
            {insufficientMatchDataMessage()}
          </p>
        ) : null}

        {display.state === 'completed' || application.match_details ? (
          <div className="grid gap-4 md:grid-cols-2">
            <section className="min-w-0 space-y-2 rounded-xl border border-surface bg-background px-4 py-3">
              <h3 className="text-sm font-semibold text-ink">Eşleşen Alanlar</h3>
              {explanation.skillsInsufficient || explanation.matchedSkills.length === 0 ? (
                <p className="text-sm text-ink-muted">{insufficientMatchDataMessage()}</p>
              ) : (
                <ul className="space-y-1.5">
                  {explanation.matchedSkills.map((skill) => (
                    <li key={skill} className="flex items-start gap-2 text-sm text-ink">
                      <Check className="mt-0.5 h-4 w-4 shrink-0 text-secondary" aria-hidden="true" />
                      <span className="min-w-0 break-words">{skill}</span>
                    </li>
                  ))}
                </ul>
              )}
            </section>

            <section className="min-w-0 space-y-2 rounded-xl border border-surface bg-background px-4 py-3">
              <h3 className="text-sm font-semibold text-ink">Dikkat Gerektiren Alanlar</h3>
              {explanation.attentionSkills.length === 0 ? (
                <p className="text-sm text-ink-muted">
                  {explanation.skillsInsufficient
                    ? insufficientMatchDataMessage()
                    : 'Eksik yetenek bildirimi bulunmuyor.'}
                </p>
              ) : (
                <ul className="space-y-1.5">
                  {explanation.attentionSkills.map((skill) => (
                    <li key={skill} className="flex items-start gap-2 text-sm text-ink">
                      <CircleAlert className="mt-0.5 h-4 w-4 shrink-0 text-amber-600" aria-hidden="true" />
                      <span className="min-w-0 break-words">{skill} bilgisi belirtilmemiş</span>
                    </li>
                  ))}
                </ul>
              )}
            </section>

            <section className="min-w-0 space-y-2 rounded-xl border border-surface bg-background px-4 py-3 md:col-span-2">
              <h3 className="text-sm font-semibold text-ink">Deneyim Uyumu</h3>
              {explanation.experience.insufficient ? (
                <p className="text-sm text-ink-muted">{insufficientMatchDataMessage()}</p>
              ) : (
                <div className="grid gap-2 text-sm text-ink sm:grid-cols-2">
                  <p>
                    <span className="text-ink-muted">İlan: </span>
                    {explanation.experience.jobLevelLabel ?? 'Belirtilmemiş'}
                  </p>
                  <p>
                    <span className="text-ink-muted">Aday: </span>
                    {explanation.experience.candidateLabel ?? 'Belirtilmemiş'}
                  </p>
                </div>
              )}
            </section>
          </div>
        ) : null}
      </CardBody>
    </Card>
  );
}

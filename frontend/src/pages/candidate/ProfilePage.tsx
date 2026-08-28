import { useEffect, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { Button } from '@/components/ui/Button';
import { EmptyState, Skeleton } from '@/components/ui/States';
import { useProfileCvActions } from '@/components/profile/useProfileCvActions';
import { ProfileCvImportBanner } from '@/components/profile/ProfileCvImportBanner';
import { ProfileCertificationsSection } from '@/components/profile/ProfileCertificationsSection';
import { ProfileCvSection } from '@/components/profile/ProfileCvSection';
import { ProfileEducationsSection } from '@/components/profile/ProfileEducationsSection';
import { ProfileExperiencesSection } from '@/components/profile/ProfileExperiencesSection';
import { ProfileGeneralForm } from '@/components/profile/ProfileGeneralForm';
import { ProfileProjectsSection } from '@/components/profile/ProfileProjectsSection';
import { ProfileSkillsSection } from '@/components/profile/ProfileSkillsSection';
import { ProfileStrengthPanel } from '@/components/profile/ProfileStrengthPanel';
import { useCandidateCv, useCandidateProfile, useCandidateProfileMutations } from '@/hooks/useCandidateProfile';
import { cn } from '@/utils/format';

type ProfileTab = 'general' | 'experience' | 'education' | 'skills' | 'certifications' | 'projects';

const TABS: { id: ProfileTab; label: string }[] = [
  { id: 'general', label: 'Genel Bilgiler' },
  { id: 'experience', label: 'Deneyim' },
  { id: 'education', label: 'Eğitim' },
  { id: 'skills', label: 'Beceriler' },
  { id: 'certifications', label: 'Sertifikalar' },
  { id: 'projects', label: 'Projeler' },
];

export function ProfilePage() {
  const [searchParams] = useSearchParams();
  const { data: profile, isLoading, isError, refetch } = useCandidateProfile();
  const { data: cvMeta } = useCandidateCv();
  const { uploadCv } = useCandidateProfileMutations();
  const [activeTab, setActiveTab] = useState<ProfileTab>('general');
  const [feedback, setFeedback] = useState<{ type: 'success' | 'error'; message: string } | null>(null);
  const [suggestCvFill, setSuggestCvFill] = useState(false);
  const [cvImportDismissed, setCvImportDismissed] = useState(false);

  useEffect(() => {
    if (searchParams.get('cv') === '1') {
      setSuggestCvFill(true);
      setCvImportDismissed(false);
    }
  }, [searchParams]);

  function showSuccess(message: string) {
    setFeedback({ type: 'success', message });
  }

  function showError(message: string) {
    setFeedback({ type: 'error', message });
  }

  const cvFilename = cvMeta?.source_filename ?? cvMeta?.cv_parsed_data?.source_filename ?? 'cv.pdf';
  const cvActions = useProfileCvActions({
    hasCv: profile?.has_cv ?? false,
    filename: cvFilename,
    onUpload: async (file) => {
      await uploadCv.mutateAsync(file);
      setCvImportDismissed(false);
      setSuggestCvFill(true);
      setActiveTab('general');
      showSuccess('CV başarıyla yüklendi. İçerikleri CV\'den doldurabilirsin.');
    },
    onError: showError,
  });

  if (isLoading) {
    return (
      <div className="space-y-6">
        <Skeleton className="h-10 w-64" />
        <Skeleton className="h-40" />
        <Skeleton className="h-72" />
      </div>
    );
  }

  if (isError || !profile) {
    return (
      <EmptyState
        title="Profil yüklenemedi"
        description="Profil bilgileri şu anda getirilemedi."
        action={
          <Button type="button" onClick={() => void refetch()}>
            Tekrar Dene
          </Button>
        }
      />
    );
  }

  const sectionProps = { onUpdated: showSuccess, onError: showError };

  return (
    <div className="space-y-5">
      <header className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div className="space-y-1">
          <h1 className="text-2xl font-bold tracking-tight text-ink sm:text-3xl">CV Profilim</h1>
          <p className="max-w-2xl text-sm text-ink-muted">
            Profilini güncelle, daha iyi fırsatlar yakala
          </p>
        </div>

        <div className="flex flex-wrap gap-2 sm:justify-end">
          {cvActions.uploadButton}
          {cvActions.previewButton}
        </div>
      </header>

      {feedback ? (
        <div
          className={cn(
            'rounded-xl border px-4 py-3 text-sm',
            feedback.type === 'success'
              ? 'border-primary/20 bg-primary/5 text-primary-800'
              : 'border-danger/20 bg-red-50 text-danger',
          )}
        >
          {feedback.message}
        </div>
      ) : null}

      <ProfileCvImportBanner
        profile={profile}
        cvMeta={cvMeta}
        visible={(suggestCvFill || !cvImportDismissed) && Boolean(cvMeta?.cv_parsed_data)}
        onDismiss={() => {
          setSuggestCvFill(false);
          setCvImportDismissed(true);
        }}
        {...sectionProps}
      />

      <div className="border-b border-surface">
        <div className="flex gap-1 overflow-x-auto">
          {TABS.map((tab) => (
            <button
              key={tab.id}
              type="button"
              onClick={() => setActiveTab(tab.id)}
              className={cn(
                'shrink-0 border-b-2 px-4 py-3 text-sm font-medium transition',
                activeTab === tab.id
                  ? 'border-primary text-primary'
                  : 'border-transparent text-ink-muted hover:text-ink',
              )}
            >
              {tab.label}
            </button>
          ))}
        </div>
      </div>

      {activeTab === 'general' ? (
        <div className="grid gap-5 xl:grid-cols-[320px_minmax(0,1fr)]">
          <ProfileStrengthPanel profile={profile} />
          <div className="space-y-5">
            <ProfileGeneralForm profile={profile} {...sectionProps} />
            <ProfileCvSection profile={profile} {...sectionProps} />
          </div>
        </div>
      ) : null}

      {activeTab === 'experience' ? (
        <ProfileExperiencesSection experiences={profile.experiences} {...sectionProps} />
      ) : null}

      {activeTab === 'education' ? (
        <ProfileEducationsSection educations={profile.educations} {...sectionProps} />
      ) : null}

      {activeTab === 'skills' ? (
        <ProfileSkillsSection skills={profile.skills} {...sectionProps} />
      ) : null}

      {activeTab === 'certifications' ? (
        <ProfileCertificationsSection certifications={profile.certifications} {...sectionProps} />
      ) : null}

      {activeTab === 'projects' ? (
        <ProfileProjectsSection projects={profile.projects} {...sectionProps} />
      ) : null}
    </div>
  );
}

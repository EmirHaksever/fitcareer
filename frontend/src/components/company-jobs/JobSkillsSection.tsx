import { useMutation, useQuery } from '@tanstack/react-query';
import { useEffect, useMemo, useState } from 'react';
import { Plus, Trash2 } from 'lucide-react';
import { skillsCatalogApi } from '@/api/candidate/resources';
import { companyJobSkillsApi } from '@/api/companyJobSkills';
import { getApiErrorMessage, getValidationErrors } from '@/api/client';
import { Button } from '@/components/ui/Button';
import { Card, CardBody, CardHeader } from '@/components/ui/Card';
import { Input } from '@/components/ui/Input';
import { EmptyState, LoadingState } from '@/components/ui/States';
import { selectClassName } from '@/components/profile/profileFormOptions';
import { useSyncCompanyJobSkills } from '@/hooks/useCompanyJobSkills';
import type { JobSkill, JobSkillDraft, JobSkillImportance } from '@/types/companyJob';
import {
  SKILL_IMPORTANCE_LABELS,
  SKILL_IMPORTANCE_OPTIONS,
  addSkill,
  buildSyncPayload,
  catalogItemToDraft,
  mapJobSkillsToDraft,
  mapSkillValidationErrors,
  removeSkill,
  updateSkillImportance,
} from '@/utils/jobSkills';
import { cn } from '@/utils/format';

interface JobSkillsSectionProps {
  jobId?: number;
  initialSkills?: JobSkill[];
  draftSkills?: JobSkillDraft[];
  onDraftSkillsChange?: (skills: JobSkillDraft[]) => void;
  onSaved?: (message: string) => void;
  onError?: (message: string) => void;
  disabled?: boolean;
}

export function JobSkillsSection({
  jobId,
  initialSkills,
  draftSkills,
  onDraftSkillsChange,
  onSaved,
  onError,
  disabled = false,
}: JobSkillsSectionProps) {
  const isDraftMode = !jobId;
  const syncSkills = useSyncCompanyJobSkills();

  const {
    data: fetchedSkills,
    isLoading: isLoadingSkills,
    isError: isSkillsError,
    refetch: refetchSkills,
  } = useQuery({
    queryKey: ['company', 'jobs', 'skills', jobId],
    queryFn: () => companyJobSkillsApi.list(jobId!),
    enabled: Boolean(jobId) && !initialSkills,
  });

  const [localSkills, setLocalSkills] = useState<JobSkillDraft[]>([]);
  const [hasHydrated, setHasHydrated] = useState(false);
  const [search, setSearch] = useState('');
  const [debouncedSearch, setDebouncedSearch] = useState('');
  const [selectedSkillId, setSelectedSkillId] = useState<number | ''>('');
  const [selectedImportance, setSelectedImportance] = useState<JobSkillImportance>('required');
  const [addError, setAddError] = useState<string | null>(null);
  const [saveError, setSaveError] = useState<string | null>(null);
  const [isDirty, setIsDirty] = useState(false);

  const skills = isDraftMode ? (draftSkills ?? localSkills) : localSkills;

  useEffect(() => {
    const timer = window.setTimeout(() => setDebouncedSearch(search.trim()), 300);
    return () => window.clearTimeout(timer);
  }, [search]);

  useEffect(() => {
    if (hasHydrated) return;

    if (isDraftMode) {
      if (draftSkills) {
        setLocalSkills(draftSkills);
      }
      setHasHydrated(true);
      return;
    }

    const source = initialSkills ?? fetchedSkills;
    if (source) {
      setLocalSkills(mapJobSkillsToDraft(source));
      setHasHydrated(true);
    }
  }, [draftSkills, fetchedSkills, hasHydrated, initialSkills, isDraftMode]);

  const { data: catalog = [], isLoading: catalogLoading } = useQuery({
    queryKey: ['skills', 'catalog', debouncedSearch],
    queryFn: () => skillsCatalogApi.search(debouncedSearch, 30),
    enabled: !disabled,
  });

  const availableCatalog = useMemo(
    () => catalog.filter((item) => !skills.some((skill) => skill.skill_id === item.id)),
    [catalog, skills],
  );

  const syncDraftSkills = (nextSkills: JobSkillDraft[]) => {
    if (isDraftMode) {
      if (onDraftSkillsChange) {
        onDraftSkillsChange(nextSkills);
      } else {
        setLocalSkills(nextSkills);
      }
      return;
    }

    setLocalSkills(nextSkills);
    setIsDirty(true);
  };

  function handleAddSkill() {
    setAddError(null);

    if (!selectedSkillId) {
      setAddError('Lütfen bir yetenek seçin.');
      return;
    }

    const catalogItem = catalog.find((item) => item.id === selectedSkillId);
    if (!catalogItem) {
      setAddError('Seçilen yetenek bulunamadı.');
      return;
    }

    const result = addSkill(skills, catalogItemToDraft(catalogItem, selectedImportance));
    if (!result.ok) {
      setAddError('Bu yetenek zaten eklendi.');
      return;
    }

    syncDraftSkills(result.skills);
    setSelectedSkillId('');
    setSelectedImportance('required');
    setSearch('');
    setDebouncedSearch('');
  }

  function handleImportanceChange(skillId: number, importance: JobSkillImportance) {
    syncDraftSkills(updateSkillImportance(skills, skillId, importance));
  }

  function handleRemoveSkill(skillId: number) {
    syncDraftSkills(removeSkill(skills, skillId));
  }

  const saveMutation = useMutation({
    mutationFn: () => {
      if (!jobId) {
        throw new Error('İlan kaydedilmeden yetenekler sunucuya gönderilemez.');
      }

      return syncSkills.mutateAsync({
        jobId,
        payload: buildSyncPayload(skills),
      });
    },
    onSuccess: () => {
      setSaveError(null);
      setIsDirty(false);
      onSaved?.('Aranan yetenekler kaydedildi.');
    },
    onError: (error) => {
      const validationErrors = getValidationErrors(error);
      const message =
        Object.keys(validationErrors).length > 0
          ? mapSkillValidationErrors(validationErrors)
          : getApiErrorMessage(error, 'Yetenekler kaydedilemedi.');
      setSaveError(message);
      onError?.(message);
    },
  });

  if (!isDraftMode && !initialSkills && isLoadingSkills) {
    return (
      <Card>
        <CardHeader>
          <h2 className="text-lg font-semibold text-ink">Aranan Yetenekler</h2>
        </CardHeader>
        <CardBody>
          <LoadingState label="Yetenekler yükleniyor..." />
        </CardBody>
      </Card>
    );
  }

  if (!isDraftMode && !initialSkills && isSkillsError) {
    return (
      <Card>
        <CardHeader>
          <h2 className="text-lg font-semibold text-ink">Aranan Yetenekler</h2>
        </CardHeader>
        <CardBody>
          <EmptyState
            title="Yetenekler yüklenemedi"
            description="Bağlantı sorunu olabilir. Tekrar deneyin."
            action={
              <Button type="button" variant="outline" onClick={() => void refetchSkills()}>
                Tekrar Dene
              </Button>
            }
          />
        </CardBody>
      </Card>
    );
  }

  return (
    <Card>
      <CardHeader>
        <h2 className="text-lg font-semibold text-ink">Aranan Yetenekler</h2>
        <p className="text-sm text-ink-muted">
          {isDraftMode
            ? 'Yetenekler ilan kaydedildiğinde birlikte gönderilir.'
            : 'Pozisyon için aranan yetenekleri zorunlu veya tercihli olarak işaretleyin.'}
        </p>
      </CardHeader>
      <CardBody className="space-y-5">
        {!disabled ? (
          <div className="space-y-4 rounded-xl border border-surface bg-background p-4">
            <Input
              label="Yetenek Ara"
              name="job_skill_search"
              value={search}
              onChange={(event) => setSearch(event.target.value)}
              placeholder="Örn. Flutter, Laravel, Git"
            />

            <div className="grid gap-4 sm:grid-cols-[minmax(0,1fr)_160px_auto]">
              <label className="block space-y-2">
                <span className="text-sm font-medium text-ink">Yetenek</span>
                <select
                  value={selectedSkillId}
                  onChange={(event) =>
                    setSelectedSkillId(event.target.value ? Number(event.target.value) : '')
                  }
                  className={selectClassName}
                  disabled={catalogLoading}
                >
                  <option value="">{catalogLoading ? 'Yükleniyor...' : 'Seçiniz'}</option>
                  {availableCatalog.map((item) => (
                    <option key={item.id} value={item.id}>
                      {item.name}
                    </option>
                  ))}
                </select>
              </label>

              <label className="block space-y-2">
                <span className="text-sm font-medium text-ink">Önem</span>
                <select
                  value={selectedImportance}
                  onChange={(event) =>
                    setSelectedImportance(event.target.value as JobSkillImportance)
                  }
                  className={selectClassName}
                >
                  {SKILL_IMPORTANCE_OPTIONS.map((option) => (
                    <option key={option.value} value={option.value}>
                      {option.label}
                    </option>
                  ))}
                </select>
              </label>

              <div className="flex items-end">
                <Button type="button" variant="secondary" onClick={handleAddSkill} className="w-full sm:w-auto">
                  <Plus className="h-4 w-4" aria-hidden="true" />
                  Ekle
                </Button>
              </div>
            </div>

            {addError ? <p className="text-sm text-danger">{addError}</p> : null}

            {!catalogLoading && availableCatalog.length === 0 ? (
              <p className="text-sm text-ink-muted">
                {debouncedSearch
                  ? 'Aramanızla eşleşen yetenek bulunamadı.'
                  : skills.length > 0
                    ? 'Eklenebilecek başka yetenek kalmadı.'
                    : 'Yetenek kataloğu şu anda boş.'}
              </p>
            ) : null}
          </div>
        ) : null}

        {skills.length === 0 ? (
          <EmptyState
            title="Henüz yetenek eklenmedi"
            description="Aranan yetenekleri ekleyerek aday eşleşmesini iyileştirebilirsin."
          />
        ) : (
          <ul className="divide-y divide-surface overflow-hidden rounded-xl border border-surface">
            {skills.map((skill) => (
              <li
                key={skill.skill_id}
                className="flex flex-col gap-3 bg-white px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
              >
                <div className="min-w-0">
                  <p className="truncate font-medium text-ink">{skill.name}</p>
                  <p className="text-xs text-ink-muted sm:hidden">
                    {SKILL_IMPORTANCE_LABELS[skill.importance]}
                  </p>
                </div>

                <div className="flex items-center gap-2 sm:gap-3">
                  <select
                    value={skill.importance}
                    onChange={(event) =>
                      handleImportanceChange(skill.skill_id, event.target.value as JobSkillImportance)
                    }
                    disabled={disabled}
                    className={cn(selectClassName, 'min-w-[140px] text-sm')}
                    aria-label={`${skill.name} önemi`}
                  >
                    {SKILL_IMPORTANCE_OPTIONS.map((option) => (
                      <option key={option.value} value={option.value}>
                        {option.label}
                      </option>
                    ))}
                  </select>

                  {!disabled ? (
                    <button
                      type="button"
                      onClick={() => handleRemoveSkill(skill.skill_id)}
                      className="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg text-ink-muted transition hover:bg-danger/10 hover:text-danger"
                      aria-label={`${skill.name} yeteneğini kaldır`}
                    >
                      <Trash2 className="h-4 w-4" />
                    </button>
                  ) : null}
                </div>
              </li>
            ))}
          </ul>
        )}

        {!isDraftMode && !disabled ? (
          <div className="space-y-3">
            {saveError ? (
              <div className="rounded-xl border border-danger/20 bg-danger/5 px-4 py-3 text-sm text-danger">
                {saveError}
              </div>
            ) : null}

            <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
              <p className="text-sm text-ink-muted">
                {isDirty ? 'Kaydedilmemiş değişiklikler var.' : 'Tüm değişiklikler kaydedildi.'}
              </p>
              <Button
                type="button"
                onClick={() => saveMutation.mutate()}
                loading={saveMutation.isPending || syncSkills.isPending}
                disabled={!isDirty}
              >
                Yetenekleri Kaydet
              </Button>
            </div>
          </div>
        ) : null}
      </CardBody>
    </Card>
  );
}

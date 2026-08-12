import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useEffect, useMemo, useState } from 'react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { candidateSkillsApi, skillsCatalogApi } from '@/api/candidate/resources';
import { getApiErrorMessage, getValidationErrors } from '@/api/client';
import { Button } from '@/components/ui/Button';
import { ConfirmDialog } from '@/components/ui/ConfirmDialog';
import { Input } from '@/components/ui/Input';
import { Modal } from '@/components/ui/Modal';
import { EmptyState } from '@/components/ui/States';
import { ProfileSectionCard } from '@/components/profile/ProfileSectionCard';
import { PROFICIENCY_OPTIONS, selectClassName } from '@/components/profile/profileFormOptions';
import { CANDIDATE_PROFILE_KEY } from '@/hooks/useCandidateProfile';
import type { AttachSkillPayload, CandidateSkill, ProficiencyLevel, UpdateSkillPayload } from '@/types/candidate';
import { formatProficiencyLevel } from '@/utils/format';
import { sanitizePayload } from '@/utils/payload';

interface Props {
  skills: CandidateSkill[];
  onUpdated?: (message: string) => void;
  onError?: (message: string) => void;
}

export function ProfileSkillsSection({ skills, onUpdated, onError }: Props) {
  const queryClient = useQueryClient();
  const [open, setOpen] = useState(false);
  const [editing, setEditing] = useState<CandidateSkill | null>(null);
  const [deleteId, setDeleteId] = useState<number | null>(null);
  const [search, setSearch] = useState('');
  const [debouncedSearch, setDebouncedSearch] = useState('');
  const [skillId, setSkillId] = useState<number | ''>('');
  const [proficiency, setProficiency] = useState<ProficiencyLevel | ''>('');
  const [years, setYears] = useState('');
  const [errors, setErrors] = useState<Record<string, string>>({});

  useEffect(() => {
    const timer = window.setTimeout(() => setDebouncedSearch(search.trim()), 300);
    return () => window.clearTimeout(timer);
  }, [search]);

  const { data: catalog = [], isLoading: catalogLoading } = useQuery({
    queryKey: ['skills', 'catalog', debouncedSearch],
    queryFn: () => skillsCatalogApi.search(debouncedSearch, 30),
    enabled: open,
  });

  const availableSkills = useMemo(
    () => catalog.filter((item) => !skills.some((s) => s.skill_id === item.id) || editing?.skill_id === item.id),
    [catalog, skills, editing],
  );

  const invalidate = () => void queryClient.invalidateQueries({ queryKey: CANDIDATE_PROFILE_KEY });

  const saveMutation = useMutation({
    mutationFn: () => {
      if (editing) {
        const payload: UpdateSkillPayload = sanitizePayload({
          proficiency_level: proficiency || null,
          years_of_experience: years ? Number(years) : null,
        });
        return candidateSkillsApi.update(editing.id, payload);
      }

      if (!skillId) {
        throw new Error('Lütfen bir yetenek seçin.');
      }

      const payload: AttachSkillPayload = sanitizePayload({
        skill_id: Number(skillId),
        proficiency_level: proficiency || null,
        years_of_experience: years ? Number(years) : null,
      });
      return candidateSkillsApi.attach(payload);
    },
    onSuccess: () => {
      invalidate();
      setOpen(false);
      onUpdated?.(editing ? 'Yetenek güncellendi.' : 'Yetenek eklendi.');
    },
    onError: (error) => {
      if (error instanceof Error && error.message === 'Lütfen bir yetenek seçin.') {
        setErrors({ skill_id: error.message });
        onError?.(error.message);
        return;
      }
      setErrors(Object.fromEntries(Object.entries(getValidationErrors(error)).map(([k, v]) => [k, v[0] ?? ''])));
      onError?.(getApiErrorMessage(error));
    },
  });

  const deleteMutation = useMutation({
    mutationFn: (id: number) => candidateSkillsApi.remove(id),
    onSuccess: () => {
      invalidate();
      setDeleteId(null);
      onUpdated?.('Yetenek silindi.');
    },
    onError: (error) => onError?.(getApiErrorMessage(error)),
  });

  function openCreate() {
    setEditing(null);
    setSkillId('');
    setProficiency('');
    setYears('');
    setSearch('');
    setDebouncedSearch('');
    setErrors({});
    setOpen(true);
  }

  function openEdit(item: CandidateSkill) {
    setEditing(item);
    setSkillId(item.skill_id);
    setProficiency(item.proficiency_level ?? '');
    setYears(item.years_of_experience?.toString() ?? '');
    setSearch(item.skill.name);
    setDebouncedSearch(item.skill.name);
    setErrors({});
    setOpen(true);
  }

  return (
    <>
      <ProfileSectionCard
        title="Yetenekler"
        action={
          <Button type="button" size="sm" onClick={openCreate}>
            <Plus className="h-4 w-4" aria-hidden="true" />
            Yetenek Ekle
          </Button>
        }
      >
        {skills.length === 0 ? (
          <EmptyState title="Henüz yetenek eklenmedi" description="Yeteneklerini ekleyerek uyum skorunu artır." />
        ) : (
          <div className="flex flex-wrap gap-2">
            {skills.map((item) => (
              <div
                key={item.id}
                className="inline-flex items-center gap-2 rounded-full border border-surface bg-background px-3 py-2 text-sm"
              >
                <span className="font-medium text-ink">{item.skill?.name ?? 'Yetenek'}</span>
                <span className="text-ink-muted">
                  {formatProficiencyLevel(item.proficiency_level)}
                  {item.years_of_experience ? ` · ${item.years_of_experience} yıl` : ''}
                </span>
                <button type="button" onClick={() => openEdit(item)} className="text-ink-muted hover:text-primary">
                  <Pencil className="h-3.5 w-3.5" />
                </button>
                <button type="button" onClick={() => setDeleteId(item.id)} className="text-ink-muted hover:text-danger">
                  <Trash2 className="h-3.5 w-3.5" />
                </button>
              </div>
            ))}
          </div>
        )}
      </ProfileSectionCard>

      <Modal open={open} title={editing ? 'Yetenek Düzenle' : 'Yetenek Ekle'} onClose={() => setOpen(false)}>
        <div className="space-y-4">
          {!editing ? (
            <>
              <Input
                label="Yetenek Ara"
                name="skill_search"
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                placeholder="Örn. React, PHP, Laravel"
              />
              <label className="block space-y-2">
                <span className="text-sm font-medium text-ink-muted">Yetenek</span>
                <select
                  value={skillId}
                  onChange={(e) => setSkillId(e.target.value ? Number(e.target.value) : '')}
                  className={selectClassName}
                  disabled={catalogLoading}
                >
                  <option value="">{catalogLoading ? 'Yükleniyor...' : 'Seçiniz'}</option>
                  {availableSkills.map((item) => (
                    <option key={item.id} value={item.id}>
                      {item.name}
                    </option>
                  ))}
                </select>
                {errors.skill_id ? <span className="text-sm text-danger">{errors.skill_id}</span> : null}
                {!catalogLoading && availableSkills.length === 0 ? (
                  <p className="text-sm text-ink-muted">
                    {debouncedSearch
                      ? 'Aramanızla eşleşen yetenek bulunamadı. Farklı bir anahtar kelime deneyin.'
                      : 'Yetenek kataloğu şu anda boş. Lütfen daha sonra tekrar deneyin.'}
                  </p>
                ) : null}
              </label>
            </>
          ) : (
            <Input label="Yetenek" name="skill_name" value={editing.skill.name} disabled />
          )}
          <label className="block space-y-2">
            <span className="text-sm font-medium text-ink-muted">Seviye</span>
            <select
              value={proficiency}
              onChange={(e) => setProficiency(e.target.value as ProficiencyLevel | '')}
              className={selectClassName}
            >
              <option value="">Seçiniz</option>
              {PROFICIENCY_OPTIONS.map((o) => (
                <option key={o.value} value={o.value}>
                  {o.label}
                </option>
              ))}
            </select>
          </label>
          <Input
            label="Yıl"
            name="years_of_experience"
            type="number"
            min={0}
            max={80}
            value={years}
            onChange={(e) => setYears(e.target.value)}
          />
          <div className="flex justify-end gap-2">
            <Button type="button" variant="outline" onClick={() => setOpen(false)}>
              Vazgeç
            </Button>
            <Button type="button" onClick={() => saveMutation.mutate()} loading={saveMutation.isPending}>
              Kaydet
            </Button>
          </div>
        </div>
      </Modal>

      <ConfirmDialog
        open={deleteId !== null}
        title="Yeteneği Sil"
        description="Bu yeteneği profilden kaldırmak istediğinize emin misiniz?"
        onClose={() => setDeleteId(null)}
        onConfirm={() => deleteId && deleteMutation.mutate(deleteId)}
        loading={deleteMutation.isPending}
      />
    </>
  );
}

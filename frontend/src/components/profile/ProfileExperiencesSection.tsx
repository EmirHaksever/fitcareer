import { useMutation, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { candidateExperiencesApi } from '@/api/candidate/resources';
import { getApiErrorMessage, getValidationErrors } from '@/api/client';
import { Button } from '@/components/ui/Button';
import { ConfirmDialog } from '@/components/ui/ConfirmDialog';
import { Input } from '@/components/ui/Input';
import { Modal } from '@/components/ui/Modal';
import { EmptyState } from '@/components/ui/States';
import { ProfileSectionCard } from '@/components/profile/ProfileSectionCard';
import { EMPLOYMENT_TYPE_OPTIONS, selectClassName } from '@/components/profile/profileFormOptions';
import { CANDIDATE_PROFILE_KEY } from '@/hooks/useCandidateProfile';
import { invalidateFitRelatedQueries } from '@/hooks/invalidateFitQueries';
import type { CandidateExperience, ExperiencePayload } from '@/types/candidate';
import { sanitizePayload } from '@/utils/payload';
import { formatDateRange, formatEmploymentType } from '@/utils/format';

interface ProfileExperiencesSectionProps {
  experiences: CandidateExperience[];
  onUpdated?: (message: string) => void;
  onError?: (message: string) => void;
}

const emptyForm: ExperiencePayload = {
  company_name: '',
  position_title: '',
  employment_type: null,
  location: '',
  is_current: false,
  start_date: '',
  end_date: null,
  description: '',
};

export function ProfileExperiencesSection({
  experiences,
  onUpdated,
  onError,
}: ProfileExperiencesSectionProps) {
  const queryClient = useQueryClient();
  const [open, setOpen] = useState(false);
  const [editing, setEditing] = useState<CandidateExperience | null>(null);
  const [deleteId, setDeleteId] = useState<number | null>(null);
  const [form, setForm] = useState<ExperiencePayload>(emptyForm);
  const [errors, setErrors] = useState<Record<string, string>>({});

  const invalidate = () => {
    void queryClient.invalidateQueries({ queryKey: CANDIDATE_PROFILE_KEY });
    invalidateFitRelatedQueries(queryClient);
  };

  const saveMutation = useMutation({
    mutationFn: async () => {
      const payload = sanitizePayload(form);
      if (editing) {
        return candidateExperiencesApi.update(editing.id, payload);
      }
      return candidateExperiencesApi.create(payload);
    },
    onSuccess: () => {
      invalidate();
      setOpen(false);
      onUpdated?.(editing ? 'Deneyim güncellendi.' : 'Deneyim eklendi.');
    },
    onError: (error) => {
      const validation = getValidationErrors(error);
      setErrors(Object.fromEntries(Object.entries(validation).map(([k, v]) => [k, v[0] ?? ''])));
      onError?.(getApiErrorMessage(error, 'Kayıt başarısız.'));
    },
  });

  const deleteMutation = useMutation({
    mutationFn: (id: number) => candidateExperiencesApi.remove(id),
    onSuccess: () => {
      invalidate();
      setDeleteId(null);
      onUpdated?.('Deneyim silindi.');
    },
    onError: (error) => onError?.(getApiErrorMessage(error, 'Silme başarısız.')),
  });

  function openCreate() {
    setEditing(null);
    setForm(emptyForm);
    setErrors({});
    setOpen(true);
  }

  function openEdit(item: CandidateExperience) {
    setEditing(item);
    setForm({
      company_name: item.company_name,
      position_title: item.position_title,
      employment_type: item.employment_type,
      location: item.location,
      is_current: item.is_current,
      start_date: item.start_date,
      end_date: item.end_date,
      description: item.description,
    });
    setErrors({});
    setOpen(true);
  }

  return (
    <>
      <ProfileSectionCard
        title="Deneyim"
        action={
          <Button type="button" size="sm" onClick={openCreate}>
            <Plus className="h-4 w-4" aria-hidden="true" />
            Deneyim Ekle
          </Button>
        }
      >
        {experiences.length === 0 ? (
          <EmptyState title="Henüz deneyim eklenmedi" description="İş deneyimlerini ekleyerek profilini güçlendir." />
        ) : (
          <div className="space-y-4">
            {experiences.map((item) => (
              <div key={item.id} className="rounded-xl border border-surface p-4">
                <div className="flex items-start justify-between gap-3">
                  <div>
                    <p className="font-semibold text-ink">{item.position_title}</p>
                    <p className="text-sm text-ink-muted">{item.company_name}</p>
                    <p className="mt-1 text-xs text-ink-muted">
                      {formatDateRange(item.start_date, item.end_date, item.is_current)}
                      {item.employment_type ? ` · ${formatEmploymentType(item.employment_type)}` : ''}
                    </p>
                    {item.description ? (
                      <p className="mt-2 whitespace-pre-wrap text-sm text-ink-muted">{item.description}</p>
                    ) : null}
                  </div>
                  <div className="flex gap-1">
                    <Button type="button" variant="ghost" size="sm" onClick={() => openEdit(item)}>
                      <Pencil className="h-4 w-4" />
                    </Button>
                    <Button type="button" variant="ghost" size="sm" onClick={() => setDeleteId(item.id)}>
                      <Trash2 className="h-4 w-4" />
                    </Button>
                  </div>
                </div>
              </div>
            ))}
          </div>
        )}
      </ProfileSectionCard>

      <Modal
        open={open}
        title={editing ? 'Deneyim Düzenle' : 'Deneyim Ekle'}
        onClose={() => setOpen(false)}
      >
        <div className="space-y-4">
          <Input label="Pozisyon" name="position_title" value={form.position_title} onChange={(e) => setForm((p) => ({ ...p, position_title: e.target.value }))} error={errors.position_title} />
          <Input label="Şirket" name="company_name" value={form.company_name} onChange={(e) => setForm((p) => ({ ...p, company_name: e.target.value }))} error={errors.company_name} />
          <Input label="Konum" name="location" value={form.location ?? ''} onChange={(e) => setForm((p) => ({ ...p, location: e.target.value }))} />
          <label className="block space-y-2">
            <span className="text-sm font-medium text-ink-muted">İstihdam Türü</span>
            <select value={form.employment_type ?? ''} onChange={(e) => setForm((p) => ({ ...p, employment_type: e.target.value as ExperiencePayload['employment_type'] }))} className={selectClassName}>
              <option value="">Seçiniz</option>
              {EMPLOYMENT_TYPE_OPTIONS.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
            </select>
          </label>
          <div className="grid gap-4 sm:grid-cols-2">
            <Input label="Başlangıç" name="start_date" type="date" value={form.start_date} onChange={(e) => setForm((p) => ({ ...p, start_date: e.target.value }))} error={errors.start_date} />
            <Input label="Bitiş" name="end_date" type="date" value={form.end_date ?? ''} onChange={(e) => setForm((p) => ({ ...p, end_date: e.target.value || null }))} disabled={form.is_current} error={errors.end_date} />
          </div>
          <label className="flex items-center gap-2 text-sm text-ink">
            <input type="checkbox" checked={form.is_current ?? false} onChange={(e) => setForm((p) => ({ ...p, is_current: e.target.checked, end_date: e.target.checked ? null : p.end_date }))} />
            Halen çalışıyorum
          </label>
          <label className="block space-y-2">
            <span className="text-sm font-medium text-ink-muted">Açıklama</span>
            <textarea value={form.description ?? ''} onChange={(e) => setForm((p) => ({ ...p, description: e.target.value }))} rows={4} className="w-full rounded-xl border border-surface px-3 py-2 text-sm outline-none focus:border-primary focus:ring-4 focus:ring-primary/10" />
          </label>
          <div className="flex justify-end gap-2">
            <Button type="button" variant="outline" onClick={() => setOpen(false)}>Vazgeç</Button>
            <Button type="button" onClick={() => saveMutation.mutate()} loading={saveMutation.isPending}>Kaydet</Button>
          </div>
        </div>
      </Modal>

      <ConfirmDialog
        open={deleteId !== null}
        title="Deneyimi Sil"
        description="Bu deneyim kaydını silmek istediğinize emin misiniz?"
        onClose={() => setDeleteId(null)}
        onConfirm={() => deleteId && deleteMutation.mutate(deleteId)}
        loading={deleteMutation.isPending}
      />
    </>
  );
}

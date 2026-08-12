import { useMutation, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { candidateEducationsApi } from '@/api/candidate/resources';
import { getApiErrorMessage, getValidationErrors } from '@/api/client';
import { Button } from '@/components/ui/Button';
import { ConfirmDialog } from '@/components/ui/ConfirmDialog';
import { Input } from '@/components/ui/Input';
import { Modal } from '@/components/ui/Modal';
import { EmptyState } from '@/components/ui/States';
import { ProfileSectionCard } from '@/components/profile/ProfileSectionCard';
import { CANDIDATE_PROFILE_KEY } from '@/hooks/useCandidateProfile';
import type { CandidateEducation, EducationPayload } from '@/types/candidate';
import { sanitizePayload } from '@/utils/payload';
import { formatDateRange } from '@/utils/format';

interface Props {
  educations: CandidateEducation[];
  onUpdated?: (message: string) => void;
  onError?: (message: string) => void;
}

const emptyForm: EducationPayload = {
  school_name: '',
  degree: '',
  field_of_study: '',
  start_date: '',
  end_date: null,
  is_current: false,
  grade: '',
  description: '',
};

export function ProfileEducationsSection({ educations, onUpdated, onError }: Props) {
  const queryClient = useQueryClient();
  const [open, setOpen] = useState(false);
  const [editing, setEditing] = useState<CandidateEducation | null>(null);
  const [deleteId, setDeleteId] = useState<number | null>(null);
  const [form, setForm] = useState<EducationPayload>(emptyForm);
  const [errors, setErrors] = useState<Record<string, string>>({});

  const invalidate = () => void queryClient.invalidateQueries({ queryKey: CANDIDATE_PROFILE_KEY });

  const saveMutation = useMutation({
    mutationFn: () => (editing ? candidateEducationsApi.update(editing.id, sanitizePayload(form)) : candidateEducationsApi.create(sanitizePayload(form))),
    onSuccess: () => { invalidate(); setOpen(false); onUpdated?.(editing ? 'Eğitim güncellendi.' : 'Eğitim eklendi.'); },
    onError: (error) => {
      setErrors(Object.fromEntries(Object.entries(getValidationErrors(error)).map(([k, v]) => [k, v[0] ?? ''])));
      onError?.(getApiErrorMessage(error));
    },
  });

  const deleteMutation = useMutation({
    mutationFn: (id: number) => candidateEducationsApi.remove(id),
    onSuccess: () => { invalidate(); setDeleteId(null); onUpdated?.('Eğitim silindi.'); },
    onError: (error) => onError?.(getApiErrorMessage(error)),
  });

  function openCreate() { setEditing(null); setForm(emptyForm); setErrors({}); setOpen(true); }
  function openEdit(item: CandidateEducation) {
    setEditing(item);
    setForm({ school_name: item.school_name, degree: item.degree, field_of_study: item.field_of_study, start_date: item.start_date, end_date: item.end_date, is_current: item.is_current, grade: item.grade, description: item.description });
    setErrors({}); setOpen(true);
  }

  return (
    <>
      <ProfileSectionCard title="Eğitim" action={<Button type="button" size="sm" onClick={openCreate}><Plus className="h-4 w-4" />Eğitim Ekle</Button>}>
        {educations.length === 0 ? <EmptyState title="Henüz eğitim eklenmedi" description="Eğitim bilgilerini ekleyerek profilini tamamla." /> : (
          <div className="space-y-4">
            {educations.map((item) => (
              <div key={item.id} className="rounded-xl border border-surface p-4">
                <div className="flex justify-between gap-3">
                  <div>
                    <p className="font-semibold text-ink">{item.school_name}</p>
                    <p className="text-sm text-ink-muted">{[item.degree, item.field_of_study].filter(Boolean).join(' · ') || '—'}</p>
                    <p className="mt-1 text-xs text-ink-muted">{formatDateRange(item.start_date, item.end_date, item.is_current)}</p>
                  </div>
                  <div className="flex gap-1">
                    <Button type="button" variant="ghost" size="sm" onClick={() => openEdit(item)}><Pencil className="h-4 w-4" /></Button>
                    <Button type="button" variant="ghost" size="sm" onClick={() => setDeleteId(item.id)}><Trash2 className="h-4 w-4" /></Button>
                  </div>
                </div>
              </div>
            ))}
          </div>
        )}
      </ProfileSectionCard>

      <Modal open={open} title={editing ? 'Eğitim Düzenle' : 'Eğitim Ekle'} onClose={() => setOpen(false)}>
        <div className="space-y-4">
          <Input label="Okul" value={form.school_name} onChange={(e) => setForm((p) => ({ ...p, school_name: e.target.value }))} error={errors.school_name} />
          <Input label="Derece" value={form.degree ?? ''} onChange={(e) => setForm((p) => ({ ...p, degree: e.target.value }))} />
          <Input label="Bölüm" value={form.field_of_study ?? ''} onChange={(e) => setForm((p) => ({ ...p, field_of_study: e.target.value }))} />
          <div className="grid gap-4 sm:grid-cols-2">
            <Input label="Başlangıç" type="date" value={form.start_date} onChange={(e) => setForm((p) => ({ ...p, start_date: e.target.value }))} error={errors.start_date} />
            <Input label="Bitiş" type="date" value={form.end_date ?? ''} onChange={(e) => setForm((p) => ({ ...p, end_date: e.target.value || null }))} disabled={form.is_current} />
          </div>
          <label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={form.is_current ?? false} onChange={(e) => setForm((p) => ({ ...p, is_current: e.target.checked, end_date: e.target.checked ? null : p.end_date }))} />Devam ediyor</label>
          <div className="flex justify-end gap-2"><Button variant="outline" onClick={() => setOpen(false)}>Vazgeç</Button><Button onClick={() => saveMutation.mutate()} loading={saveMutation.isPending}>Kaydet</Button></div>
        </div>
      </Modal>

      <ConfirmDialog open={deleteId !== null} title="Eğitimi Sil" description="Bu eğitim kaydını silmek istediğinize emin misiniz?" onClose={() => setDeleteId(null)} onConfirm={() => deleteId && deleteMutation.mutate(deleteId)} loading={deleteMutation.isPending} />
    </>
  );
}

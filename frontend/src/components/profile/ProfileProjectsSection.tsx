import { useMutation, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { ExternalLink, Pencil, Plus, Trash2 } from 'lucide-react';
import { candidateProjectsApi } from '@/api/candidate/resources';
import { getApiErrorMessage, getValidationErrors } from '@/api/client';
import { Button } from '@/components/ui/Button';
import { ConfirmDialog } from '@/components/ui/ConfirmDialog';
import { Input } from '@/components/ui/Input';
import { Modal } from '@/components/ui/Modal';
import { EmptyState } from '@/components/ui/States';
import { ProfileSectionCard } from '@/components/profile/ProfileSectionCard';
import { CANDIDATE_PROFILE_KEY } from '@/hooks/useCandidateProfile';
import type { CandidateProject, ProjectPayload } from '@/types/candidate';
import { sanitizePayload } from '@/utils/payload';

interface Props {
  projects: CandidateProject[];
  onUpdated?: (message: string) => void;
  onError?: (message: string) => void;
}

const emptyForm: ProjectPayload = {
  title: '',
  description: '',
  project_url: '',
  repository_url: '',
  start_date: null,
  end_date: null,
  technologies: [],
};

export function ProfileProjectsSection({ projects, onUpdated, onError }: Props) {
  const queryClient = useQueryClient();
  const [open, setOpen] = useState(false);
  const [editing, setEditing] = useState<CandidateProject | null>(null);
  const [deleteId, setDeleteId] = useState<number | null>(null);
  const [form, setForm] = useState<ProjectPayload>(emptyForm);
  const [techInput, setTechInput] = useState('');
  const [errors, setErrors] = useState<Record<string, string>>({});

  const invalidate = () => void queryClient.invalidateQueries({ queryKey: CANDIDATE_PROFILE_KEY });

  const saveMutation = useMutation({
    mutationFn: (payload: ProjectPayload) =>
      editing ? candidateProjectsApi.update(editing.id, payload) : candidateProjectsApi.create(payload),
    onSuccess: () => { invalidate(); setOpen(false); onUpdated?.(editing ? 'Proje güncellendi.' : 'Proje eklendi.'); },
    onError: (error) => {
      setErrors(Object.fromEntries(Object.entries(getValidationErrors(error)).map(([k, v]) => [k, v[0] ?? ''])));
      onError?.(getApiErrorMessage(error));
    },
  });

  const deleteMutation = useMutation({
    mutationFn: (id: number) => candidateProjectsApi.remove(id),
    onSuccess: () => { invalidate(); setDeleteId(null); onUpdated?.('Proje silindi.'); },
    onError: (error) => onError?.(getApiErrorMessage(error)),
  });

  function openCreate() { setEditing(null); setForm(emptyForm); setTechInput(''); setErrors({}); setOpen(true); }
  function openEdit(item: CandidateProject) {
    setEditing(item);
    setForm({ title: item.title, description: item.description, project_url: item.project_url, repository_url: item.repository_url, start_date: item.start_date, end_date: item.end_date, technologies: item.technologies });
    setTechInput((item.technologies ?? []).join(', '));
    setErrors({}); setOpen(true);
  }

  function handleSave() {
    const technologies = techInput.split(',').map((t) => t.trim()).filter(Boolean);
    saveMutation.mutate(sanitizePayload({ ...form, technologies }));
  }

  return (
    <>
      <ProfileSectionCard title="Projeler" action={<Button type="button" size="sm" onClick={openCreate}><Plus className="h-4 w-4" />Proje Ekle</Button>}>
        {projects.length === 0 ? <EmptyState title="Henüz proje eklenmedi" description="Projelerini ekleyerek deneyimini göster." /> : (
          <div className="space-y-4">
            {projects.map((item) => (
              <div key={item.id} className="rounded-xl border border-surface p-4">
                <div className="flex justify-between gap-3">
                  <div>
                    <p className="font-semibold text-ink">{item.title}</p>
                    {item.description ? <p className="mt-1 text-sm text-ink-muted">{item.description}</p> : null}
                    {item.project_url ? (
                      <a href={item.project_url} target="_blank" rel="noreferrer" className="mt-2 inline-flex items-center gap-1 text-sm text-primary hover:underline">
                        <ExternalLink className="h-3.5 w-3.5" />Proje bağlantısı
                      </a>
                    ) : null}
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

      <Modal open={open} title={editing ? 'Proje Düzenle' : 'Proje Ekle'} onClose={() => setOpen(false)}>
        <div className="space-y-4">
          <Input label="Proje Adı" value={form.title} onChange={(e) => setForm((p) => ({ ...p, title: e.target.value }))} error={errors.title} />
          <label className="block space-y-2">
            <span className="text-sm font-medium text-ink-muted">Açıklama</span>
            <textarea value={form.description ?? ''} onChange={(e) => setForm((p) => ({ ...p, description: e.target.value }))} rows={4} className="w-full rounded-xl border border-surface px-3 py-2 text-sm outline-none focus:border-primary focus:ring-4 focus:ring-primary/10" />
          </label>
          <Input label="Proje URL" value={form.project_url ?? ''} onChange={(e) => setForm((p) => ({ ...p, project_url: e.target.value }))} error={errors.project_url} />
          <Input label="Repository URL" value={form.repository_url ?? ''} onChange={(e) => setForm((p) => ({ ...p, repository_url: e.target.value }))} />
          <Input label="Teknolojiler (virgülle)" value={techInput} onChange={(e) => setTechInput(e.target.value)} placeholder="React, TypeScript" />
          <div className="flex justify-end gap-2"><Button variant="outline" onClick={() => setOpen(false)}>Vazgeç</Button><Button onClick={handleSave} loading={saveMutation.isPending}>Kaydet</Button></div>
        </div>
      </Modal>

      <ConfirmDialog open={deleteId !== null} title="Projeyi Sil" description="Bu projeyi silmek istediğinize emin misiniz?" onClose={() => setDeleteId(null)} onConfirm={() => deleteId && deleteMutation.mutate(deleteId)} loading={deleteMutation.isPending} />
    </>
  );
}

import { useMutation, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { candidateCertificationsApi } from '@/api/candidate/resources';
import { getApiErrorMessage, getValidationErrors } from '@/api/client';
import { Button } from '@/components/ui/Button';
import { ConfirmDialog } from '@/components/ui/ConfirmDialog';
import { Input } from '@/components/ui/Input';
import { Modal } from '@/components/ui/Modal';
import { EmptyState } from '@/components/ui/States';
import { ProfileSectionCard } from '@/components/profile/ProfileSectionCard';
import { CANDIDATE_PROFILE_KEY } from '@/hooks/useCandidateProfile';
import type { CandidateCertification, CertificationPayload } from '@/types/candidate';
import { formatDateRange } from '@/utils/format';
import { sanitizePayload } from '@/utils/payload';

interface Props {
  certifications: CandidateCertification[];
  onUpdated?: (message: string) => void;
  onError?: (message: string) => void;
}

const emptyForm: CertificationPayload = {
  name: '',
  issuing_organization: '',
  issue_date: null,
  expiration_date: null,
  credential_id: null,
  credential_url: null,
};

export function ProfileCertificationsSection({ certifications, onUpdated, onError }: Props) {
  const queryClient = useQueryClient();
  const [open, setOpen] = useState(false);
  const [editing, setEditing] = useState<CandidateCertification | null>(null);
  const [deleteId, setDeleteId] = useState<number | null>(null);
  const [form, setForm] = useState<CertificationPayload>(emptyForm);
  const [errors, setErrors] = useState<Record<string, string>>({});

  const invalidate = () => void queryClient.invalidateQueries({ queryKey: CANDIDATE_PROFILE_KEY });

  const saveMutation = useMutation({
    mutationFn: () => {
      const payload = sanitizePayload(form);
      return editing
        ? candidateCertificationsApi.update(editing.id, payload)
        : candidateCertificationsApi.create(payload);
    },
    onSuccess: () => {
      invalidate();
      setOpen(false);
      onUpdated?.(editing ? 'Sertifika güncellendi.' : 'Sertifika eklendi.');
    },
    onError: (error) => {
      setErrors(Object.fromEntries(Object.entries(getValidationErrors(error)).map(([k, v]) => [k, v[0] ?? ''])));
      onError?.(getApiErrorMessage(error));
    },
  });

  const deleteMutation = useMutation({
    mutationFn: (id: number) => candidateCertificationsApi.remove(id),
    onSuccess: () => {
      invalidate();
      setDeleteId(null);
      onUpdated?.('Sertifika silindi.');
    },
    onError: (error) => onError?.(getApiErrorMessage(error)),
  });

  function openCreate() {
    setEditing(null);
    setForm(emptyForm);
    setErrors({});
    setOpen(true);
  }

  function openEdit(item: CandidateCertification) {
    setEditing(item);
    setForm({
      name: item.name,
      issuing_organization: item.issuing_organization,
      issue_date: item.issue_date,
      expiration_date: item.expiration_date,
      credential_id: item.credential_id,
      credential_url: item.credential_url,
    });
    setErrors({});
    setOpen(true);
  }

  return (
    <>
      <ProfileSectionCard
        title="Sertifikalar"
        action={
          <Button type="button" size="sm" onClick={openCreate}>
            <Plus className="h-4 w-4" aria-hidden="true" />
            Sertifika Ekle
          </Button>
        }
      >
        {certifications.length === 0 ? (
          <EmptyState
            title="Henüz sertifika eklenmedi"
            description="Sertifika bilgilerini manuel olarak ekleyebilirsin. (Dosya yükleme desteklenmiyor)"
          />
        ) : (
          <div className="space-y-4">
            {certifications.map((item) => (
              <div key={item.id} className="rounded-xl border border-surface p-4">
                <div className="flex justify-between gap-3">
                  <div>
                    <p className="font-semibold text-ink">{item.name}</p>
                    <p className="text-sm text-ink-muted">{item.issuing_organization}</p>
                    <p className="mt-1 text-xs text-ink-muted">
                      {formatDateRange(item.issue_date, item.expiration_date)}
                    </p>
                    {item.credential_url ? (
                      <a
                        href={item.credential_url}
                        target="_blank"
                        rel="noreferrer"
                        className="mt-1 inline-block text-xs text-primary hover:underline"
                      >
                        Sertifika bağlantısı
                      </a>
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

      <Modal open={open} title={editing ? 'Sertifika Düzenle' : 'Sertifika Ekle'} onClose={() => setOpen(false)}>
        <div className="space-y-4">
          <Input
            label="Sertifika Adı"
            name="name"
            value={form.name}
            onChange={(e) => setForm((p) => ({ ...p, name: e.target.value }))}
            error={errors.name}
          />
          <Input
            label="Veren Kurum"
            name="issuing_organization"
            value={form.issuing_organization}
            onChange={(e) => setForm((p) => ({ ...p, issuing_organization: e.target.value }))}
            error={errors.issuing_organization}
          />
          <div className="grid gap-4 sm:grid-cols-2">
            <Input
              label="Alınma Tarihi"
              name="issue_date"
              type="date"
              value={form.issue_date ?? ''}
              onChange={(e) => setForm((p) => ({ ...p, issue_date: e.target.value || null }))}
            />
            <Input
              label="Bitiş Tarihi"
              name="expiration_date"
              type="date"
              value={form.expiration_date ?? ''}
              onChange={(e) => setForm((p) => ({ ...p, expiration_date: e.target.value || null }))}
            />
          </div>
          <Input
            label="Kimlik / Credential ID"
            name="credential_id"
            value={form.credential_id ?? ''}
            onChange={(e) => setForm((p) => ({ ...p, credential_id: e.target.value || null }))}
            error={errors.credential_id}
          />
          <Input
            label="Sertifika URL"
            name="credential_url"
            value={form.credential_url ?? ''}
            onChange={(e) => setForm((p) => ({ ...p, credential_url: e.target.value || null }))}
            placeholder="https://..."
            error={errors.credential_url}
          />
          <p className="text-xs text-ink-muted">
            Sertifika dosyası yüklenemez; doğrulama bağlantısı veya kimlik numarası ekleyebilirsin.
          </p>
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
        title="Sertifikayı Sil"
        description="Bu sertifikayı silmek istediğinize emin misiniz?"
        onClose={() => setDeleteId(null)}
        onConfirm={() => deleteId && deleteMutation.mutate(deleteId)}
        loading={deleteMutation.isPending}
      />
    </>
  );
}

import axios from 'axios';
import { useEffect, useState } from 'react';
import { CompanyApplicationStatusBadge } from '@/components/company-applications/CompanyApplicationStatusBadge';
import { Button } from '@/components/ui/Button';
import { Modal } from '@/components/ui/Modal';
import { useUpdateCompanyApplicationStatus } from '@/hooks/useCompanyApplications';
import type { ApplicationStatus } from '@/types/application';
import type { CompanyApplication } from '@/types/companyApplication';
import { getApplicationStatusLabel } from '@/utils/applicationStatus';
import { getAllowedNextStatuses } from '@/utils/applicationTransitions';
import { cn } from '@/utils/format';

interface CompanyApplicationStatusModalProps {
  application: CompanyApplication;
  open: boolean;
  onClose: () => void;
  onSuccess?: () => void;
}

export function CompanyApplicationStatusModal({
  application,
  open,
  onClose,
  onSuccess,
}: CompanyApplicationStatusModalProps) {
  const allowedStatuses = getAllowedNextStatuses(application.status);
  const [selectedStatus, setSelectedStatus] = useState<ApplicationStatus | ''>('');
  const [note, setNote] = useState('');
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const updateStatus = useUpdateCompanyApplicationStatus();

  useEffect(() => {
    if (!open) return;

    const nextStatuses = getAllowedNextStatuses(application.status);
    setSelectedStatus(nextStatuses[0] ?? '');
    setNote('');
    setErrorMessage(null);
  }, [open, application.status]);

  function handleClose() {
    if (updateStatus.isPending) return;
    onClose();
  }

  function handleSubmit() {
    if (!selectedStatus) return;

    setErrorMessage(null);

    updateStatus.mutate(
      {
        id: application.id,
        payload: {
          status: selectedStatus,
          note: note.trim() || null,
        },
      },
      {
        onSuccess: () => {
          onSuccess?.();
          onClose();
        },
        onError: (error) => {
          if (axios.isAxiosError(error) && error.response?.status === 409) {
            const message =
              error.response.data?.message ??
              error.response.data?.errors?.status?.[0] ??
              'Bu durum geçişi artık geçerli değil.';
            setErrorMessage(message);
            return;
          }

          if (axios.isAxiosError(error) && error.response?.status === 403) {
            setErrorMessage('Bu işlem için yetkin yok.');
            return;
          }

          setErrorMessage('Durum güncellenemedi. Lütfen tekrar dene.');
        },
      },
    );
  }

  return (
    <Modal open={open} title="Başvuru Durumunu Güncelle" onClose={handleClose}>
      <div className="space-y-5">
        <div className="flex flex-wrap items-center gap-2">
          <span className="text-sm text-ink-muted">Mevcut durum:</span>
          <CompanyApplicationStatusBadge status={application.status} />
        </div>

        {allowedStatuses.length === 0 ? (
          <p className="rounded-lg border border-surface bg-background px-3 py-2 text-sm text-ink-muted">
            Bu başvuru son durumda. Yeni bir geçiş yapılamaz.
          </p>
        ) : (
          <>
            <label className="block space-y-2" htmlFor="company-application-status">
              <span className="text-sm font-medium text-ink">Yeni durum</span>
              <select
                id="company-application-status"
                value={selectedStatus}
                onChange={(event) => setSelectedStatus(event.target.value as ApplicationStatus)}
                className={cn(
                  'h-11 w-full rounded-xl border border-surface bg-background px-3.5 text-sm text-ink outline-none transition',
                  'focus:border-primary focus:bg-white focus:ring-4 focus:ring-primary/10',
                )}
              >
                {allowedStatuses.map((status) => (
                  <option key={status} value={status}>
                    {getApplicationStatusLabel(status)}
                  </option>
                ))}
              </select>
            </label>

            <label className="block space-y-2" htmlFor="company-application-note">
              <span className="text-sm font-medium text-ink">Not (opsiyonel)</span>
              <textarea
                id="company-application-note"
                rows={4}
                value={note}
                onChange={(event) => setNote(event.target.value)}
                placeholder="Durum değişikliği için kısa bir not ekleyebilirsin..."
                className={cn(
                  'w-full resize-y rounded-xl border border-surface bg-background px-3.5 py-3 text-sm text-ink outline-none transition',
                  'placeholder:text-ink-subtle focus:border-primary focus:bg-white focus:ring-4 focus:ring-primary/10',
                )}
              />
            </label>
          </>
        )}

        {errorMessage ? (
          <p className="rounded-lg border border-danger/20 bg-red-50 px-3 py-2 text-sm text-danger" role="alert">
            {errorMessage}
          </p>
        ) : null}

        <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
          <Button type="button" variant="outline" onClick={handleClose} disabled={updateStatus.isPending}>
            Vazgeç
          </Button>
          {allowedStatuses.length > 0 ? (
            <Button
              type="button"
              onClick={handleSubmit}
              loading={updateStatus.isPending}
              loadingLabel="Güncelleniyor..."
            >
              Durumu Güncelle
            </Button>
          ) : null}
        </div>
      </div>
    </Modal>
  );
}

import axios from 'axios';
import { CheckCircle2 } from 'lucide-react';
import { useState } from 'react';
import { Link } from 'react-router-dom';
import { getApiErrorMessage, getValidationErrors } from '@/api/client';
import { Button } from '@/components/ui/Button';
import { Modal } from '@/components/ui/Modal';
import { useCreateApplication } from '@/hooks/useApplications';
import { translateApplicationError } from '@/utils/applicationStatus';
import { cn } from '@/utils/format';

interface ApplyJobModalProps {
  jobId: number;
  jobTitle: string;
  companyName?: string | null;
  open: boolean;
  onClose: () => void;
}

export function ApplyJobModal({ jobId, jobTitle, companyName, open, onClose }: ApplyJobModalProps) {
  const [coverLetter, setCoverLetter] = useState('');
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [submitted, setSubmitted] = useState(false);
  const createApplication = useCreateApplication();

  function handleClose() {
    if (createApplication.isPending) return;
    setCoverLetter('');
    setErrorMessage(null);
    setSubmitted(false);
    onClose();
  }

  function handleSubmit() {
    setErrorMessage(null);

    createApplication.mutate(
      {
        job_id: jobId,
        cover_letter: coverLetter.trim() || null,
      },
      {
        onSuccess: () => {
          setSubmitted(true);
        },
        onError: (error) => {
          const validationErrors = getValidationErrors(error);
          const jobError = validationErrors.job_id?.[0];

          if (jobError) {
            setErrorMessage(translateApplicationError(jobError));
            return;
          }

          if (axios.isAxiosError(error)) {
            const status = error.response?.status;

            if (status === 403) {
              setErrorMessage('Bu işlem için yetkin yok.');
              return;
            }

            if (status === 404) {
              setErrorMessage('İlan bulunamadı veya artık başvuruya kapalı.');
              return;
            }
          }

          setErrorMessage(translateApplicationError(getApiErrorMessage(error, 'Başvuru gönderilemedi.')));
        },
      },
    );
  }

  return (
    <Modal open={open} title={submitted ? 'Başvuru Gönderildi' : 'İlana Başvur'} onClose={handleClose}>
      {submitted ? (
        <div className="space-y-5 text-center">
          <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-success/20 text-primary">
            <CheckCircle2 className="h-7 w-7" aria-hidden="true" />
          </div>
          <div className="space-y-2">
            <p className="text-base font-semibold text-ink">Başvurun başarıyla gönderildi</p>
            <p className="text-sm text-ink-muted">
              <span className="font-medium text-ink">{jobTitle}</span>
              {companyName ? ` · ${companyName}` : ''} ilanına başvurun alındı.
            </p>
          </div>
          <div className="flex flex-col gap-2 sm:flex-row sm:justify-center">
            <Link to="/applications" className="w-full sm:w-auto">
              <Button type="button" className="w-full">
                Başvurularım
              </Button>
            </Link>
            <Button type="button" variant="outline" className="w-full sm:w-auto" onClick={handleClose}>
              Kapat
            </Button>
          </div>
        </div>
      ) : (
        <div className="space-y-5">
          <div className="space-y-1">
            <p className="text-sm font-medium text-ink">{jobTitle}</p>
            {companyName ? <p className="text-sm text-ink-muted">{companyName}</p> : null}
          </div>

          <label className="block space-y-2" htmlFor="cover-letter">
            <span className="text-sm font-medium text-ink">Ön Yazı (opsiyonel)</span>
            <textarea
              id="cover-letter"
              rows={5}
              value={coverLetter}
              onChange={(event) => setCoverLetter(event.target.value)}
              placeholder="Neden bu role uygun olduğunu kısaca anlatabilirsin..."
              className={cn(
                'w-full resize-y rounded-xl border border-surface bg-background px-3.5 py-3 text-sm text-ink outline-none transition',
                'placeholder:text-ink-subtle focus:border-primary focus:bg-white focus:ring-4 focus:ring-primary/10',
              )}
            />
          </label>

          {errorMessage ? (
            <p className="rounded-lg border border-danger/20 bg-red-50 px-3 py-2 text-sm text-danger" role="alert">
              {errorMessage}
            </p>
          ) : null}

          <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <Button type="button" variant="outline" onClick={handleClose} disabled={createApplication.isPending}>
              Vazgeç
            </Button>
            <Button
              type="button"
              onClick={handleSubmit}
              loading={createApplication.isPending}
              loadingLabel="Gönderiliyor..."
            >
              Başvuruyu Gönder
            </Button>
          </div>
        </div>
      )}
    </Modal>
  );
}

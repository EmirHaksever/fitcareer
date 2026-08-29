import { useState } from 'react';
import { CheckCircle2, XCircle } from 'lucide-react';
import { Button } from '@/components/ui/Button';
import { Card, CardBody } from '@/components/ui/Card';
import { ConfirmDialog } from '@/components/ui/ConfirmDialog';
import { EmptyState, Skeleton } from '@/components/ui/States';
import { usePendingCompanies, useVerifyCompany } from '@/hooks/useAdminCompanies';
import { getApiErrorMessage } from '@/api/client';
import type { CompanyProfile } from '@/types/company';
import type { CompanyVerificationAction } from '@/types/adminCompany';
import { formatLocation } from '@/utils/format';

type PendingAction = {
  company: CompanyProfile;
  action: CompanyVerificationAction;
};

export function AdminCompaniesPage() {
  const { data, isLoading, isError, refetch } = usePendingCompanies();
  const verifyMutation = useVerifyCompany();
  const [pendingAction, setPendingAction] = useState<PendingAction | null>(null);
  const [bannerError, setBannerError] = useState<string | null>(null);
  const [bannerMessage, setBannerMessage] = useState<string | null>(null);

  const companies = data?.items ?? [];
  const total = data?.pagination.total ?? 0;

  function handleConfirm() {
    if (!pendingAction) return;

    setBannerError(null);
    verifyMutation.mutate(
      { companyId: pendingAction.company.id, action: pendingAction.action },
      {
        onSuccess: () => {
          setBannerMessage(
            pendingAction.action === 'approve'
              ? `"${pendingAction.company.name}" onaylandı.`
              : `"${pendingAction.company.name}" reddedildi.`,
          );
          setPendingAction(null);
        },
        onError: (error) => {
          setBannerError(getApiErrorMessage(error, 'İşlem gerçekleştirilemedi.'));
        },
      },
    );
  }

  return (
    <div className="space-y-6">
      <section className="space-y-2">
        <p className="text-sm font-medium text-primary">Admin</p>
        <h1 className="text-3xl font-bold tracking-tight text-ink">Şirket Doğrulama</h1>
        <p className="text-sm text-ink-muted">
          Doğrulama isteği gönderen şirketleri incele, onayla veya reddet.
        </p>
      </section>

      {bannerMessage ? (
        <div className="flex items-center gap-2 rounded-xl border border-success/30 bg-success/10 px-4 py-3 text-sm text-primary-800">
          <CheckCircle2 className="h-4 w-4" aria-hidden="true" />
          {bannerMessage}
        </div>
      ) : null}

      {bannerError ? (
        <div className="rounded-xl border border-danger/20 bg-danger/5 px-4 py-3 text-sm text-danger">
          {bannerError}
        </div>
      ) : null}

      {isLoading ? (
        <div className="space-y-3">
          <Skeleton className="h-20" />
          <Skeleton className="h-20" />
        </div>
      ) : null}

      {isError ? (
        <EmptyState
          title="Bekleyen şirketler yüklenemedi"
          description="Liste getirilemedi. Lütfen tekrar dene."
          action={
            <Button type="button" onClick={() => void refetch()}>
              Tekrar Dene
            </Button>
          }
        />
      ) : null}

      {!isLoading && !isError && companies.length === 0 ? (
        <EmptyState
          title="Bekleyen doğrulama isteği yok"
          description="Şu anda onay bekleyen bir şirket bulunmuyor."
        />
      ) : null}

      {!isLoading && !isError && companies.length > 0 ? (
        <>
          <p className="text-sm text-ink-muted">
            {total.toLocaleString('tr-TR')} şirket onay bekliyor
          </p>

          <div className="hidden lg:block">
            <Card>
              <CardBody className="overflow-x-auto p-0">
                <table className="min-w-full text-left">
                  <thead className="border-b border-surface bg-background text-xs uppercase tracking-wide text-ink-subtle">
                    <tr>
                      <th className="px-4 py-3 font-medium">Şirket</th>
                      <th className="px-4 py-3 font-medium">Sektör</th>
                      <th className="px-4 py-3 font-medium">Konum</th>
                      <th className="px-4 py-3 font-medium">Web Sitesi</th>
                      <th className="px-4 py-3 font-medium text-right">İşlem</th>
                    </tr>
                  </thead>
                  <tbody>
                    {companies.map((company) => (
                      <tr key={company.id} className="border-b border-surface last:border-b-0">
                        <td className="px-4 py-4">
                          <p className="font-medium text-ink">{company.name}</p>
                          <p className="text-xs text-ink-subtle">{company.slug}</p>
                        </td>
                        <td className="px-4 py-4 text-sm text-ink-muted">
                          {company.industry ?? '—'}
                        </td>
                        <td className="px-4 py-4 text-sm text-ink-muted">
                          {formatLocation(company.city, company.country)}
                        </td>
                        <td className="px-4 py-4 text-sm text-ink-muted">
                          {company.website ? (
                            <a
                              href={company.website}
                              target="_blank"
                              rel="noreferrer"
                              className="text-primary hover:underline"
                            >
                              {company.website}
                            </a>
                          ) : (
                            '—'
                          )}
                        </td>
                        <td className="px-4 py-4">
                          <div className="flex justify-end gap-2">
                            <Button
                              type="button"
                              size="sm"
                              onClick={() => setPendingAction({ company, action: 'approve' })}
                            >
                              <CheckCircle2 className="h-4 w-4" aria-hidden="true" />
                              Onayla
                            </Button>
                            <Button
                              type="button"
                              variant="outline"
                              size="sm"
                              onClick={() => setPendingAction({ company, action: 'reject' })}
                            >
                              <XCircle className="h-4 w-4" aria-hidden="true" />
                              Reddet
                            </Button>
                          </div>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </CardBody>
            </Card>
          </div>

          <div className="space-y-3 lg:hidden">
            {companies.map((company) => (
              <Card key={company.id}>
                <CardBody className="space-y-3">
                  <div>
                    <p className="font-medium text-ink">{company.name}</p>
                    <p className="text-xs text-ink-subtle">{company.slug}</p>
                  </div>
                  <div className="space-y-1 text-sm text-ink-muted">
                    <p>Sektör: {company.industry ?? '—'}</p>
                    <p>Konum: {formatLocation(company.city, company.country)}</p>
                    {company.website ? (
                      <p>
                        Web:{' '}
                        <a
                          href={company.website}
                          target="_blank"
                          rel="noreferrer"
                          className="text-primary hover:underline"
                        >
                          {company.website}
                        </a>
                      </p>
                    ) : null}
                  </div>
                  <div className="flex gap-2">
                    <Button
                      type="button"
                      size="sm"
                      className="flex-1"
                      onClick={() => setPendingAction({ company, action: 'approve' })}
                    >
                      <CheckCircle2 className="h-4 w-4" aria-hidden="true" />
                      Onayla
                    </Button>
                    <Button
                      type="button"
                      variant="outline"
                      size="sm"
                      className="flex-1"
                      onClick={() => setPendingAction({ company, action: 'reject' })}
                    >
                      <XCircle className="h-4 w-4" aria-hidden="true" />
                      Reddet
                    </Button>
                  </div>
                </CardBody>
              </Card>
            ))}
          </div>
        </>
      ) : null}

      <ConfirmDialog
        open={pendingAction !== null}
        title={pendingAction?.action === 'approve' ? 'Şirketi onayla' : 'Şirketi reddet'}
        description={
          pendingAction
            ? `"${pendingAction.company.name}" şirketini ${
                pendingAction.action === 'approve' ? 'onaylamak' : 'reddetmek'
              } istediğine emin misin?`
            : ''
        }
        confirmLabel={pendingAction?.action === 'approve' ? 'Onayla' : 'Reddet'}
        loading={verifyMutation.isPending}
        onConfirm={handleConfirm}
        onClose={() => setPendingAction(null)}
      />
    </div>
  );
}

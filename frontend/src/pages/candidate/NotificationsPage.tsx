import { Bell } from 'lucide-react';
import { Link } from 'react-router-dom';
import { NotificationListItem } from '@/components/notifications/NotificationListItem';
import { Button } from '@/components/ui/Button';
import { Card, CardBody } from '@/components/ui/Card';
import { EmptyState, Skeleton } from '@/components/ui/States';
import {
  useMarkAllNotificationsRead,
  useMarkNotificationRead,
  useNotificationUnreadCount,
  useNotifications,
} from '@/hooks/useNotifications';

export function NotificationsPage() {
  const { data, isLoading, isError, refetch } = useNotifications();
  const { data: unreadData } = useNotificationUnreadCount();
  const markRead = useMarkNotificationRead();
  const markAllRead = useMarkAllNotificationsRead();

  const items = data?.items ?? [];
  const hasUnread = (unreadData?.unread_count ?? 0) > 0;

  if (isLoading) {
    return (
      <div className="space-y-4">
        <Skeleton className="h-10 w-48" />
        <Skeleton className="h-24" />
        <Skeleton className="h-24" />
      </div>
    );
  }

  if (isError) {
    return (
      <EmptyState
        title="Bildirimler yüklenemedi"
        description="Bağlantınızı kontrol edip tekrar deneyin."
        action={
          <Button type="button" onClick={() => void refetch()}>
            Tekrar Dene
          </Button>
        }
      />
    );
  }

  return (
    <div className="space-y-6">
      <header className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div className="space-y-1">
          <h1 className="text-2xl font-bold tracking-tight text-ink sm:text-3xl">Bildirimler</h1>
          <p className="text-sm text-ink-muted">
            {data?.pagination.total
              ? `${data.pagination.total.toLocaleString('tr-TR')} bildirim`
              : 'Kariyer hareketlerin ve ilan güncellemelerin burada listelenir.'}
          </p>
        </div>
        {hasUnread ? (
          <Button
            type="button"
            variant="outline"
            size="sm"
            className="w-full sm:w-auto"
            loading={markAllRead.isPending}
            onClick={() => void markAllRead.mutateAsync()}
          >
            Tümünü okundu işaretle
          </Button>
        ) : null}
      </header>

      {items.length === 0 ? (
        <Card>
          <CardBody className="flex flex-col items-center gap-4 py-12 text-center">
            <div className="flex h-14 w-14 items-center justify-center rounded-full bg-primary/10 text-primary">
              <Bell className="h-7 w-7" aria-hidden="true" />
            </div>
            <div className="space-y-1">
              <h2 className="text-lg font-semibold text-ink">Henüz bildirim yok</h2>
              <p className="max-w-md text-sm text-ink-muted">
                Başvuru durumu güncellemeleri ve diğer kariyer bildirimleri burada görünecek.
              </p>
            </div>
            <Link to="/jobs">
              <Button variant="outline">İş İlanlarına Göz At</Button>
            </Link>
          </CardBody>
        </Card>
      ) : (
        <div className="space-y-3">
          {items.map((notification) => (
            <NotificationListItem
              key={notification.id}
              notification={notification}
              onMarkRead={(id) => void markRead.mutateAsync(id)}
            />
          ))}
        </div>
      )}
    </div>
  );
}

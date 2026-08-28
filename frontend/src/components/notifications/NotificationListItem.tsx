import { BriefcaseBusiness, Bell, Megaphone, Sparkles } from 'lucide-react';
import { Link } from 'react-router-dom';
import type { CandidateNotification } from '@/types/api';
import { formatNotificationDate, getNotificationCategoryLabel } from '@/utils/notifications';
import { cn } from '@/utils/format';

interface NotificationListItemProps {
  notification: CandidateNotification;
  onMarkRead: (id: string) => void;
}

function CategoryIcon({ category }: { category: CandidateNotification['category'] }) {
  const className = 'h-5 w-5';

  switch (category) {
    case 'application_update':
      return <BriefcaseBusiness className={className} aria-hidden="true" />;
    case 'job_match':
      return <Sparkles className={className} aria-hidden="true" />;
    case 'promotion':
      return <Megaphone className={className} aria-hidden="true" />;
    default:
      return <Bell className={className} aria-hidden="true" />;
  }
}

export function NotificationListItem({ notification, onMarkRead }: NotificationListItemProps) {
  const content = (
    <div
      className={cn(
        'flex gap-3 rounded-xl border px-4 py-3 transition',
        notification.is_read
          ? 'border-surface bg-white'
          : 'border-primary/20 bg-primary/5',
      )}
    >
      <div
        className={cn(
          'flex h-10 w-10 shrink-0 items-center justify-center rounded-xl',
          notification.is_read ? 'bg-background text-ink-muted' : 'bg-white text-primary',
        )}
      >
        <CategoryIcon category={notification.category} />
      </div>
      <div className="min-w-0 flex-1 space-y-1">
        <div className="flex flex-wrap items-center gap-2">
          <p className="text-sm font-semibold text-ink">{notification.title}</p>
          {!notification.is_read ? (
            <span className="rounded-full bg-primary/10 px-2 py-0.5 text-[11px] font-semibold text-primary">
              Yeni
            </span>
          ) : null}
        </div>
        <p className="break-words text-sm leading-6 text-ink-muted">{notification.body}</p>
        <div className="flex flex-wrap items-center gap-2 text-xs text-ink-subtle">
          <span>{getNotificationCategoryLabel(notification.category)}</span>
          <span aria-hidden="true">·</span>
          <time dateTime={notification.created_at}>{formatNotificationDate(notification.created_at)}</time>
        </div>
      </div>
    </div>
  );

  function handleActivate() {
    if (!notification.is_read) {
      onMarkRead(notification.id);
    }
  }

  if (notification.action_path) {
    return (
      <Link
        to={notification.action_path}
        onClick={handleActivate}
        className="block min-w-0 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/30"
      >
        {content}
      </Link>
    );
  }

  return (
    <button
      type="button"
      onClick={handleActivate}
      className="block w-full min-w-0 text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/30"
    >
      {content}
    </button>
  );
}

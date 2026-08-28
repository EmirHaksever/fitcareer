import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { notificationsApi } from '@/api/notifications';

export const NOTIFICATIONS_QUERY_KEY = ['candidate-notifications'] as const;
export const NOTIFICATIONS_UNREAD_QUERY_KEY = ['candidate-notifications-unread'] as const;

export function useNotifications(page = 1) {
  return useQuery({
    queryKey: [...NOTIFICATIONS_QUERY_KEY, page],
    queryFn: () => notificationsApi.list({ page, per_page: 20 }),
  });
}

export function useNotificationUnreadCount(enabled = true) {
  return useQuery({
    queryKey: NOTIFICATIONS_UNREAD_QUERY_KEY,
    queryFn: () => notificationsApi.unreadCount(),
    staleTime: 30_000,
    enabled,
  });
}

export function useMarkNotificationRead() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (notificationId: string) => notificationsApi.markRead(notificationId),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: NOTIFICATIONS_QUERY_KEY });
      void queryClient.invalidateQueries({ queryKey: NOTIFICATIONS_UNREAD_QUERY_KEY });
    },
  });
}

export function useMarkAllNotificationsRead() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: () => notificationsApi.markAllRead(),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: NOTIFICATIONS_QUERY_KEY });
      void queryClient.invalidateQueries({ queryKey: NOTIFICATIONS_UNREAD_QUERY_KEY });
    },
  });
}

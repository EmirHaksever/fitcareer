import { apiClient } from '@/api/client';
import type {
  ApiResponse,
  CandidateNotification,
  NotificationUnreadCount,
  PaginatedNotifications,
} from '@/types/api';

export const notificationsApi = {
  async list(params: { page?: number; per_page?: number } = {}): Promise<PaginatedNotifications> {
    const { data } = await apiClient.get<ApiResponse<PaginatedNotifications>>('/candidate/notifications', {
      params,
    });
    return data.data;
  },

  async unreadCount(): Promise<NotificationUnreadCount> {
    const { data } = await apiClient.get<ApiResponse<NotificationUnreadCount>>(
      '/candidate/notifications/unread-count',
    );
    return data.data;
  },

  async markRead(notificationId: string): Promise<CandidateNotification> {
    const { data } = await apiClient.patch<ApiResponse<CandidateNotification>>(
      `/candidate/notifications/${notificationId}/read`,
    );
    return data.data;
  },

  async markAllRead(): Promise<{ updated_count: number }> {
    const { data } = await apiClient.post<ApiResponse<{ updated_count: number }>>(
      '/candidate/notifications/mark-all-read',
    );
    return data.data;
  },
};

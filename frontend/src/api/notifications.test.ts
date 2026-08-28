import { beforeEach, describe, expect, it, vi } from 'vitest';
import { notificationsApi } from '@/api/notifications';
import { apiClient } from '@/api/client';
import type { CandidateNotification } from '@/types/api';

vi.mock('@/api/client', () => ({
  apiClient: {
    get: vi.fn(),
    patch: vi.fn(),
    post: vi.fn(),
  },
}));

const mockedGet = vi.mocked(apiClient.get);
const mockedPatch = vi.mocked(apiClient.patch);
const mockedPost = vi.mocked(apiClient.post);

const sampleNotification: CandidateNotification = {
  id: '11111111-1111-1111-1111-111111111111',
  category: 'application_update',
  title: 'Başvuru durumu güncellendi',
  body: 'Senior PHP Developer ilanı için: Başvurunuz inceleniyor.',
  action_path: '/applications/1',
  is_read: false,
  read_at: null,
  created_at: '2026-08-13T10:00:00+03:00',
};

describe('notificationsApi', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('lists notifications with pagination envelope', async () => {
    mockedGet.mockResolvedValueOnce({
      data: {
        success: true,
        message: 'Notifications retrieved.',
        data: {
          items: [sampleNotification],
          pagination: {
            current_page: 1,
            per_page: 20,
            total: 1,
            last_page: 1,
          },
        },
        errors: null,
      },
    });

    const result = await notificationsApi.list({ page: 1, per_page: 20 });

    expect(mockedGet).toHaveBeenCalledWith('/candidate/notifications', {
      params: { page: 1, per_page: 20 },
    });
    expect(result.items[0]?.title).toBe('Başvuru durumu güncellendi');
    expect(result.items[0]?.is_read).toBe(false);
  });

  it('fetches unread count', async () => {
    mockedGet.mockResolvedValueOnce({
      data: {
        success: true,
        message: 'Unread notification count retrieved.',
        data: { unread_count: 3 },
        errors: null,
      },
    });

    const result = await notificationsApi.unreadCount();
    expect(result.unread_count).toBe(3);
  });

  it('marks a single notification as read', async () => {
    mockedPatch.mockResolvedValueOnce({
      data: {
        success: true,
        message: 'Notification marked as read.',
        data: { ...sampleNotification, is_read: true, read_at: '2026-08-13T11:00:00+03:00' },
        errors: null,
      },
    });

    const result = await notificationsApi.markRead(sampleNotification.id);
    expect(mockedPatch).toHaveBeenCalledWith(`/candidate/notifications/${sampleNotification.id}/read`);
    expect(result.is_read).toBe(true);
  });

  it('marks all notifications as read', async () => {
    mockedPost.mockResolvedValueOnce({
      data: {
        success: true,
        message: 'All notifications marked as read.',
        data: { updated_count: 2 },
        errors: null,
      },
    });

    const result = await notificationsApi.markAllRead();
    expect(mockedPost).toHaveBeenCalledWith('/candidate/notifications/mark-all-read');
    expect(result.updated_count).toBe(2);
  });
});

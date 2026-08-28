<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Candidate;

use App\Http\Controllers\Controller;
use App\Http\Resources\Candidate\NotificationResource;
use App\Services\Notification\CandidateNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class NotificationController extends Controller
{
    public function __construct(
        private readonly CandidateNotificationService $notificationService,
    ) {}

    #[OA\Get(
        path: '/candidate/notifications',
        summary: 'List candidate in-app notifications',
        security: [['sanctum' => []]],
        tags: ['Candidate Notifications'],
        responses: [
            new OA\Response(response: 200, description: 'Notifications returned'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ],
    )]
    public function index(Request $request): JsonResponse
    {
        $paginator = $this->notificationService->listForUser(
            $request->user(),
            (int) $request->query('page', 1),
            $request->query('per_page') !== null ? (int) $request->query('per_page') : null,
        );

        return $this->successResponse([
            'items' => NotificationResource::collection($paginator->items())->resolve(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ], 'Notifications retrieved.');
    }

    #[OA\Get(
        path: '/candidate/notifications/unread-count',
        summary: 'Unread notification count',
        security: [['sanctum' => []]],
        tags: ['Candidate Notifications'],
        responses: [
            new OA\Response(response: 200, description: 'Unread count returned'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ],
    )]
    public function unreadCount(Request $request): JsonResponse
    {
        return $this->successResponse([
            'unread_count' => $this->notificationService->unreadCountForUser($request->user()),
        ], 'Unread notification count retrieved.');
    }

    #[OA\Patch(
        path: '/candidate/notifications/{notification}/read',
        summary: 'Mark a notification as read',
        security: [['sanctum' => []]],
        tags: ['Candidate Notifications'],
        responses: [
            new OA\Response(response: 200, description: 'Notification marked as read'),
            new OA\Response(response: 404, description: 'Not found'),
        ],
    )]
    public function markRead(Request $request, string $notification): JsonResponse
    {
        $record = $this->notificationService->markAsRead($request->user(), $notification);

        return $this->successResponse(
            new NotificationResource($record),
            'Notification marked as read.',
        );
    }

    #[OA\Post(
        path: '/candidate/notifications/mark-all-read',
        summary: 'Mark all notifications as read',
        security: [['sanctum' => []]],
        tags: ['Candidate Notifications'],
        responses: [
            new OA\Response(response: 200, description: 'Notifications marked as read'),
        ],
    )]
    public function markAllRead(Request $request): JsonResponse
    {
        $updated = $this->notificationService->markAllAsRead($request->user());

        return $this->successResponse([
            'updated_count' => $updated,
        ], 'All notifications marked as read.');
    }
}

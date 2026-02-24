<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(
        protected NotificationService $notificationService
    ) {}

    /**
     * Get all notifications for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = min($request->input('per_page', 20), 100);

        $notifications = $this->notificationService->getNotificationsForUser($user, $perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'notifications' => $notifications->items(),
                'meta' => [
                    'current_page' => $notifications->currentPage(),
                    'last_page' => $notifications->lastPage(),
                    'per_page' => $notifications->perPage(),
                    'total' => $notifications->total(),
                ],
            ],
        ]);
    }

    /**
     * Get unread notifications count.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $user = $request->user();
        $count = $this->notificationService->getUnreadCount($user);

        return response()->json([
            'success' => true,
            'data' => [
                'unread_count' => $count,
            ],
        ]);
    }

    /**
     * Get unread notifications.
     */
    public function unread(Request $request): JsonResponse
    {
        $user = $request->user();
        $notifications = $this->notificationService->getUnreadNotificationsForUser($user);

        return response()->json([
            'success' => true,
            'data' => [
                'notifications' => $notifications,
            ],
        ]);
    }

    /**
     * Mark a notification as read.
     */
    public function markAsRead(Request $request, Notification $notification): JsonResponse
    {
        $user = $request->user();

        if ($notification->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $this->notificationService->markAsRead($notification);

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read',
        ]);
    }

    /**
     * Mark a notification as unread.
     */
    public function markAsUnread(Request $request, Notification $notification): JsonResponse
    {
        $user = $request->user();

        if ($notification->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $this->notificationService->markAsUnread($notification);

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as unread',
        ]);
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $user = $request->user();
        $count = $this->notificationService->markAllAsRead($user);

        return response()->json([
            'success' => true,
            'message' => 'All notifications marked as read',
            'data' => [
                'marked_count' => $count,
            ],
        ]);
    }

    /**
     * Delete a notification.
     */
    public function destroy(Request $request, Notification $notification): JsonResponse
    {
        $user = $request->user();

        if ($notification->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $this->notificationService->deleteNotification($notification);

        return response()->json([
            'success' => true,
            'message' => 'Notification deleted',
        ]);
    }
}

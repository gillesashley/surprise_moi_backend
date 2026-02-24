<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

class NotificationService
{
    public function getNotificationsForUser(User $user, int $perPage = 20): LengthAwarePaginator
    {
        return $user->notifications()
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    public function getUnreadNotificationsForUser(User $user): \Illuminate\Database\Eloquent\Collection
    {
        return $user->unreadNotifications()
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getUnreadCount(User $user): int
    {
        return $user->getUnreadNotificationsCount();
    }

    public function createNotification(User $user, string $type, string $title, string $message, array $data = []): Notification
    {
        return Notification::create([
            'user_id' => $user->id,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => $data,
        ]);
    }

    public function markAsRead(Notification $notification): void
    {
        $notification->markAsRead();
    }

    public function markAsUnread(Notification $notification): void
    {
        $notification->markAsUnread();
    }

    public function markAllAsRead(User $user): int
    {
        return $user->unreadNotifications()->update(['read_at' => now()]);
    }

    public function deleteNotification(Notification $notification): bool
    {
        return $notification->delete();
    }

    public function createVendorNotification(int $userId, string $vendorName, string $action, array $data = []): Notification
    {
        $user = User::findOrFail($userId);

        $titles = [
            'submitted' => 'New Vendor Application',
            'approved' => 'Vendor Approved',
            'rejected' => 'Vendor Rejected',
        ];

        $messages = [
            'submitted' => "A new vendor application has been submitted by {$vendorName}.",
            'approved' => "Vendor {$vendorName} has been approved.",
            'rejected' => "Vendor {$vendorName} has been rejected.",
        ];

        return $this->createNotification(
            $user,
            "vendor_{$action}",
            $titles[$action] ?? 'Vendor Update',
            $messages[$action] ?? "Vendor {$vendorName} has been {$action}.",
            $data
        );
    }

    public function createChatNotification(int $userId, string $senderName, string $preview, array $data = []): Notification
    {
        $user = User::findOrFail($userId);

        return $this->createNotification(
            $user,
            'chat_message',
            'New Message',
            "{$senderName}: {$preview}",
            $data
        );
    }

    public function createSystemNotification(int $userId, string $title, string $message, array $data = []): Notification
    {
        $user = User::findOrFail($userId);

        return $this->createNotification(
            $user,
            'system',
            $title,
            $message,
            $data
        );
    }
}

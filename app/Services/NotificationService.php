<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Pagination\LengthAwarePaginator;

class NotificationService
{
    public function getNotificationsForUser(User $user, int $perPage = 20): LengthAwarePaginator
    {
        return $user->notifications()->latest()->paginate($perPage);
    }

    public function getUnreadNotificationsForUser(User $user): \Illuminate\Database\Eloquent\Collection
    {
        return $user->unreadNotifications()->latest()->get();
    }

    public function getUnreadCount(User $user): int
    {
        return $user->unreadNotifications()->count();
    }

    public function markAsRead(DatabaseNotification $notification): void
    {
        $notification->markAsRead();
    }

    public function markAsUnread(DatabaseNotification $notification): void
    {
        $notification->markAsUnread();
    }

    public function markAllAsRead(User $user): int
    {
        return $user->unreadNotifications()->update(['read_at' => now()]);
    }

    public function deleteNotification(DatabaseNotification $notification): bool
    {
        return $notification->delete();
    }
}

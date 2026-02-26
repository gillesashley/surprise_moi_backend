import { Bell, Check, Trash2 } from 'lucide-react';
import { useNotifications } from '@/hooks/useNotifications';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

interface NotificationDropdownProps {
    onClose: () => void;
}

export function NotificationDropdown({ onClose }: NotificationDropdownProps) {
    const {
        notifications,
        unreadCount,
        isLoading,
        markAsRead,
        markAllAsRead,
        deleteNotification,
    } = useNotifications();

    const handleNotificationClick = async (notificationId: string) => {
        await markAsRead(notificationId);
    };

    const handleMarkAllRead = async () => {
        await markAllAsRead();
    };

    const handleDelete = async (e: React.MouseEvent, notificationId: string) => {
        e.stopPropagation();
        await deleteNotification(notificationId);
    };

    return (
        <div className="flex flex-col">
            <div className="flex items-center justify-between border-b px-4 py-3">
                <h3 className="font-semibold">Notifications</h3>
                {unreadCount > 0 && (
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={handleMarkAllRead}
                        className="text-xs"
                    >
                        <Check className="mr-1 h-3 w-3" />
                        Mark all read
                    </Button>
                )}
            </div>

            <div className="h-80 overflow-y-auto">
                {isLoading ? (
                    <div className="flex items-center justify-center p-4">
                        <span className="text-sm text-muted-foreground">
                            Loading...
                        </span>
                    </div>
                ) : notifications.length === 0 ? (
                    <div className="flex flex-col items-center justify-center p-4 text-center">
                        <Bell className="mb-2 h-8 w-8 text-muted-foreground" />
                        <span className="text-sm text-muted-foreground">
                            No notifications yet
                        </span>
                    </div>
                ) : (
                    <div className="divide-y">
                        {notifications.map((notification) => (
                            <div
                                key={notification.id}
                                onClick={() =>
                                    handleNotificationClick(notification.id)
                                }
                                className={cn(
                                    'flex cursor-pointer items-start gap-3 px-4 py-3 transition-colors hover:bg-accent',
                                    !notification.read_at && 'bg-primary/5',
                                )}
                            >
                                <div className="flex-1 space-y-1">
                                    <p
                                        className={cn(
                                            'text-sm',
                                            !notification.read_at &&
                                                'font-medium',
                                        )}
                                    >
                                        {notification.title}
                                    </p>
                                    <p className="text-xs text-muted-foreground line-clamp-2">
                                        {notification.message}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {formatTimeAgo(notification.created_at)}
                                    </p>
                                </div>
                                <div className="flex flex-col gap-1">
                                    {!notification.read_at && (
                                        <span className="h-2 w-2 rounded-full bg-primary" />
                                    )}
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        className="h-6 w-6 opacity-50 hover:opacity-100"
                                        onClick={(e) =>
                                            handleDelete(e, notification.id)
                                        }
                                    >
                                        <Trash2 className="h-3 w-3" />
                                    </Button>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </div>
    );
}

function formatTimeAgo(dateString: string): string {
    const date = new Date(dateString);
    const now = new Date();
    const diffInSeconds = Math.floor(
        (now.getTime() - date.getTime()) / 1000,
    );

    if (diffInSeconds < 60) {
        return 'Just now';
    }

    const diffInMinutes = Math.floor(diffInSeconds / 60);
    if (diffInMinutes < 60) {
        return `${diffInMinutes}m ago`;
    }

    const diffInHours = Math.floor(diffInMinutes / 60);
    if (diffInHours < 24) {
        return `${diffInHours}h ago`;
    }

    const diffInDays = Math.floor(diffInHours / 24);
    if (diffInDays < 7) {
        return `${diffInDays}d ago`;
    }

    return date.toLocaleDateString();
}

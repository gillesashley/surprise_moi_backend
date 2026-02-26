import {
    createContext,
    useCallback,
    useEffect,
    useState,
    type ReactNode,
} from 'react';
import {
    notificationApi,
    type Notification,
} from '@/lib/notifications/api';

interface NotificationContextValue {
    notifications: Notification[];
    unreadCount: number;
    isLoading: boolean;
    error: string | null;
    fetchNotifications: () => Promise<void>;
    fetchUnreadCount: () => Promise<void>;
    markAsRead: (notificationId: string) => Promise<void>;
    markAsUnread: (notificationId: string) => Promise<void>;
    markAllAsRead: () => Promise<void>;
    deleteNotification: (notificationId: string) => Promise<void>;
    addNotification: (notification: Notification) => void;
}

export const NotificationContext = createContext<NotificationContextValue | null>(
    null,
);

interface NotificationProviderProps {
    children: ReactNode;
}

export function NotificationProvider({ children }: NotificationProviderProps) {
    const [notifications, setNotifications] = useState<Notification[]>([]);
    const [unreadCount, setUnreadCount] = useState(0);
    const [isLoading, setIsLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    const fetchNotifications = useCallback(async () => {
        try {
            setIsLoading(true);
            setError(null);
            const response = await notificationApi.getAll();
            setNotifications(response.data.notifications);
            setUnreadCount(response.data.notifications.filter((n) => !n.read_at).length);
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Failed to fetch notifications');
        } finally {
            setIsLoading(false);
        }
    }, []);

    const fetchUnreadCount = useCallback(async () => {
        try {
            const response = await notificationApi.getUnreadCount();
            setUnreadCount(response.data.unread_count);
        } catch (err) {
            console.error('Failed to fetch unread count:', err);
        }
    }, []);

    const markAsRead = useCallback(async (notificationId: string) => {
        try {
            await notificationApi.markAsRead(notificationId);
            setNotifications((prev) =>
                prev.map((n) =>
                    n.id === notificationId
                        ? { ...n, read_at: new Date().toISOString() }
                        : n,
                ),
            );
            setUnreadCount((prev) => Math.max(0, prev - 1));
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Failed to mark as read');
        }
    }, []);

    const markAsUnread = useCallback(async (notificationId: string) => {
        try {
            await notificationApi.markAsUnread(notificationId);
            setNotifications((prev) =>
                prev.map((n) =>
                    n.id === notificationId ? { ...n, read_at: null } : n,
                ),
            );
            setUnreadCount((prev) => prev + 1);
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Failed to mark as unread');
        }
    }, []);

    const markAllAsRead = useCallback(async () => {
        try {
            await notificationApi.markAllAsRead();
            setNotifications((prev) =>
                prev.map((n) => ({ ...n, read_at: n.read_at ?? new Date().toISOString() })),
            );
            setUnreadCount(0);
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Failed to mark all as read');
        }
    }, []);

    const deleteNotification = useCallback(async (notificationId: string) => {
        try {
            await notificationApi.delete(notificationId);
            const deletedNotification = notifications.find((n) => n.id === notificationId);
            setNotifications((prev) => prev.filter((n) => n.id !== notificationId));
            if (deletedNotification && !deletedNotification.read_at) {
                setUnreadCount((prev) => Math.max(0, prev - 1));
            }
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Failed to delete notification');
        }
    }, [notifications]);

    const addNotification = useCallback((notification: Notification) => {
        setNotifications((prev) => [notification, ...prev]);
        if (!notification.read_at) {
            setUnreadCount((prev) => prev + 1);
        }
    }, []);

    useEffect(() => {
        fetchNotifications();
    }, [fetchNotifications]);

    return (
        <NotificationContext.Provider
            value={{
                notifications,
                unreadCount,
                isLoading,
                error,
                fetchNotifications,
                fetchUnreadCount,
                markAsRead,
                markAsUnread,
                markAllAsRead,
                deleteNotification,
                addNotification,
            }}
        >
            {children}
        </NotificationContext.Provider>
    );
}

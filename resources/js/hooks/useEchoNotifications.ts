import { useEffect } from 'react';
import echo from '@/lib/echo';
import { useNotifications } from '@/hooks/useNotifications';
import { showNotificationToast } from '@/components/notifications';
import type { Notification } from '@/lib/notifications/api';
import { usePage } from '@inertiajs/react';

const isDevelopment = import.meta.env.DEV;

interface BroadcastNotification {
    id: string;
    type: string;
    title: string;
    message: string;
    action_url?: string;
    actor?: {
        id: number;
        name: string;
        avatar: string | null;
    };
    [key: string]: unknown;
}

export function useEchoNotifications() {
    const { addNotification, fetchUnreadCount } = useNotifications();
    const { auth } = usePage<{ auth: { user: { id: number } } }>().props;

    useEffect(() => {
        if (!auth?.user?.id) {
            return;
        }

        const userId = auth.user.id;

        if (isDevelopment) {
            console.log(
                `[useEchoNotifications] Subscribing to user.${userId} channel...`,
            );
        }

        const channel = echo.private(`user.${userId}`);

        channel.notification((data: BroadcastNotification) => {
            if (isDevelopment) {
                console.log('[useEchoNotifications] Notification received:', data);
            }

            const notification: Notification = {
                id: data.id,
                type: data.type ?? 'unknown',
                title: data.title ?? '',
                message: data.message ?? '',
                action_url: data.action_url ?? null,
                actor: data.actor ?? null,
                data: data,
                read_at: null,
                created_at: new Date().toISOString(),
                updated_at: new Date().toISOString(),
            };

            addNotification(notification);
            showNotificationToast(notification);
            fetchUnreadCount();
        });

        channel.error((error: unknown) => {
            if (isDevelopment) {
                console.error('[useEchoNotifications] Channel error:', error);
            }
        });

        return () => {
            if (isDevelopment) {
                console.log(
                    `[useEchoNotifications] Leaving user.${userId} channel`,
                );
            }
            echo.leaveChannel(`private-user.${userId}`);
        };
    }, [auth?.user?.id, addNotification, fetchUnreadCount]);
}

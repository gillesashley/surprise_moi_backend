import { useEffect } from 'react';
import echo from '@/lib/echo';
import { useNotifications } from '@/hooks/useNotifications';
import { showNotificationToast } from '@/components/notifications';
import type { Notification } from '@/lib/notifications/api';

const isDevelopment = import.meta.env.DEV;

interface VendorApprovalEvent {
    vendor_application_id: number;
    user_id: number;
    user_name: string;
    user_email: string;
    submitted_at: string;
    message: string;
}

interface MessageSentEvent {
    conversation_id: number;
    sender_id: number;
    sender_name: string;
    message: string;
    preview?: string;
}

export function useEchoNotifications() {
    const { addNotification, fetchUnreadCount } = useNotifications();

    useEffect(() => {
        if (isDevelopment) {
            console.log(
                '📡 [useEchoNotifications] Hook mounted, subscribing to admin channel...',
            );
        }

        const channel = echo.private('admin');

        channel.listen(
            'vendor.approval.submitted',
            (event: VendorApprovalEvent) => {
                if (isDevelopment) {
                    console.log(
                        '✅ [useEchoNotifications] Vendor approval submitted:',
                        event,
                    );
                }

                const notification: Notification = {
                    id: `vendor-${event.vendor_application_id}-${Date.now()}`,
                    type: 'vendor_submitted',
                    title: 'New Vendor Application',
                    message: `${event.user_name} has submitted a vendor application`,
                    data: {
                        vendor_application_id: event.vendor_application_id,
                        user_id: event.user_id,
                        action_url: '/admin/vendors',
                    },
                    user_id: event.user_id,
                    read_at: null,
                    created_at: event.submitted_at,
                    updated_at: event.submitted_at,
                };

                addNotification(notification);
                showNotificationToast(notification);
                fetchUnreadCount();
            },
        );

        channel.listen(
            'vendor.approved',
            (event: VendorApprovalEvent) => {
                if (isDevelopment) {
                    console.log('✅ [useEchoNotifications] Vendor approved:', event);
                }

                const notification: Notification = {
                    id: `vendor-approved-${event.vendor_application_id}-${Date.now()}`,
                    type: 'vendor_approved',
                    title: 'Vendor Approved',
                    message: `${event.user_name}'s vendor application has been approved`,
                    data: {
                        vendor_application_id: event.vendor_application_id,
                        action_url: '/admin/vendors',
                    },
                    user_id: event.user_id,
                    read_at: null,
                    created_at: new Date().toISOString(),
                    updated_at: new Date().toISOString(),
                };

                addNotification(notification);
                showNotificationToast(notification);
                fetchUnreadCount();
            },
        );

        channel.listen(
            'vendor.rejected',
            (event: VendorApprovalEvent) => {
                if (isDevelopment) {
                    console.log('✅ [useEchoNotifications] Vendor rejected:', event);
                }

                const notification: Notification = {
                    id: `vendor-rejected-${event.vendor_application_id}-${Date.now()}`,
                    type: 'vendor_rejected',
                    title: 'Vendor Rejected',
                    message: `${event.user_name}'s vendor application has been rejected`,
                    data: {
                        vendor_application_id: event.vendor_application_id,
                        action_url: '/admin/vendors',
                    },
                    user_id: event.user_id,
                    read_at: null,
                    created_at: new Date().toISOString(),
                    updated_at: new Date().toISOString(),
                };

                addNotification(notification);
                showNotificationToast(notification);
                fetchUnreadCount();
            },
        );

        channel.listen(
            'message.sent',
            (event: MessageSentEvent) => {
                if (isDevelopment) {
                    console.log('✅ [useEchoNotifications] Message sent:', event);
                }

                const notification: Notification = {
                    id: `message-${event.conversation_id}-${Date.now()}`,
                    type: 'chat_message',
                    title: 'New Message',
                    message: `${event.sender_name}: ${event.preview || event.message}`,
                    data: {
                        conversation_id: event.conversation_id,
                        action_url: '/admin/chat',
                    },
                    user_id: event.sender_id,
                    read_at: null,
                    created_at: new Date().toISOString(),
                    updated_at: new Date().toISOString(),
                };

                addNotification(notification);
                showNotificationToast(notification);
                fetchUnreadCount();
            },
        );

        channel.error((error: unknown) => {
            if (isDevelopment) {
                console.error(
                    '❌ [useEchoNotifications] Channel error:',
                    error,
                );
            }
        });

        return () => {
            if (isDevelopment) {
                console.log(
                    '📡 [useEchoNotifications] Cleaning up - leaving admin channel',
                );
            }
            echo.leaveChannel('admin');
        };
    }, [addNotification, fetchUnreadCount]);
}

import { toast } from 'sonner';
import { X } from 'lucide-react';
import type { Notification } from '@/lib/notifications/api';
import { Button } from '@/components/ui/button';

interface ToastNotificationProps {
    notification: Notification;
    onDismiss?: () => void;
}

export function showNotificationToast(notification: Notification) {
    toast(notification.title, {
        description: notification.message,
        duration: 5000,
        action: notification.data?.action_url
            ? {
                  label: 'View',
                  onClick: () => {
                      window.location.href = notification.data?.action_url as string;
                  },
              }
            : undefined,
    });
}

export function ToastProvider() {
    return null;
}

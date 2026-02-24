import { AppContent } from '@/components/app-content';
import { AppHeader } from '@/components/app-header';
import { AppShell } from '@/components/app-shell';
import { NotificationProvider } from '@/context/NotificationContext';
import { useEchoNotifications } from '@/hooks/useEchoNotifications';
import { type BreadcrumbItem } from '@/types';
import type { PropsWithChildren } from 'react';
import { Toaster } from 'sonner';

function NotificationLayer({ children }: PropsWithChildren) {
    useEchoNotifications();

    return (
        <>
            {children}
            <Toaster position="bottom-right" richColors />
        </>
    );
}

export default function AppHeaderLayout({
    children,
    breadcrumbs,
}: PropsWithChildren<{ breadcrumbs?: BreadcrumbItem[] }>) {
    return (
        <NotificationProvider>
            <NotificationLayer>
                <AppShell>
                    <AppHeader breadcrumbs={breadcrumbs} />
                    <AppContent>{children}</AppContent>
                </AppShell>
            </NotificationLayer>
        </NotificationProvider>
    );
}

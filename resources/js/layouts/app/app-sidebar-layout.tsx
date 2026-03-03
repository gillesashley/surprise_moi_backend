import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { NotificationProvider } from '@/context/NotificationContext';
import { useEchoNotifications } from '@/hooks/useEchoNotifications';
import { type BreadcrumbItem } from '@/types';
import { type PropsWithChildren } from 'react';
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

export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
}: PropsWithChildren<{ breadcrumbs?: BreadcrumbItem[] }>) {
    return (
        <NotificationProvider>
            <NotificationLayer>
                <AppShell variant="sidebar">
                    <AppSidebar />
                    <AppContent variant="sidebar" style={{ overflowX: 'hidden' }}>
                        <AppSidebarHeader breadcrumbs={breadcrumbs} />
                        {children}
                    </AppContent>
                </AppShell>
            </NotificationLayer>
        </NotificationProvider>
    );
}

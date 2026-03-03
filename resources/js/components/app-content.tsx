import Box from '@mui/material/Box';
import { SidebarInset } from '@/components/ui/sidebar';
import * as React from 'react';

interface AppContentProps extends React.ComponentProps<'main'> {
    variant?: 'header' | 'sidebar';
}

export function AppContent({
    variant = 'header',
    children,
    ...props
}: AppContentProps) {
    if (variant === 'sidebar') {
        return <SidebarInset {...props}>{children}</SidebarInset>;
    }

    return (
        <Box
            component="main"
            sx={{
                mx: 'auto',
                display: 'flex',
                height: '100%',
                width: '100%',
                maxWidth: 1280,
                flex: 1,
                flexDirection: 'column',
                gap: 2,
                borderRadius: 3,
            }}
            {...props}
        >
            {children}
        </Box>
    );
}

import Box from '@mui/material/Box';
import { alpha } from '@mui/material/styles';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { SidebarTrigger, useSidebar } from '@/components/ui/sidebar';
import { type BreadcrumbItem as BreadcrumbItemType } from '@/types';

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    const { state } = useSidebar();
    const isCollapsed = state === 'collapsed';

    return (
        <Box
            component="header"
            sx={(theme) => ({
                display: 'flex',
                height: isCollapsed ? 48 : 64,
                flexShrink: 0,
                alignItems: 'center',
                gap: 1,
                borderBottom: 1,
                borderColor: alpha(theme.palette.divider, 0.5),
                px: { xs: 3, md: 2 },
                transition: 'all 0.2s linear',
            })}
        >
            <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                <SidebarTrigger style={{ marginLeft: -4 }} />
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </Box>
        </Box>
    );
}

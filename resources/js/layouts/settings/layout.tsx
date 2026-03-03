import Box from '@mui/material/Box';
import Divider from '@mui/material/Divider';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { isSameUrl, resolveUrl } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { edit } from '@/routes/profile';
import { index as vendorOnboarding } from '@/routes/settings/vendor-onboarding';
import { show } from '@/routes/two-factor';
import { edit as editPassword } from '@/routes/user-password';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { type PropsWithChildren } from 'react';

const sidebarNavItems: NavItem[] = [
    {
        title: 'Profile',
        href: edit(),
        icon: null,
    },
    {
        title: 'Password',
        href: editPassword(),
        icon: null,
    },
    {
        title: 'Two-Factor Auth',
        href: show(),
        icon: null,
    },
    {
        title: 'Appearance',
        href: editAppearance(),
        icon: null,
    },
];

const getConditionalNavItems = (): NavItem[] => {
    if (typeof window === 'undefined') {
        return [];
    }

    const { auth } = usePage<{ auth: { user: { role: string } } }>().props;
    const conditionalItems: NavItem[] = [];

    // Add Vendor Onboarding for admins and super admins
    if (auth?.user?.role === 'admin' || auth?.user?.role === 'super_admin') {
        conditionalItems.push({
            title: 'Vendor Onboarding',
            href: vendorOnboarding(),
            icon: null,
        });
    }

    return conditionalItems;
};

export default function SettingsLayout({ children }: PropsWithChildren) {
    // When server-side rendering, we only render the layout on the client...
    if (typeof window === 'undefined') {
        return null;
    }

    const currentPath = window.location.pathname;
    const allNavItems = [...sidebarNavItems, ...getConditionalNavItems()];

    return (
        <Box sx={{ px: 2, py: 3 }}>
            <Heading
                title="Settings"
                description="Manage your profile and account settings"
            />

            <Box sx={{ display: 'flex', flexDirection: { xs: 'column', lg: 'row' }, gap: { lg: 6 } }}>
                <Box component="aside" sx={{ width: '100%', maxWidth: { lg: 192 } }}>
                    <Box component="nav" sx={{ display: 'flex', flexDirection: 'column', gap: 0.5 }}>
                        {allNavItems.map((item, index) => {
                            const isActive = isSameUrl(currentPath, item.href);
                            return (
                                <Button
                                    key={`${resolveUrl(item.href)}-${index}`}
                                    size="sm"
                                    variant="ghost"
                                    asChild
                                    sx={{
                                        width: '100%',
                                        justifyContent: 'flex-start',
                                        ...(isActive && { bgcolor: 'action.selected' }),
                                    }}
                                >
                                    <Link href={item.href}>
                                        {item.icon && (
                                            <item.icon style={{ width: 16, height: 16 }} />
                                        )}
                                        {item.title}
                                    </Link>
                                </Button>
                            );
                        })}
                    </Box>
                </Box>

                <Divider sx={{ my: 3, display: { lg: 'none' } }} />

                <Box sx={{ flex: 1, maxWidth: { md: 672 } }}>
                    <Box component="section" sx={{ maxWidth: 576, display: 'flex', flexDirection: 'column', gap: 6 }}>
                        {children}
                    </Box>
                </Box>
            </Box>
        </Box>
    );
}

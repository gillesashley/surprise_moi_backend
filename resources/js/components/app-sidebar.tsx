import { index as contentManagementIndex } from '@/actions/App/Http/Controllers/ContentManagementController';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { index as usersIndex } from '@/routes/users';
import { index as vendorApplicationsIndex } from '@/routes/vendor-applications';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    BarChart3,
    CheckCircle,
    Code,
    CreditCard,
    DollarSign,
    LayoutGrid,
    List,
    Megaphone,
    MonitorPlay,
    Settings2,
    Target,
    UserCheck,
    Users,
    Wallet,
} from 'lucide-react';
import AppLogo from './app-logo';

const getNavItemsForRole = (role: string): NavItem[] => {
    // Admin & Super Admin - full access with organized groups
    if (role === 'admin' || role === 'super_admin') {
        return [
            {
                title: 'Dashboard',
                href: dashboard(),
                icon: LayoutGrid,
            },
            {
                title: 'User Management',
                icon: Users,
                items: [
                    {
                        title: 'Users',
                        href: usersIndex(),
                        icon: UserCheck,
                    },
                    {
                        title: 'Vendor Applications',
                        href: vendorApplicationsIndex(),
                        icon: CheckCircle,
                    },
                ],
            },
            {
                title: 'Financial',
                icon: DollarSign,
                items: [
                    {
                        title: 'Commission Statistics',
                        href: '/dashboard/commission-statistics',
                        icon: BarChart3,
                    },
                    {
                        title: 'Vendor Payouts',
                        href: '/dashboard/vendor-payouts',
                        icon: CreditCard,
                    },
                    {
                        title: 'All Transactions',
                        href: '/dashboard/transactions',
                        icon: List,
                    },
                ],
            },
            {
                title: 'Marketing',
                icon: Megaphone,
                items: [
                    {
                        title: 'Advertisements',
                        href: '/dashboard/advertisements',
                        icon: MonitorPlay,
                    },
                    {
                        title: 'Targets',
                        href: '/dashboard/targets',
                        icon: Target,
                    },
                    {
                        title: 'Referral Codes',
                        href: '/dashboard/referral-codes',
                        icon: Code,
                    },
                ],
            },
            {
                title: 'Support',
                icon: AlertTriangle,
                items: [
                    {
                        title: 'Reports & Conflicts',
                        href: '/dashboard/reports',
                        icon: AlertTriangle,
                    },
                ],
            },
            {
                title: 'Content Management',
                href: contentManagementIndex.url(),
                icon: Settings2,
            },
            {
                title: 'Jobs',
                href: '/dashboard/jobs',
                icon: BarChart3,
            },
        ];
    }

    // Influencer - referrals, earnings, payouts
    if (role === 'influencer') {
        return [
            {
                title: 'Dashboard',
                href: '/influencer/dashboard',
                icon: LayoutGrid,
            },
            {
                title: 'Performance',
                icon: DollarSign,
                items: [
                    {
                        title: 'My Referrals',
                        href: '/influencer/referrals',
                        icon: UserCheck,
                    },
                    {
                        title: 'My Earnings',
                        href: '/influencer/earnings',
                        icon: Wallet,
                    },
                    {
                        title: 'Payouts',
                        href: '/influencer/payouts',
                        icon: CheckCircle,
                    },
                ],
            },
        ];
    }

    // Field Agent - targets, earnings, payouts
    if (role === 'field_agent') {
        return [
            {
                title: 'Dashboard',
                href: '/field-agent/dashboard',
                icon: LayoutGrid,
            },
            {
                title: 'Work & Earnings',
                icon: DollarSign,
                items: [
                    {
                        title: 'My Targets',
                        href: '/field-agent/targets',
                        icon: Target,
                    },
                    {
                        title: 'My Earnings',
                        href: '/field-agent/earnings',
                        icon: Wallet,
                    },
                    {
                        title: 'Payouts',
                        href: '/field-agent/payouts',
                        icon: CheckCircle,
                    },
                ],
            },
        ];
    }

    // Marketer - targets, earnings, payouts
    if (role === 'marketer') {
        return [
            {
                title: 'Dashboard',
                href: '/marketer/dashboard',
                icon: LayoutGrid,
            },
            {
                title: 'Work & Earnings',
                icon: DollarSign,
                items: [
                    {
                        title: 'My Targets',
                        href: '/marketer/targets',
                        icon: Target,
                    },
                    {
                        title: 'My Earnings',
                        href: '/marketer/earnings',
                        icon: Wallet,
                    },
                    {
                        title: 'Payouts',
                        href: '/marketer/payouts',
                        icon: CheckCircle,
                    },
                ],
            },
        ];
    }

    // Default - just dashboard
    return [
        {
            title: 'Dashboard',
            href: dashboard(),
            icon: LayoutGrid,
        },
    ];
};

export function AppSidebar() {
    const { auth } = usePage<SharedData>().props;
    const navItems = getNavItemsForRole(auth?.user?.role || 'customer');

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={navItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}

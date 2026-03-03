import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { show as userShow } from '@/routes/users';
import { type BreadcrumbItem, type SharedData } from '@/types';
import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';
import { Head, Link, usePage } from '@inertiajs/react';
import {
    AlertCircle,
    Clock,
    Gift,
    Package,
    ShoppingCart,
    Sparkles,
    TrendingUp,
    Users,
} from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];

interface StatCardProps {
    title: string;
    value: string | number;
    icon: React.ElementType;
    trend?: string;
    trendUp?: boolean;
    iconColor: string;
}

function StatCard({
    title,
    value,
    icon: Icon,
    trend,
    trendUp,
    iconColor,
}: StatCardProps) {
    return (
        <Box
            sx={{
                position: 'relative',
                overflow: 'hidden',
                borderRadius: 3,
                p: 3,
                boxShadow: 1,
                border: 1,
                borderColor: 'divider',
                bgcolor: 'background.paper',
                transition: 'all 0.2s',
                '&:hover': { boxShadow: 3 },
            }}
        >
            <Box sx={{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between' }}>
                <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
                    <Typography variant="body2" color="text.secondary" fontWeight={500}>
                        {title}
                    </Typography>
                    <Typography variant="h4" fontWeight={700} sx={{ letterSpacing: '-0.02em' }}>
                        {value}
                    </Typography>
                    {trend && (
                        <Box
                            sx={{
                                display: 'flex',
                                alignItems: 'center',
                                color: trendUp ? 'success.main' : 'error.main',
                            }}
                        >
                            <TrendingUp
                                style={{
                                    width: 16,
                                    height: 16,
                                    marginRight: 4,
                                    transform: !trendUp ? 'rotate(180deg)' : undefined,
                                }}
                            />
                            <Typography variant="body2" fontWeight={500}>
                                {trend}
                            </Typography>
                        </Box>
                    )}
                </Box>
                <Box
                    sx={{
                        borderRadius: 2,
                        p: 1.5,
                        bgcolor: iconColor,
                    }}
                >
                    <Icon style={{ width: 24, height: 24, color: 'white' }} />
                </Box>
            </Box>
            <Box
                sx={{
                    position: 'absolute',
                    right: -16,
                    bottom: -16,
                    width: 96,
                    height: 96,
                    borderRadius: '50%',
                    bgcolor: 'rgba(255,255,255,0.05)',
                }}
            />
        </Box>
    );
}

function WelcomeCard({ userName }: { userName: string }) {
    return (
        <Box
            sx={{
                position: 'relative',
                overflow: 'hidden',
                borderRadius: 3,
                p: 3,
                color: 'primary.contrastText',
                boxShadow: 3,
                background: (theme) =>
                    `linear-gradient(135deg, ${theme.palette.primary.main}, ${theme.palette.secondary.main})`,
            }}
        >
            <Box sx={{ position: 'relative', zIndex: 1 }}>
                <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                    <Sparkles style={{ width: 24, height: 24 }} />
                    <Typography variant="h5" fontWeight={700}>
                        Welcome back, {userName}!
                    </Typography>
                </Box>
                <Typography variant="body1" sx={{ mt: 1, opacity: 0.8 }}>
                    Your SurpriseMoi dashboard is ready. Let's create some
                    amazing surprises today!
                </Typography>
            </Box>
            <Box
                sx={{
                    position: 'absolute',
                    top: -32,
                    right: -32,
                    width: 160,
                    height: 160,
                    borderRadius: '50%',
                    bgcolor: 'rgba(255,255,255,0.1)',
                }}
            />
            <Box
                sx={{
                    position: 'absolute',
                    bottom: -32,
                    left: -32,
                    width: 128,
                    height: 128,
                    borderRadius: '50%',
                    bgcolor: 'rgba(255,255,255,0.05)',
                }}
            />
        </Box>
    );
}

function QuickActionCard({
    title,
    description,
    icon: Icon,
    href,
}: {
    title: string;
    description: string;
    icon: React.ElementType;
    href: string;
}) {
    return (
        <Link href={href} style={{ textDecoration: 'none', color: 'inherit' }}>
            <Box
                sx={{
                    display: 'flex',
                    alignItems: 'center',
                    gap: 2,
                    borderRadius: 3,
                    p: 2.5,
                    bgcolor: 'primary.main',
                    color: 'primary.contrastText',
                    transition: 'all 0.2s',
                    '&:hover': {
                        transform: 'scale(1.02)',
                        boxShadow: 3,
                        bgcolor: 'primary.dark',
                    },
                }}
            >
                <Box
                    sx={{
                        borderRadius: 2,
                        p: 1.5,
                        bgcolor: 'rgba(255,255,255,0.2)',
                    }}
                >
                    <Icon style={{ width: 24, height: 24 }} />
                </Box>
                <Box>
                    <Typography variant="subtitle2" fontWeight={600}>
                        {title}
                    </Typography>
                    <Typography variant="body2" sx={{ opacity: 0.8 }}>
                        {description}
                    </Typography>
                </Box>
            </Box>
        </Link>
    );
}

function RecentActivityCard() {
    const activities = [
        {
            action: 'New order placed',
            user: 'John D.',
            time: '2 mins ago',
            type: 'order',
        },
        {
            action: 'User registered',
            user: 'Sarah M.',
            time: '15 mins ago',
            type: 'user',
        },
        {
            action: 'Surprise delivered',
            user: 'Mike R.',
            time: '1 hour ago',
            type: 'delivery',
        },
        {
            action: 'Payment received',
            user: 'Emma W.',
            time: '2 hours ago',
            type: 'payment',
        },
    ];

    const getActivityColor = (type: string) => {
        switch (type) {
            case 'order': return 'primary.main';
            case 'user': return 'secondary.main';
            case 'delivery': return 'success.main';
            default: return 'info.main';
        }
    };

    return (
        <Box
            sx={{
                borderRadius: 3,
                border: 1,
                borderColor: 'divider',
                bgcolor: 'background.paper',
                p: 3,
                boxShadow: 1,
            }}
        >
            <Box sx={{ display: 'flex', alignItems: 'center', gap: 1, mb: 2 }}>
                <Box
                    sx={{
                        width: 8,
                        height: 8,
                        borderRadius: '50%',
                        bgcolor: 'success.main',
                        animation: 'pulse 2s infinite',
                        '@keyframes pulse': {
                            '0%, 100%': { opacity: 1 },
                            '50%': { opacity: 0.5 },
                        },
                    }}
                />
                <Typography variant="h6" fontWeight={600}>
                    Recent Activity
                </Typography>
            </Box>
            <Box sx={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
                {activities.map((activity, index) => (
                    <Box
                        key={index}
                        sx={{
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'space-between',
                            borderBottom: index < activities.length - 1 ? 1 : 0,
                            borderColor: 'divider',
                            pb: index < activities.length - 1 ? 1.5 : 0,
                        }}
                    >
                        <Box sx={{ display: 'flex', alignItems: 'center', gap: 1.5 }}>
                            <Box
                                sx={{
                                    width: 8,
                                    height: 8,
                                    borderRadius: '50%',
                                    bgcolor: getActivityColor(activity.type),
                                }}
                            />
                            <Box>
                                <Typography variant="body2" fontWeight={500}>
                                    {activity.action}
                                </Typography>
                                <Typography variant="caption" color="text.secondary">
                                    {activity.user}
                                </Typography>
                            </Box>
                        </Box>
                        <Typography variant="caption" color="text.secondary">
                            {activity.time}
                        </Typography>
                    </Box>
                ))}
            </Box>
        </Box>
    );
}

interface VendorApplication {
    id: number;
    user: {
        id: number;
        name: string;
        email: string;
    };
    status: string;
    submitted_at: string | null;
    progress: string;
}

interface DashboardStats {
    totalUsers: {
        value: number;
        trend: number;
        trendUp: boolean;
    };
    activeOrders: {
        value: number;
        trend: number;
        trendUp: boolean;
    };
    surprisesSent: {
        value: number;
        trend: number;
        trendUp: boolean;
    };
    revenue: {
        value: number;
        trend: number;
        trendUp: boolean;
    };
}

interface DashboardProps {
    stats?: DashboardStats;
    pendingApplications: VendorApplication[];
}

function VendorApplicationsCard({
    applications,
}: {
    applications: VendorApplication[];
}) {
    if (!applications || applications.length === 0) {
        return (
            <Card>
                <CardHeader>
                    <CardTitle style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                        <Clock style={{ width: 20, height: 20 }} />
                        Pending Vendor Applications
                    </CardTitle>
                    <CardDescription>
                        Review and approve vendor registrations
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <Typography variant="body2" color="text.secondary">
                        No pending applications at the moment.
                    </Typography>
                </CardContent>
            </Card>
        );
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                    <AlertCircle style={{ width: 20, height: 20, color: 'orange' }} />
                    Pending Vendor Applications
                    <Badge variant="destructive" style={{ marginLeft: 8 }}>
                        {applications.length}
                    </Badge>
                </CardTitle>
                <CardDescription>
                    Review and approve vendor registrations
                </CardDescription>
            </CardHeader>
            <CardContent>
                <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1.5 }}>
                    {applications.map((app) => (
                        <Link
                            key={app.id}
                            href={userShow.url(app.user.id)}
                            style={{ textDecoration: 'none', color: 'inherit' }}
                        >
                            <Box
                                sx={{
                                    borderRadius: 2,
                                    border: 1,
                                    borderColor: 'divider',
                                    p: 2,
                                    transition: 'all 0.2s',
                                    '&:hover': {
                                        borderColor: 'primary.main',
                                        boxShadow: 3,
                                    },
                                }}
                            >
                                <Box sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                                    <Box sx={{ flex: 1 }}>
                                        <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                                            <Typography variant="body2" fontWeight={500}>
                                                {app.user.name}
                                            </Typography>
                                            <Badge
                                                variant={
                                                    app.status === 'pending'
                                                        ? 'secondary'
                                                        : 'outline'
                                                }
                                            >
                                                {app.status
                                                    .replace(/_/g, ' ')
                                                    .toUpperCase()}
                                            </Badge>
                                        </Box>
                                        <Typography variant="body2" color="text.secondary">
                                            {app.user.email}
                                        </Typography>
                                        <Box sx={{ mt: 1, display: 'flex', alignItems: 'center', gap: 2 }}>
                                            <Typography variant="caption" color="text.secondary">
                                                Progress: {app.progress}
                                            </Typography>
                                            {app.submitted_at && (
                                                <Typography variant="caption" color="text.secondary">
                                                    Submitted:{' '}
                                                    {new Date(
                                                        app.submitted_at,
                                                    ).toLocaleDateString()}
                                                </Typography>
                                            )}
                                        </Box>
                                    </Box>
                                    <TrendingUp style={{ width: 20, height: 20 }} />
                                </Box>
                            </Box>
                        </Link>
                    ))}
                </Box>
            </CardContent>
        </Card>
    );
}

export default function Dashboard({
    stats,
    pendingApplications,
}: DashboardProps) {
    const { auth } = usePage<SharedData>().props;

    const dashboardStats: DashboardStats = stats || {
        totalUsers: { value: 0, trend: 0, trendUp: true },
        activeOrders: { value: 0, trend: 0, trendUp: true },
        surprisesSent: { value: 0, trend: 0, trendUp: true },
        revenue: { value: 0, trend: 0, trendUp: true },
    };

    const formatCurrency = (value: number) => {
        return new Intl.NumberFormat('en-GH', {
            style: 'currency',
            currency: 'GHS',
            minimumFractionDigits: 0,
            maximumFractionDigits: 0,
        }).format(value);
    };

    const formatNumber = (value: number) => {
        return new Intl.NumberFormat('en-GH').format(value);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            <Box sx={{ display: 'flex', flex: 1, flexDirection: 'column', gap: 3, overflowX: 'auto', p: 3 }}>
                <WelcomeCard userName={auth.user.name.split(' ')[0]} />

                <Box
                    sx={{
                        display: 'grid',
                        gap: 2,
                        gridTemplateColumns: {
                            xs: '1fr',
                            md: 'repeat(2, 1fr)',
                            lg: 'repeat(4, 1fr)',
                        },
                    }}
                >
                    <StatCard
                        title="Total Users"
                        value={formatNumber(dashboardStats.totalUsers.value)}
                        icon={Users}
                        trend={
                            dashboardStats.totalUsers.trend !== 0
                                ? `${dashboardStats.totalUsers.trendUp ? '+' : ''}${dashboardStats.totalUsers.trend}% from last month`
                                : undefined
                        }
                        trendUp={dashboardStats.totalUsers.trendUp}
                        iconColor="primary.main"
                    />
                    <StatCard
                        title="Active Orders"
                        value={formatNumber(dashboardStats.activeOrders.value)}
                        icon={ShoppingCart}
                        trend={
                            dashboardStats.activeOrders.trend !== 0
                                ? `${dashboardStats.activeOrders.trendUp ? '+' : ''}${dashboardStats.activeOrders.trend}% from last week`
                                : undefined
                        }
                        trendUp={dashboardStats.activeOrders.trendUp}
                        iconColor="secondary.main"
                    />
                    <StatCard
                        title="Surprises Sent"
                        value={formatNumber(dashboardStats.surprisesSent.value)}
                        icon={Gift}
                        trend={
                            dashboardStats.surprisesSent.trend !== 0
                                ? `${dashboardStats.surprisesSent.trendUp ? '+' : ''}${dashboardStats.surprisesSent.trend}% this month`
                                : undefined
                        }
                        trendUp={dashboardStats.surprisesSent.trendUp}
                        iconColor="success.main"
                    />
                    <StatCard
                        title="Revenue"
                        value={formatCurrency(dashboardStats.revenue.value)}
                        icon={Package}
                        trend={
                            dashboardStats.revenue.trend !== 0
                                ? `${dashboardStats.revenue.trendUp ? '+' : ''}${dashboardStats.revenue.trend}% from last month`
                                : undefined
                        }
                        trendUp={dashboardStats.revenue.trendUp}
                        iconColor="info.main"
                    />
                </Box>

                <Box
                    sx={{
                        display: 'grid',
                        gap: 3,
                        gridTemplateColumns: { lg: 'repeat(3, 1fr)' },
                    }}
                >
                    <Box sx={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
                        <Typography variant="h6" fontWeight={600}>
                            Quick Actions
                        </Typography>
                        <Box sx={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
                            <QuickActionCard
                                title="Manage Users"
                                description="View and manage user accounts"
                                icon={Users}
                                href="/dashboard/users"
                            />
                            <QuickActionCard
                                title="Content Management"
                                description="Update categories and content"
                                icon={Package}
                                href="/dashboard/content-management"
                            />
                        </Box>
                    </Box>

                    <RecentActivityCard />

                    <VendorApplicationsCard
                        applications={pendingApplications}
                    />
                </Box>
            </Box>
        </AppLayout>
    );
}

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
    bgColor: string;
    iconBgColor: string;
}

function StatCard({
    title,
    value,
    icon: Icon,
    trend,
    trendUp,
    bgColor,
    iconBgColor,
}: StatCardProps) {
    return (
        <div
            className={`relative overflow-hidden rounded-xl p-6 shadow-sm transition-all duration-200 hover:shadow-md ${bgColor}`}
        >
            <div className="flex items-start justify-between">
                <div className="space-y-2">
                    <p className="text-sm font-medium text-muted-foreground">
                        {title}
                    </p>
                    <p className="text-3xl font-bold tracking-tight">{value}</p>
                    {trend && (
                        <p
                            className={`flex items-center text-sm font-medium ${trendUp ? 'text-success' : 'text-destructive'}`}
                        >
                            <TrendingUp
                                className={`mr-1 h-4 w-4 ${!trendUp && 'rotate-180'}`}
                            />
                            {trend}
                        </p>
                    )}
                </div>
                <div className={`rounded-lg p-3 ${iconBgColor}`}>
                    <Icon className="h-6 w-6 text-white" />
                </div>
            </div>
            <div className="absolute -right-4 -bottom-4 h-24 w-24 rounded-full bg-white/5" />
        </div>
    );
}

function WelcomeCard({ userName }: { userName: string }) {
    return (
        <div className="relative overflow-hidden rounded-xl bg-gradient-to-br from-primary to-secondary p-6 text-white shadow-lg">
            <div className="relative z-10">
                <div className="flex items-center gap-2">
                    <Sparkles className="h-6 w-6 text-accent" />
                    <h2 className="text-2xl font-bold">
                        Welcome back, {userName}!
                    </h2>
                </div>
                <p className="mt-2 text-white/80">
                    Your SurpriseMoi dashboard is ready. Let's create some
                    amazing surprises today!
                </p>
            </div>
            <div className="absolute -top-8 -right-8 h-40 w-40 rounded-full bg-white/10" />
            <div className="absolute -bottom-8 -left-8 h-32 w-32 rounded-full bg-white/5" />
        </div>
    );
}

function QuickActionCard({
    title,
    description,
    icon: Icon,
    bgColor,
    href,
}: {
    title: string;
    description: string;
    icon: React.ElementType;
    bgColor: string;
    href: string;
}) {
    return (
        <Link
            href={href}
            className={`group block rounded-xl p-5 transition-all duration-200 hover:scale-[1.02] hover:shadow-md ${bgColor}`}
        >
            <div className="flex items-center gap-4">
                <div className="rounded-lg bg-white/20 p-3">
                    <Icon className="h-6 w-6" />
                </div>
                <div>
                    <h3 className="font-semibold">{title}</h3>
                    <p className="text-sm opacity-80">{description}</p>
                </div>
            </div>
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

    return (
        <div className="rounded-xl border border-border bg-card p-6 shadow-sm">
            <h3 className="mb-4 flex items-center gap-2 text-lg font-semibold">
                <div className="h-2 w-2 animate-pulse rounded-full bg-success" />
                Recent Activity
            </h3>
            <div className="space-y-4">
                {activities.map((activity, index) => (
                    <div
                        key={index}
                        className="flex items-center justify-between border-b border-border/50 pb-3 last:border-0"
                    >
                        <div className="flex items-center gap-3">
                            <div
                                className={`h-2 w-2 rounded-full ${
                                    activity.type === 'order'
                                        ? 'bg-primary'
                                        : activity.type === 'user'
                                          ? 'bg-accent'
                                          : activity.type === 'delivery'
                                            ? 'bg-success'
                                            : 'bg-chart-2'
                                }`}
                            />
                            <div>
                                <p className="text-sm font-medium">
                                    {activity.action}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    {activity.user}
                                </p>
                            </div>
                        </div>
                        <span className="text-xs text-muted-foreground">
                            {activity.time}
                        </span>
                    </div>
                ))}
            </div>
        </div>
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
                    <CardTitle className="flex items-center gap-2">
                        <Clock className="h-5 w-5" />
                        Pending Vendor Applications
                    </CardTitle>
                    <CardDescription>
                        Review and approve vendor registrations
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <p className="text-sm text-muted-foreground">
                        No pending applications at the moment.
                    </p>
                </CardContent>
            </Card>
        );
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <AlertCircle className="text-warning h-5 w-5" />
                    Pending Vendor Applications
                    <Badge variant="destructive" className="ml-2">
                        {applications.length}
                    </Badge>
                </CardTitle>
                <CardDescription>
                    Review and approve vendor registrations
                </CardDescription>
            </CardHeader>
            <CardContent>
                <div className="space-y-3">
                    {applications.map((app) => (
                        <Link
                            key={app.id}
                            href={userShow.url(app.user.id)}
                            className="block rounded-lg border p-4 transition-all hover:border-primary hover:shadow-md"
                        >
                            <div className="flex items-center justify-between">
                                <div className="flex-1">
                                    <div className="flex items-center gap-2">
                                        <h4 className="font-medium">
                                            {app.user.name}
                                        </h4>
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
                                    </div>
                                    <p className="text-sm text-muted-foreground">
                                        {app.user.email}
                                    </p>
                                    <div className="mt-2 flex items-center gap-4 text-xs text-muted-foreground">
                                        <span>Progress: {app.progress}</span>
                                        {app.submitted_at && (
                                            <span>
                                                Submitted:{' '}
                                                {new Date(
                                                    app.submitted_at,
                                                ).toLocaleDateString()}
                                            </span>
                                        )}
                                    </div>
                                </div>
                                <TrendingUp className="h-5 w-5 text-primary" />
                            </div>
                        </Link>
                    ))}
                </div>
            </CardContent>
        </Card>
    );
}

export default function Dashboard({
    stats,
    pendingApplications,
}: DashboardProps) {
    const { auth } = usePage<SharedData>().props;

    // Default stats if not provided
    const dashboardStats: DashboardStats = stats || {
        totalUsers: { value: 0, trend: 0, trendUp: true },
        activeOrders: { value: 0, trend: 0, trendUp: true },
        surprisesSent: { value: 0, trend: 0, trendUp: true },
        revenue: { value: 0, trend: 0, trendUp: true },
    };

    // Format currency
    const formatCurrency = (value: number) => {
        return new Intl.NumberFormat('en-GH', {
            style: 'currency',
            currency: 'GHS',
            minimumFractionDigits: 0,
            maximumFractionDigits: 0,
        }).format(value);
    };

    // Format number with commas
    const formatNumber = (value: number) => {
        return new Intl.NumberFormat('en-GH').format(value);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-6">
                {/* Welcome Section */}
                <WelcomeCard userName={auth.user.name.split(' ')[0]} />

                {/* Stats Grid */}
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
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
                        bgColor="bg-card border border-border"
                        iconBgColor="bg-primary"
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
                        bgColor="bg-card border border-border"
                        iconBgColor="bg-accent"
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
                        bgColor="bg-card border border-border"
                        iconBgColor="bg-success"
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
                        bgColor="bg-card border border-border"
                        iconBgColor="bg-secondary"
                    />
                </div>

                {/* Quick Actions & Activity */}
                <div className="grid gap-6 lg:grid-cols-3">
                    {/* Quick Actions */}
                    <div className="space-y-4">
                        <h3 className="text-lg font-semibold">Quick Actions</h3>
                        <div className="grid gap-4">
                            <QuickActionCard
                                title="Manage Users"
                                description="View and manage user accounts"
                                icon={Users}
                                bgColor="bg-primary/10 text-primary hover:bg-primary/20"
                                href="/dashboard/users"
                            />
                            <QuickActionCard
                                title="Content Management"
                                description="Update categories and content"
                                icon={Package}
                                bgColor="bg-accent/10 text-accent-foreground hover:bg-accent/20"
                                href="/dashboard/content-management"
                            />
                        </div>
                    </div>

                    {/* Recent Activity */}
                    <RecentActivityCard />

                    {/* Vendor Applications - New */}
                    <VendorApplicationsCard
                        applications={pendingApplications}
                    />
                </div>
            </div>
        </AppLayout>
    );
}

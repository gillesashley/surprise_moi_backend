import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, usePage } from '@inertiajs/react';
import {
    CheckCircle,
    Clock,
    Code,
    DollarSign,
    Users,
    XCircle,
} from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/influencer/dashboard',
    },
];

interface Stats {
    total_referrals: number;
    active_referrals: number;
    pending_referrals: number;
    expired_referrals: number;
    total_earnings: number;
    pending_earnings: number;
    approved_earnings: number;
    paid_earnings: number;
}

interface ReferralCode {
    id: number;
    code: string;
    is_active: boolean;
    usage_count: number;
    max_uses: number | null;
    expires_at: string | null;
}

interface Referral {
    id: number;
    status: string;
    vendor: {
        name: string;
        email: string;
    };
    created_at: string;
}

interface Earning {
    id: number;
    amount: number;
    currency: string;
    earning_type: string;
    status: string;
    earned_at: string;
}

interface DashboardProps {
    stats: Stats;
    recent_referrals: Referral[];
    recent_earnings: Earning[];
    referral_codes: ReferralCode[];
}

interface StatCardProps {
    title: string;
    value: string | number;
    icon: React.ElementType;
    bgColor: string;
    iconBgColor: string;
}

function StatCard({
    title,
    value,
    icon: Icon,
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
                </div>
                <div className={`rounded-lg p-3 ${iconBgColor}`}>
                    <Icon className="h-6 w-6 text-white" />
                </div>
            </div>
        </div>
    );
}

function getStatusBadge(status: string) {
    const statusConfig = {
        active: {
            label: 'Active',
            variant: 'default' as const,
            icon: CheckCircle,
        },
        pending: {
            label: 'Pending',
            variant: 'secondary' as const,
            icon: Clock,
        },
        expired: {
            label: 'Expired',
            variant: 'destructive' as const,
            icon: XCircle,
        },
        paid: { label: 'Paid', variant: 'default' as const, icon: CheckCircle },
        approved: {
            label: 'Approved',
            variant: 'secondary' as const,
            icon: CheckCircle,
        },
    };

    const config = statusConfig[status as keyof typeof statusConfig] || {
        label: status,
        variant: 'outline' as const,
        icon: Clock,
    };

    const Icon = config.icon;

    return (
        <Badge variant={config.variant} className="flex items-center gap-1">
            <Icon className="h-3 w-3" />
            {config.label}
        </Badge>
    );
}

export default function InfluencerDashboard({
    stats = {
        total_referrals: 0,
        active_referrals: 0,
        pending_referrals: 0,
        expired_referrals: 0,
        total_earnings: 0,
        pending_earnings: 0,
        approved_earnings: 0,
        paid_earnings: 0,
    },
    recent_referrals = [],
    recent_earnings = [],
    referral_codes = [],
}: DashboardProps) {
    const { auth } = usePage<SharedData>().props;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Influencer Dashboard" />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-6">
                {/* Welcome Section */}
                <div className="relative overflow-hidden rounded-xl bg-gradient-to-br from-primary to-secondary p-6 text-white shadow-lg">
                    <div className="relative z-10">
                        <h2 className="text-2xl font-bold">
                            Welcome back, {auth.user?.name}!
                        </h2>
                        <p className="mt-2 text-white/80">
                            Track your referrals, earnings, and grow your
                            income.
                        </p>
                    </div>
                </div>

                {/* Stats Grid */}
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard
                        title="Total Referrals"
                        value={stats.total_referrals}
                        icon={Users}
                        bgColor="bg-card"
                        iconBgColor="bg-blue-500"
                    />
                    <StatCard
                        title="Active Referrals"
                        value={stats.active_referrals}
                        icon={CheckCircle}
                        bgColor="bg-card"
                        iconBgColor="bg-green-500"
                    />
                    <StatCard
                        title="Total Earnings"
                        value={`GHS ${stats.total_earnings.toFixed(2)}`}
                        icon={DollarSign}
                        bgColor="bg-card"
                        iconBgColor="bg-purple-500"
                    />
                    <StatCard
                        title="Pending Earnings"
                        value={`GHS ${stats.pending_earnings.toFixed(2)}`}
                        icon={Clock}
                        bgColor="bg-card"
                        iconBgColor="bg-orange-500"
                    />
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    {/* Referral Codes */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Your Referral Codes</CardTitle>
                            <CardDescription>
                                Share these codes with potential vendors
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-4">
                                {referral_codes.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        No referral codes yet. Create one to get
                                        started!
                                    </p>
                                ) : (
                                    referral_codes.map((code) => (
                                        <div
                                            key={code.id}
                                            className="flex items-center justify-between rounded-lg border p-4"
                                        >
                                            <div className="flex items-center gap-3">
                                                <Code className="h-5 w-5 text-primary" />
                                                <div>
                                                    <p className="font-mono font-bold">
                                                        {code.code}
                                                    </p>
                                                    <p className="text-sm text-muted-foreground">
                                                        Used: {code.usage_count}
                                                        {code.max_uses &&
                                                            ` / ${code.max_uses}`}
                                                    </p>
                                                </div>
                                            </div>
                                            {code.is_active ? (
                                                <Badge variant="default">
                                                    Active
                                                </Badge>
                                            ) : (
                                                <Badge variant="secondary">
                                                    Inactive
                                                </Badge>
                                            )}
                                        </div>
                                    ))
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    {/* Recent Referrals */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Recent Referrals</CardTitle>
                            <CardDescription>
                                Your latest vendor signups
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-4">
                                {recent_referrals.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        No referrals yet
                                    </p>
                                ) : (
                                    recent_referrals.map((referral) => (
                                        <div
                                            key={referral.id}
                                            className="flex items-center justify-between"
                                        >
                                            <div>
                                                <p className="font-medium">
                                                    {referral.vendor.name}
                                                </p>
                                                <p className="text-sm text-muted-foreground">
                                                    {referral.vendor.email}
                                                </p>
                                            </div>
                                            {getStatusBadge(referral.status)}
                                        </div>
                                    ))
                                )}
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Recent Earnings */}
                <Card>
                    <CardHeader>
                        <CardTitle>Recent Earnings</CardTitle>
                        <CardDescription>
                            Your latest commission payments
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-4">
                            {recent_earnings.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    No earnings yet
                                </p>
                            ) : (
                                recent_earnings.map((earning) => (
                                    <div
                                        key={earning.id}
                                        className="flex items-center justify-between"
                                    >
                                        <div>
                                            <p className="font-medium">
                                                {earning.currency}{' '}
                                                {earning.amount.toFixed(2)}
                                            </p>
                                            <p className="text-sm text-muted-foreground">
                                                {earning.earning_type.replace(
                                                    '_',
                                                    ' ',
                                                )}
                                            </p>
                                        </div>
                                        {getStatusBadge(earning.status)}
                                    </div>
                                ))
                            )}
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}

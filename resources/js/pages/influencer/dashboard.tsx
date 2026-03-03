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
import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';
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
        <Box
            sx={{
                position: 'relative',
                overflow: 'hidden',
                borderRadius: 3,
                p: 3,
                boxShadow: 1,
                transition: 'all 0.2s',
                '&:hover': { boxShadow: 3 },
                bgcolor: 'background.paper',
            }}
        >
            <Box sx={{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between' }}>
                <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
                    <Typography variant="body2" fontWeight={500} color="text.secondary">
                        {title}
                    </Typography>
                    <Typography variant="h4" fontWeight={700} sx={{ letterSpacing: '-0.02em' }}>
                        {value}
                    </Typography>
                </Box>
                <Box
                    sx={{
                        borderRadius: 2,
                        p: 1.5,
                        bgcolor: iconBgColor,
                    }}
                >
                    <Icon style={{ width: 24, height: 24, color: 'white' }} />
                </Box>
            </Box>
        </Box>
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

    const StatusIcon = config.icon;

    return (
        <Badge variant={config.variant}>
            <Box sx={{ display: 'flex', alignItems: 'center', gap: 0.5 }}>
                <StatusIcon style={{ width: 12, height: 12 }} />
                {config.label}
            </Box>
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

            <Box sx={{ display: 'flex', height: '100%', flex: 1, flexDirection: 'column', gap: 3, overflowX: 'auto', p: 3 }}>
                {/* Welcome Section */}
                <Box
                    sx={{
                        position: 'relative',
                        overflow: 'hidden',
                        borderRadius: 3,
                        background: (theme) =>
                            `linear-gradient(135deg, ${theme.palette.primary.main}, ${theme.palette.secondary.main})`,
                        p: 3,
                        color: 'white',
                        boxShadow: 3,
                    }}
                >
                    <Box sx={{ position: 'relative', zIndex: 1 }}>
                        <Typography variant="h5" fontWeight={700}>
                            Welcome back, {auth.user?.name}!
                        </Typography>
                        <Typography sx={{ mt: 1, color: 'rgba(255,255,255,0.8)' }}>
                            Track your referrals, earnings, and grow your
                            income.
                        </Typography>
                    </Box>
                </Box>

                {/* Stats Grid */}
                <Box
                    sx={{
                        display: 'grid',
                        gap: 2,
                        gridTemplateColumns: {
                            xs: '1fr',
                            sm: 'repeat(2, 1fr)',
                            lg: 'repeat(4, 1fr)',
                        },
                    }}
                >
                    <StatCard
                        title="Total Referrals"
                        value={stats.total_referrals}
                        icon={Users}
                        bgColor="bg-card"
                        iconBgColor="#3b82f6"
                    />
                    <StatCard
                        title="Active Referrals"
                        value={stats.active_referrals}
                        icon={CheckCircle}
                        bgColor="bg-card"
                        iconBgColor="#22c55e"
                    />
                    <StatCard
                        title="Total Earnings"
                        value={`GHS ${stats.total_earnings.toFixed(2)}`}
                        icon={DollarSign}
                        bgColor="bg-card"
                        iconBgColor="#a855f7"
                    />
                    <StatCard
                        title="Pending Earnings"
                        value={`GHS ${stats.pending_earnings.toFixed(2)}`}
                        icon={Clock}
                        bgColor="bg-card"
                        iconBgColor="#f97316"
                    />
                </Box>

                <Box sx={{ display: 'grid', gap: 3, gridTemplateColumns: { lg: 'repeat(2, 1fr)' } }}>
                    {/* Referral Codes */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Your Referral Codes</CardTitle>
                            <CardDescription>
                                Share these codes with potential vendors
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Box sx={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
                                {referral_codes.length === 0 ? (
                                    <Typography variant="body2" color="text.secondary">
                                        No referral codes yet. Create one to get
                                        started!
                                    </Typography>
                                ) : (
                                    referral_codes.map((code) => (
                                        <Box
                                            key={code.id}
                                            sx={{
                                                display: 'flex',
                                                alignItems: 'center',
                                                justifyContent: 'space-between',
                                                borderRadius: 2,
                                                border: 1,
                                                borderColor: 'divider',
                                                p: 2,
                                            }}
                                        >
                                            <Box sx={{ display: 'flex', alignItems: 'center', gap: 1.5 }}>
                                                <Code style={{ width: 20, height: 20 }} />
                                                <Box>
                                                    <Typography fontWeight={700} sx={{ fontFamily: 'monospace' }}>
                                                        {code.code}
                                                    </Typography>
                                                    <Typography variant="body2" color="text.secondary">
                                                        Used: {code.usage_count}
                                                        {code.max_uses &&
                                                            ` / ${code.max_uses}`}
                                                    </Typography>
                                                </Box>
                                            </Box>
                                            {code.is_active ? (
                                                <Badge variant="default">
                                                    Active
                                                </Badge>
                                            ) : (
                                                <Badge variant="secondary">
                                                    Inactive
                                                </Badge>
                                            )}
                                        </Box>
                                    ))
                                )}
                            </Box>
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
                            <Box sx={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
                                {recent_referrals.length === 0 ? (
                                    <Typography variant="body2" color="text.secondary">
                                        No referrals yet
                                    </Typography>
                                ) : (
                                    recent_referrals.map((referral) => (
                                        <Box
                                            key={referral.id}
                                            sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}
                                        >
                                            <Box>
                                                <Typography fontWeight={500}>
                                                    {referral.vendor.name}
                                                </Typography>
                                                <Typography variant="body2" color="text.secondary">
                                                    {referral.vendor.email}
                                                </Typography>
                                            </Box>
                                            {getStatusBadge(referral.status)}
                                        </Box>
                                    ))
                                )}
                            </Box>
                        </CardContent>
                    </Card>
                </Box>

                {/* Recent Earnings */}
                <Card>
                    <CardHeader>
                        <CardTitle>Recent Earnings</CardTitle>
                        <CardDescription>
                            Your latest commission payments
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Box sx={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
                            {recent_earnings.length === 0 ? (
                                <Typography variant="body2" color="text.secondary">
                                    No earnings yet
                                </Typography>
                            ) : (
                                recent_earnings.map((earning) => (
                                    <Box
                                        key={earning.id}
                                        sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}
                                    >
                                        <Box>
                                            <Typography fontWeight={500}>
                                                {earning.currency}{' '}
                                                {earning.amount.toFixed(2)}
                                            </Typography>
                                            <Typography variant="body2" color="text.secondary">
                                                {earning.earning_type.replace(
                                                    '_',
                                                    ' ',
                                                )}
                                            </Typography>
                                        </Box>
                                        {getStatusBadge(earning.status)}
                                    </Box>
                                ))
                            )}
                        </Box>
                    </CardContent>
                </Card>
            </Box>
        </AppLayout>
    );
}

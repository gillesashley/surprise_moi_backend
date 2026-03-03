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
import { Calendar, CheckCircle, DollarSign, Target } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/marketer/dashboard',
    },
];

interface Stats {
    total_targets: number;
    active_targets: number;
    completed_targets: number;
    total_bonus_earned: number;
    total_earnings: number;
    pending_earnings: number;
    approved_earnings: number;
    paid_earnings: number;
    current_quarter: number;
    current_year: number;
    quarterly_earnings: number;
}

interface Target {
    id: number;
    target_type: string;
    target_value: number;
    current_value: number;
    base_bonus: number;
    status: string;
    start_date: string;
    end_date: string;
    completion_percentage: number;
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
    active_targets: Target[];
    recent_sign_on_bonuses: Earning[];
}

function StatCard({
    title,
    value,
    icon: Icon,
    bgColor,
    iconBgColor,
}: {
    title: string;
    value: string | number;
    icon: React.ElementType;
    bgColor: string;
    iconBgColor: string;
}) {
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

export default function MarketerDashboard({
    stats = {
        total_targets: 0,
        active_targets: 0,
        completed_targets: 0,
        total_bonus_earned: 0,
        total_earnings: 0,
        pending_earnings: 0,
        approved_earnings: 0,
        paid_earnings: 0,
        current_quarter: 1,
        current_year: new Date().getFullYear(),
        quarterly_earnings: 0,
    },
    active_targets = [],
    recent_sign_on_bonuses = [],
}: DashboardProps) {
    const { auth } = usePage<SharedData>().props;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Marketer Dashboard" />

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
                            Track your targets and quarterly earnings.
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
                        title="Active Targets"
                        value={stats.active_targets}
                        icon={Target}
                        bgColor="bg-card"
                        iconBgColor="#3b82f6"
                    />
                    <StatCard
                        title="Completed"
                        value={stats.completed_targets}
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
                        title={`Q${stats.current_quarter} ${stats.current_year} Earnings`}
                        value={`GHS ${stats.quarterly_earnings.toFixed(2)}`}
                        icon={Calendar}
                        bgColor="bg-card"
                        iconBgColor="#f97316"
                    />
                </Box>

                {/* Active Targets */}
                <Card>
                    <CardHeader>
                        <CardTitle>Active Targets</CardTitle>
                        <CardDescription>
                            Your current performance targets
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Box sx={{ display: 'flex', flexDirection: 'column', gap: 3 }}>
                            {active_targets.length === 0 ? (
                                <Typography variant="body2" color="text.secondary">
                                    No active targets assigned yet
                                </Typography>
                            ) : (
                                active_targets.map((target) => (
                                    <Box
                                        key={target.id}
                                        sx={{
                                            display: 'flex',
                                            flexDirection: 'column',
                                            gap: 1,
                                            borderRadius: 2,
                                            border: 1,
                                            borderColor: 'divider',
                                            p: 2,
                                        }}
                                    >
                                        <Box sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                                            <Box>
                                                <Typography fontWeight={500}>
                                                    {target.target_type
                                                        .replace('_', ' ')
                                                        .toUpperCase()}
                                                </Typography>
                                                <Typography variant="body2" color="text.secondary">
                                                    Target:{' '}
                                                    {target.target_value} |
                                                    Current:{' '}
                                                    {target.current_value}
                                                </Typography>
                                            </Box>
                                            <Badge variant="default">
                                                {target.completion_percentage.toFixed(
                                                    0,
                                                )}
                                                %
                                            </Badge>
                                        </Box>
                                        <Box
                                            sx={{
                                                height: 8,
                                                width: '100%',
                                                borderRadius: 4,
                                                bgcolor: 'action.hover',
                                            }}
                                        >
                                            <Box
                                                sx={{
                                                    height: 8,
                                                    borderRadius: 4,
                                                    bgcolor: 'primary.main',
                                                    transition: 'all 0.2s',
                                                }}
                                                style={{
                                                    width: `${target.completion_percentage}%`,
                                                }}
                                            />
                                        </Box>
                                        <Box sx={{ display: 'flex', justifyContent: 'space-between', fontSize: '0.875rem', color: 'text.secondary' }}>
                                            <Typography variant="body2" color="text.secondary">
                                                Ends:{' '}
                                                {new Date(
                                                    target.end_date,
                                                ).toLocaleDateString()}
                                            </Typography>
                                            <Typography variant="body2" color="text.secondary">
                                                Sign-on Bonus: GHS{' '}
                                                {target.base_bonus}
                                            </Typography>
                                        </Box>
                                    </Box>
                                ))
                            )}
                        </Box>
                    </CardContent>
                </Card>

                {/* Recent Sign-on Bonuses */}
                <Card>
                    <CardHeader>
                        <CardTitle>Recent Sign-on Bonuses</CardTitle>
                        <CardDescription>
                            Your latest quarterly bonuses (added to salary)
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Box sx={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
                            {recent_sign_on_bonuses.length === 0 ? (
                                <Typography variant="body2" color="text.secondary">
                                    No sign-on bonuses yet
                                </Typography>
                            ) : (
                                recent_sign_on_bonuses.map((earning) => (
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
                                                {new Date(
                                                    earning.earned_at,
                                                ).toLocaleDateString()}
                                            </Typography>
                                        </Box>
                                        <Badge
                                            variant={
                                                earning.status === 'paid'
                                                    ? 'default'
                                                    : 'secondary'
                                            }
                                        >
                                            {earning.status}
                                        </Badge>
                                    </Box>
                                ))
                            )}
                        </Box>
                    </CardContent>
                </Card>

                <Card sx={{ bgcolor: 'info.50' }}>
                    <CardHeader>
                        <CardTitle>Quarterly Earnings Info</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Typography variant="body2" color="text.secondary">
                            Your sign-on bonuses are added to your quarterly
                            salary payment. Current quarter (Q
                            {stats.current_quarter} {stats.current_year})
                            earnings:{' '}
                            <strong>
                                GHS {stats.quarterly_earnings.toFixed(2)}
                            </strong>
                        </Typography>
                    </CardContent>
                </Card>
            </Box>
        </AppLayout>
    );
}

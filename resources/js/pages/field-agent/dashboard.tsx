import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';
import { CheckCircle, Clock, DollarSign, Users, XCircle } from 'lucide-react';
import ReferralCodeCard from './components/ReferralCodeCard';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/field-agent/dashboard' },
];

type Period = 'today' | 'week' | 'month';

interface VendorStats {
    total: number;
    pending: number;
    approved: number;
    rejected: number;
}

interface EarningsSummary {
    total_earnings: number;
    pending_earnings: number;
    approved_earnings: number;
    paid_earnings: number;
}

interface ActiveTarget {
    id: number;
    current: number;
    goal: number;
    completion_percentage: number;
    ends_at: string | null;
}

interface RecentVendor {
    id: number;
    business_name: string;
    status: string;
    created_at: string;
}

interface DashboardProps {
    agent: { id: number; first_name: string };
    period: Period;
    referralCode: { code: string };
    vendorStats: VendorStats;
    earningsSummary: EarningsSummary;
    activeTarget: ActiveTarget | null;
    recentVendors: RecentVendor[];
}

function greeting(): string {
    const h = new Date().getHours();
    if (h < 12) return 'Good morning';
    if (h < 18) return 'Good afternoon';
    return 'Good evening';
}

function StatTile({
    title,
    value,
    icon: Icon,
    iconBg,
}: {
    title: string;
    value: number | string;
    icon: React.ElementType;
    iconBg: string;
}) {
    return (
        <Box
            sx={{
                borderRadius: 3,
                p: 3,
                boxShadow: 1,
                bgcolor: 'background.paper',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'space-between',
            }}
        >
            <Box>
                <Typography variant="body2" fontWeight={500} color="text.secondary">
                    {title}
                </Typography>
                <Typography variant="h4" fontWeight={700} sx={{ letterSpacing: '-0.02em' }}>
                    {value}
                </Typography>
            </Box>
            <Box sx={{ borderRadius: 2, p: 1.5, bgcolor: iconBg }}>
                <Icon style={{ width: 22, height: 22, color: 'white' }} />
            </Box>
        </Box>
    );
}

function statusVariant(status: string): 'default' | 'secondary' | 'destructive' {
    if (status === 'approved') return 'default';
    if (status === 'rejected') return 'destructive';
    return 'secondary';
}

export default function FieldAgentDashboard({
    agent,
    period,
    referralCode,
    vendorStats,
    earningsSummary,
    activeTarget,
    recentVendors,
}: DashboardProps) {
    const { auth } = usePage<SharedData>().props;
    const displayName = agent?.first_name || auth.user?.name;

    const changePeriod = (next: Period) => {
        router.visit('/field-agent/dashboard', {
            data: { period: next },
            only: ['period', 'vendorStats', 'recentVendors'],
            preserveScroll: true,
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Field Agent Dashboard" />

            <Box sx={{ display: 'flex', flexDirection: 'column', gap: 3, p: 3 }}>
                {/* Header */}
                <Box
                    sx={{
                        display: 'flex',
                        flexDirection: { xs: 'column', md: 'row' },
                        alignItems: { md: 'center' },
                        justifyContent: 'space-between',
                        gap: 2,
                    }}
                >
                    <Box>
                        <Typography variant="h5" fontWeight={700}>
                            {greeting()}, {displayName}
                        </Typography>
                        <Typography variant="body2" color="text.secondary">
                            Here's how your onboarding is tracking.
                        </Typography>
                    </Box>
                    <Box sx={{ display: 'flex', gap: 1 }}>
                        {(['today', 'week', 'month'] as Period[]).map((p) => (
                            <Button
                                key={p}
                                size="sm"
                                variant={period === p ? 'default' : 'outline'}
                                onClick={() => changePeriod(p)}
                            >
                                {p === 'today' ? 'Today' : p === 'week' ? 'This Week' : 'This Month'}
                            </Button>
                        ))}
                    </Box>
                </Box>

                {/* Referral code */}
                <ReferralCodeCard code={referralCode.code} />

                {/* Row 1: Vendor pipeline */}
                <Box
                    sx={{
                        display: 'grid',
                        gap: 2,
                        gridTemplateColumns: { xs: '1fr', sm: 'repeat(2, 1fr)', lg: 'repeat(4, 1fr)' },
                    }}
                >
                    <StatTile title="Total Vendors" value={vendorStats.total} icon={Users} iconBg="#3b82f6" />
                    <StatTile title="Pending" value={vendorStats.pending} icon={Clock} iconBg="#f59e0b" />
                    <StatTile title="Approved" value={vendorStats.approved} icon={CheckCircle} iconBg="#22c55e" />
                    <StatTile title="Rejected" value={vendorStats.rejected} icon={XCircle} iconBg="#ef4444" />
                </Box>

                {/* Row 2: Earnings + Target */}
                <Box
                    sx={{
                        display: 'grid',
                        gap: 2,
                        gridTemplateColumns: { xs: '1fr', md: activeTarget ? 'repeat(2, 1fr)' : '1fr' },
                    }}
                >
                    <Card>
                        <CardHeader>
                            <CardTitle>Earnings</CardTitle>
                            <CardDescription>Your commission balance</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Box sx={{ display: 'flex', gap: 3, flexWrap: 'wrap', mb: 2 }}>
                                <Box>
                                    <Typography variant="body2" color="text.secondary">Total</Typography>
                                    <Typography variant="h6" fontWeight={700}>
                                        GHS {earningsSummary.total_earnings.toFixed(2)}
                                    </Typography>
                                </Box>
                                <Box>
                                    <Typography variant="body2" color="text.secondary">Pending</Typography>
                                    <Typography variant="h6" fontWeight={700}>
                                        GHS {earningsSummary.pending_earnings.toFixed(2)}
                                    </Typography>
                                </Box>
                                <Box>
                                    <Typography variant="body2" color="text.secondary">Available</Typography>
                                    <Typography variant="h6" fontWeight={700}>
                                        GHS {earningsSummary.approved_earnings.toFixed(2)}
                                    </Typography>
                                </Box>
                            </Box>
                            <Button
                                asChild
                                size="sm"
                                variant="default"
                                sx={{ gap: 0.5 }}
                            >
                                <a href="/field-agent/payouts">
                                    <DollarSign size={16} />
                                    Request payout
                                </a>
                            </Button>
                        </CardContent>
                    </Card>

                    {activeTarget && (
                        <Card>
                            <CardHeader>
                                <CardTitle>Target Progress</CardTitle>
                                <CardDescription>
                                    {activeTarget.current} / {activeTarget.goal}
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <Box sx={{ height: 10, width: '100%', borderRadius: 5, bgcolor: 'action.hover', mb: 1 }}>
                                    <Box
                                        sx={{
                                            height: 10,
                                            borderRadius: 5,
                                            bgcolor: 'primary.main',
                                            width: `${Math.min(100, activeTarget.completion_percentage)}%`,
                                        }}
                                    />
                                </Box>
                                <Box sx={{ display: 'flex', justifyContent: 'space-between' }}>
                                    <Typography variant="body2" color="text.secondary">
                                        {activeTarget.completion_percentage}% complete
                                    </Typography>
                                    {activeTarget.ends_at && (
                                        <Typography variant="body2" color="text.secondary">
                                            Ends {new Date(activeTarget.ends_at).toLocaleDateString()}
                                        </Typography>
                                    )}
                                </Box>
                            </CardContent>
                        </Card>
                    )}
                </Box>

                {/* Row 3: Recent vendors */}
                <Card>
                    <CardHeader>
                        <CardTitle>Recent vendors</CardTitle>
                        <CardDescription>Last 5 vendors attributed to you</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {recentVendors.length === 0 ? (
                            <Typography variant="body2" color="text.secondary">
                                No vendors yet. Share your referral code with one to get started.
                            </Typography>
                        ) : (
                            <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1.5 }}>
                                {recentVendors.map((v) => (
                                    <Box
                                        key={v.id}
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
                                        <Box>
                                            <Typography fontWeight={500}>
                                                {v.business_name || 'Unnamed vendor'}
                                            </Typography>
                                            <Typography variant="body2" color="text.secondary">
                                                {new Date(v.created_at).toLocaleDateString()}
                                            </Typography>
                                        </Box>
                                        <Badge variant={statusVariant(v.status)}>
                                            {v.status.replace('_', ' ')}
                                        </Badge>
                                    </Box>
                                ))}
                            </Box>
                        )}
                    </CardContent>
                </Card>
            </Box>
        </AppLayout>
    );
}

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
import { Award, CheckCircle, Clock, DollarSign, TrendingUp, Users, XCircle } from 'lucide-react';
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
    agent: { id: number; first_name: string; referral_points: number };
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
        <Card className="overflow-hidden border-0 shadow-sm transition-shadow hover:shadow-md">
            <CardContent className="p-5">
                <Box
                    sx={{
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'space-between',
                    }}
                >
                    <Box>
                        <Typography variant="body2" fontWeight={500} color="text.secondary" sx={{ mb: 0.5 }}>
                            {title}
                        </Typography>
                        <Typography variant="h4" fontWeight={700} sx={{ letterSpacing: '-0.02em' }}>
                            {value}
                        </Typography>
                    </Box>
                    <Box sx={{ borderRadius: 2.5, p: 1.5, bgcolor: iconBg }}>
                        <Icon style={{ width: 22, height: 22, color: 'white' }} />
                    </Box>
                </Box>
            </CardContent>
        </Card>
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

            <Box sx={{ display: 'flex', flexDirection: 'column', gap: 4, p: { xs: 2, md: 3 } }}>
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
                        gap: 2.5,
                        gridTemplateColumns: { xs: '1fr 1fr', sm: 'repeat(2, 1fr)', lg: 'repeat(5, 1fr)' },
                    }}
                >
                    <StatTile title="Total Vendors" value={vendorStats.total} icon={Users} iconBg="#3b82f6" />
                    <StatTile title="Referral Points" value={agent.referral_points} icon={Award} iconBg="#8b5cf6" />
                    <StatTile title="Pending" value={vendorStats.pending} icon={Clock} iconBg="#f59e0b" />
                    <StatTile title="Approved" value={vendorStats.approved} icon={CheckCircle} iconBg="#22c55e" />
                    <StatTile title="Rejected" value={vendorStats.rejected} icon={XCircle} iconBg="#ef4444" />
                </Box>

                {/* Row 2: Earnings + Target */}
                <Box
                    sx={{
                        display: 'grid',
                        gap: 2.5,
                        gridTemplateColumns: { xs: '1fr', md: activeTarget ? 'repeat(2, 1fr)' : '1fr' },
                    }}
                >
                    <Card className="border-0 shadow-sm">
                        <CardHeader className="pb-2">
                            <div className="flex items-center gap-2">
                                <div className="rounded-lg bg-purple-100 p-1.5">
                                    <DollarSign className="h-4 w-4 text-purple-600" />
                                </div>
                                <div>
                                    <CardTitle>Earnings</CardTitle>
                                    <CardDescription>Your commission balance</CardDescription>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <Box sx={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: 2, mb: 3 }}>
                                <Box sx={{ p: 2, borderRadius: 2, bgcolor: 'action.hover' }}>
                                    <Typography variant="caption" color="text.secondary" sx={{ fontWeight: 500 }}>
                                        Total
                                    </Typography>
                                    <Typography variant="h6" fontWeight={700}>
                                        GHS {earningsSummary.total_earnings.toFixed(2)}
                                    </Typography>
                                </Box>
                                <Box sx={{ p: 2, borderRadius: 2, bgcolor: 'action.hover' }}>
                                    <Typography variant="caption" color="text.secondary" sx={{ fontWeight: 500 }}>
                                        Pending
                                    </Typography>
                                    <Typography variant="h6" fontWeight={700} color="warning.main">
                                        GHS {earningsSummary.pending_earnings.toFixed(2)}
                                    </Typography>
                                </Box>
                                <Box sx={{ p: 2, borderRadius: 2, bgcolor: 'action.hover' }}>
                                    <Typography variant="caption" color="text.secondary" sx={{ fontWeight: 500 }}>
                                        Available
                                    </Typography>
                                    <Typography variant="h6" fontWeight={700} color="success.main">
                                        GHS {earningsSummary.approved_earnings.toFixed(2)}
                                    </Typography>
                                </Box>
                            </Box>
                            <Button
                                asChild
                                size="sm"
                                variant="default"
                                className="gap-1.5"
                            >
                                <a href="/field-agent/payouts">
                                    <DollarSign size={16} />
                                    Request payout
                                </a>
                            </Button>
                        </CardContent>
                    </Card>

                    {activeTarget && (
                        <Card className="border-0 shadow-sm">
                            <CardHeader className="pb-2">
                                <div className="flex items-center gap-2">
                                    <div className="rounded-lg bg-blue-100 p-1.5">
                                        <TrendingUp className="h-4 w-4 text-blue-600" />
                                    </div>
                                    <div>
                                        <CardTitle>Target Progress</CardTitle>
                                        <CardDescription>
                                            {activeTarget.current} / {activeTarget.goal}
                                        </CardDescription>
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent>
                                <Box sx={{ mb: 2 }}>
                                    <Box sx={{ height: 12, width: '100%', borderRadius: 6, bgcolor: 'action.hover' }}>
                                        <Box
                                            sx={{
                                                height: 12,
                                                borderRadius: 6,
                                                bgcolor: activeTarget.completion_percentage >= 100 ? 'success.main' : 'primary.main',
                                                width: `${Math.min(100, activeTarget.completion_percentage)}%`,
                                                transition: 'width 0.5s ease-in-out',
                                            }}
                                        />
                                    </Box>
                                </Box>
                                <Box sx={{ display: 'flex', justifyContent: 'space-between' }}>
                                    <Typography variant="body2" color="text.secondary" fontWeight={500}>
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
                <Card className="border-0 shadow-sm">
                    <CardHeader>
                        <CardTitle>Recent vendors</CardTitle>
                        <CardDescription>Last 5 vendors attributed to you</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {recentVendors.length === 0 ? (
                            <Box sx={{ textAlign: 'center', py: 4 }}>
                                <Users className="mx-auto mb-2 h-10 w-10 text-gray-300" />
                                <Typography variant="body2" color="text.secondary">
                                    No vendors yet. Share your referral code with one to get started.
                                </Typography>
                            </Box>
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
                                            transition: 'background-color 0.2s',
                                            '&:hover': { bgcolor: 'action.hover' },
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

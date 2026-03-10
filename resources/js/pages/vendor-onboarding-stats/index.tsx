import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';
import { DollarSign, Users, UserCheck, UserX } from 'lucide-react';

interface VendorOnboardingStats {
    total_vendors: number;
    tier1_vendors: number;
    tier2_vendors: number;
    total_onboarding_fees: string;
    tier1_onboarding_fees: string;
    tier2_onboarding_fees: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Vendor Onboarding Stats', href: '/dashboard/vendor-onboarding-stats' },
];

const formatCurrency = (value: string) =>
    parseFloat(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

export default function VendorOnboardingStatsPage({ stats }: { stats: VendorOnboardingStats }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Vendor Onboarding Stats" />

            <Box sx={{ display: 'flex', flexDirection: 'column', gap: 3, p: 3 }}>
                <Box>
                    <Typography sx={{ fontSize: '1.5rem', fontWeight: 700 }}>
                        Vendor Onboarding Stats
                    </Typography>
                    <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>
                        Overview of vendor counts and onboarding fees by tier
                    </Typography>
                </Box>

                {/* Totals */}
                <Box sx={{ display: 'grid', gap: 2, gridTemplateColumns: { xs: '1fr', sm: 'repeat(2, 1fr)' } }}>
                    <Card sx={{ py: 2, gap: 1 }}>
                        <CardHeader sx={{ display: 'flex', flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', px: 3, pb: 0, '& > *': { my: 0 } }}>
                            <CardTitle sx={{ fontSize: '0.875rem', fontWeight: 500 }}>Total Vendors</CardTitle>
                            <Users style={{ width: 16, height: 16, color: '#2563eb', flexShrink: 0 }} />
                        </CardHeader>
                        <CardContent>
                            <Box sx={{ fontSize: '1.5rem', fontWeight: 700 }}>{stats.total_vendors}</Box>
                            <Typography sx={{ fontSize: '0.75rem', color: 'text.secondary' }}>All registered vendors</Typography>
                        </CardContent>
                    </Card>

                    <Card sx={{ py: 2, gap: 1 }}>
                        <CardHeader sx={{ display: 'flex', flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', px: 3, pb: 0, '& > *': { my: 0 } }}>
                            <CardTitle sx={{ fontSize: '0.875rem', fontWeight: 500 }}>Total Onboarding Fees</CardTitle>
                            <DollarSign style={{ width: 16, height: 16, color: '#16a34a', flexShrink: 0 }} />
                        </CardHeader>
                        <CardContent>
                            <Box sx={{ fontSize: '1.5rem', fontWeight: 700 }}>GHS {formatCurrency(stats.total_onboarding_fees)}</Box>
                            <Typography sx={{ fontSize: '0.75rem', color: 'text.secondary' }}>From all approved applications</Typography>
                        </CardContent>
                    </Card>
                </Box>

                {/* Tier 1 */}
                <Box>
                    <Typography sx={{ fontSize: '1rem', fontWeight: 600, mb: 1 }}>Tier 1 — Registered Business</Typography>
                    <Box sx={{ display: 'grid', gap: 2, gridTemplateColumns: { xs: '1fr', sm: 'repeat(2, 1fr)' } }}>
                        <Card sx={{ py: 2, gap: 1, borderColor: '#bbf7d0', background: 'linear-gradient(135deg, #f0fdf4, #dcfce7)' }}>
                            <CardHeader sx={{ display: 'flex', flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', px: 3, pb: 0, '& > *': { my: 0 } }}>
                                <CardTitle sx={{ fontSize: '0.875rem', fontWeight: 500, color: '#14532d' }}>Tier 1 Vendors</CardTitle>
                                <UserCheck style={{ width: 16, height: 16, color: '#16a34a', flexShrink: 0 }} />
                            </CardHeader>
                            <CardContent>
                                <Box sx={{ fontSize: '1.5rem', fontWeight: 700, color: '#14532d' }}>{stats.tier1_vendors}</Box>
                                <Typography sx={{ fontSize: '0.75rem', color: '#15803d' }}>With business certificate</Typography>
                            </CardContent>
                        </Card>

                        <Card sx={{ py: 2, gap: 1, borderColor: '#bbf7d0', background: 'linear-gradient(135deg, #f0fdf4, #dcfce7)' }}>
                            <CardHeader sx={{ display: 'flex', flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', px: 3, pb: 0, '& > *': { my: 0 } }}>
                                <CardTitle sx={{ fontSize: '0.875rem', fontWeight: 500, color: '#14532d' }}>Tier 1 Onboarding Fees</CardTitle>
                                <DollarSign style={{ width: 16, height: 16, color: '#16a34a', flexShrink: 0 }} />
                            </CardHeader>
                            <CardContent>
                                <Box sx={{ fontSize: '1.5rem', fontWeight: 700, color: '#14532d' }}>GHS {formatCurrency(stats.tier1_onboarding_fees)}</Box>
                                <Typography sx={{ fontSize: '0.75rem', color: '#15803d' }}>Registered business fees</Typography>
                            </CardContent>
                        </Card>
                    </Box>
                </Box>

                {/* Tier 2 */}
                <Box>
                    <Typography sx={{ fontSize: '1rem', fontWeight: 600, mb: 1 }}>Tier 2 — Individual / Unregistered</Typography>
                    <Box sx={{ display: 'grid', gap: 2, gridTemplateColumns: { xs: '1fr', sm: 'repeat(2, 1fr)' } }}>
                        <Card sx={{ py: 2, gap: 1, borderColor: '#fde68a', background: 'linear-gradient(135deg, #fffbeb, #fef3c7)' }}>
                            <CardHeader sx={{ display: 'flex', flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', px: 3, pb: 0, '& > *': { my: 0 } }}>
                                <CardTitle sx={{ fontSize: '0.875rem', fontWeight: 500, color: '#78350f' }}>Tier 2 Vendors</CardTitle>
                                <UserX style={{ width: 16, height: 16, color: '#d97706', flexShrink: 0 }} />
                            </CardHeader>
                            <CardContent>
                                <Box sx={{ fontSize: '1.5rem', fontWeight: 700, color: '#78350f' }}>{stats.tier2_vendors}</Box>
                                <Typography sx={{ fontSize: '0.75rem', color: '#92400e' }}>Without business certificate</Typography>
                            </CardContent>
                        </Card>

                        <Card sx={{ py: 2, gap: 1, borderColor: '#fde68a', background: 'linear-gradient(135deg, #fffbeb, #fef3c7)' }}>
                            <CardHeader sx={{ display: 'flex', flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', px: 3, pb: 0, '& > *': { my: 0 } }}>
                                <CardTitle sx={{ fontSize: '0.875rem', fontWeight: 500, color: '#78350f' }}>Tier 2 Onboarding Fees</CardTitle>
                                <DollarSign style={{ width: 16, height: 16, color: '#d97706', flexShrink: 0 }} />
                            </CardHeader>
                            <CardContent>
                                <Box sx={{ fontSize: '1.5rem', fontWeight: 700, color: '#78350f' }}>GHS {formatCurrency(stats.tier2_onboarding_fees)}</Box>
                                <Typography sx={{ fontSize: '0.75rem', color: '#92400e' }}>Individual vendor fees</Typography>
                            </CardContent>
                        </Card>
                    </Box>
                </Box>
            </Box>
        </AppLayout>
    );
}

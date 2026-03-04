import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';
import { DollarSign, ShoppingCart, TrendingUp, Users } from 'lucide-react';
import {
    CartesianGrid,
    Legend,
    Line,
    LineChart,
    Pie,
    PieChart,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';

interface CommissionStats {
    summary: {
        total_orders: number;
        total_order_value: string;
        total_commission_earned: string;
        average_commission_rate: string;
        total_vendor_payouts: string;
    };
    tier_breakdown: Array<{
        tier_name: string;
        order_count: number;
        commission_earned: string;
    }>;
    top_vendors: Array<{
        vendor_name: string;
        order_count: number;
        total_sales: string;
        commission_generated: string;
    }>;
    trend_data: Array<{
        month_label: string;
        order_count: number;
        commission_earned: string;
        average_order_value: string;
    }>;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'Commission Statistics',
        href: '/dashboard/commission-statistics',
    },
];

const COLORS = ['#8b5cf6', '#6366f1', '#06b6d4', '#10b981'];

export default function CommissionStatistics({
    stats,
}: {
    stats: CommissionStats;
}) {
    const platformNet =
        parseFloat(stats.summary.total_commission_earned) -
        parseFloat(stats.summary.total_vendor_payouts);

    const tierChartData = stats.tier_breakdown.map((tier, index) => ({
        name: tier.tier_name.replace('Tier ', 'T'),
        value: parseFloat(tier.commission_earned),
        orders: tier.order_count,
        fill: COLORS[index % COLORS.length],
    }));

    const trendChartData = stats.trend_data
        .filter((item) => item.order_count > 0)
        .map((item) => ({
            month: item.month_label,
            commission: parseFloat(item.commission_earned),
            orders: item.order_count,
            avgOrder: parseFloat(item.average_order_value),
        }));

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Commission Statistics" />

            <Box sx={{ display: 'flex', flexDirection: 'column', gap: 3, p: 3 }}>
                {/* Summary Cards */}
                <Box sx={{ display: 'grid', gap: 2, gridTemplateColumns: { xs: '1fr', sm: 'repeat(2, 1fr)', md: 'repeat(3, 1fr)', lg: 'repeat(5, 1fr)' }, '& > *': { minWidth: 0 } }}>
                    <Card sx={{ py: 2, gap: 1 }}>
                        <CardHeader sx={{ display: 'flex', flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', px: 3, pb: 0, '& > *': { my: 0 } }}>
                            <CardTitle sx={{ fontSize: '0.875rem', fontWeight: 500 }}>
                                Total Orders
                            </CardTitle>
                            <ShoppingCart style={{ width: 16, height: 16, color: '#2563eb', flexShrink: 0 }} />
                        </CardHeader>
                        <CardContent>
                            <Box sx={{ fontSize: '1.5rem', fontWeight: 700 }}>
                                {stats.summary.total_orders}
                            </Box>
                            <Typography sx={{ fontSize: '0.75rem', color: 'text.secondary' }}>
                                All time orders
                            </Typography>
                        </CardContent>
                    </Card>

                    <Card sx={{ py: 2, gap: 1 }}>
                        <CardHeader sx={{ display: 'flex', flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', px: 3, pb: 0, '& > *': { my: 0 } }}>
                            <CardTitle sx={{ fontSize: '0.875rem', fontWeight: 500 }}>
                                Total Sales
                            </CardTitle>
                            <DollarSign style={{ width: 16, height: 16, color: '#16a34a', flexShrink: 0 }} />
                        </CardHeader>
                        <CardContent>
                            <Box sx={{ fontSize: '1.5rem', fontWeight: 700, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                                GHS{' '}
                                {parseFloat(
                                    stats.summary.total_order_value,
                                ).toLocaleString('en-US', {
                                    maximumFractionDigits: 2,
                                })}
                            </Box>
                            <Typography sx={{ fontSize: '0.75rem', color: 'text.secondary' }}>
                                Gross revenue
                            </Typography>
                        </CardContent>
                    </Card>

                    <Card sx={{ py: 2, gap: 1, borderColor: '#e9d5ff', background: 'linear-gradient(135deg, #faf5ff, #f3e8ff)' }}>
                        <CardHeader sx={{ display: 'flex', flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', px: 3, pb: 0, '& > *': { my: 0 } }}>
                            <CardTitle sx={{ fontSize: '0.875rem', fontWeight: 500, color: '#581c87' }}>
                                Platform Commission
                            </CardTitle>
                            <TrendingUp style={{ width: 16, height: 16, color: '#9333ea', flexShrink: 0 }} />
                        </CardHeader>
                        <CardContent>
                            <Box sx={{ fontSize: '1.5rem', fontWeight: 700, color: '#581c87', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                                GHS{' '}
                                {parseFloat(
                                    stats.summary.total_commission_earned,
                                ).toLocaleString('en-US', {
                                    maximumFractionDigits: 2,
                                })}
                            </Box>
                            <Typography sx={{ fontSize: '0.75rem', color: '#7e22ce' }}>
                                {stats.summary.average_commission_rate} average
                                rate
                            </Typography>
                        </CardContent>
                    </Card>

                    <Card sx={{ py: 2, gap: 1 }}>
                        <CardHeader sx={{ display: 'flex', flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', px: 3, pb: 0, '& > *': { my: 0 } }}>
                            <CardTitle sx={{ fontSize: '0.875rem', fontWeight: 500 }}>
                                Vendor Payouts
                            </CardTitle>
                            <Users style={{ width: 16, height: 16, color: '#ea580c', flexShrink: 0 }} />
                        </CardHeader>
                        <CardContent>
                            <Box sx={{ fontSize: '1.5rem', fontWeight: 700, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                                GHS{' '}
                                {parseFloat(
                                    stats.summary.total_vendor_payouts,
                                ).toLocaleString('en-US', {
                                    maximumFractionDigits: 2,
                                })}
                            </Box>
                            <Typography sx={{ fontSize: '0.75rem', color: 'text.secondary' }}>
                                Paid to vendors
                            </Typography>
                        </CardContent>
                    </Card>

                    <Card sx={{ py: 2, gap: 1, borderColor: '#a7f3d0', background: 'linear-gradient(135deg, #ecfdf5, #d1fae5)' }}>
                        <CardHeader sx={{ display: 'flex', flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', px: 3, pb: 0, '& > *': { my: 0 } }}>
                            <CardTitle sx={{ fontSize: '0.875rem', fontWeight: 500, color: '#064e3b' }}>
                                Net Income
                            </CardTitle>
                            <TrendingUp style={{ width: 16, height: 16, color: '#059669', flexShrink: 0 }} />
                        </CardHeader>
                        <CardContent>
                            <Box sx={{ fontSize: '1.5rem', fontWeight: 700, color: '#064e3b', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                                GHS{' '}
                                {platformNet.toLocaleString('en-US', {
                                    maximumFractionDigits: 2,
                                })}
                            </Box>
                            <Typography sx={{ fontSize: '0.75rem', color: '#047857' }}>
                                Commission - Payouts
                            </Typography>
                        </CardContent>
                    </Card>
                </Box>

                {/* Charts */}
                <Box sx={{ display: 'grid', gap: 3, gridTemplateColumns: { xs: '1fr', md: 'repeat(2, 1fr)' } }}>
                    {/* Commission by Tier */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Commission by Tier</CardTitle>
                            <CardDescription>
                                Commission earned from each vendor tier
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <ResponsiveContainer width="100%" height={300}>
                                <PieChart>
                                    <Pie
                                        data={tierChartData}
                                        cx="50%"
                                        cy="50%"
                                        labelLine={false}
                                        label={(entry) =>
                                            `${entry.name}: GHS ${entry.value.toFixed(0)}`
                                        }
                                        outerRadius={80}
                                        dataKey="value"
                                    />
                                    <Tooltip
                                        formatter={(value) => {
                                            if (
                                                typeof value === 'number' &&
                                                !isNaN(value)
                                            ) {
                                                return `GHS ${value.toFixed(2)}`;
                                            }
                                            return value;
                                        }}
                                    />
                                </PieChart>
                            </ResponsiveContainer>
                        </CardContent>
                    </Card>

                    {/* Tier Breakdown Table */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Tier Breakdown</CardTitle>
                            <CardDescription>
                                Orders and earnings by tier
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Box sx={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
                                {stats.tier_breakdown.map((tier, index) => (
                                    <Box
                                        key={index}
                                        sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', borderRadius: 2, bgcolor: 'action.selected', p: 1.5 }}
                                    >
                                        <Box>
                                            <Typography sx={{ fontWeight: 500 }}>
                                                {tier.tier_name}
                                            </Typography>
                                            <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>
                                                {tier.order_count} orders
                                            </Typography>
                                        </Box>
                                        <Box sx={{ textAlign: 'right' }}>
                                            <Box sx={{ fontSize: '1.125rem', fontWeight: 700 }}>
                                                GHS{' '}
                                                {parseFloat(
                                                    tier.commission_earned,
                                                ).toFixed(2)}
                                            </Box>
                                            <Box
                                                sx={{ fontSize: '0.75rem', fontWeight: 500 }}
                                                style={{
                                                    color: COLORS[
                                                        index % COLORS.length
                                                    ],
                                                }}
                                            >
                                                {(
                                                    (parseFloat(
                                                        tier.commission_earned,
                                                    ) /
                                                        parseFloat(
                                                            stats.summary
                                                                .total_commission_earned,
                                                        )) *
                                                    100
                                                ).toFixed(1)}
                                                %
                                            </Box>
                                        </Box>
                                    </Box>
                                ))}
                            </Box>
                        </CardContent>
                    </Card>
                </Box>

                {/* Monthly Trend */}
                {trendChartData.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Monthly Commission Trend</CardTitle>
                            <CardDescription>
                                Commission and orders over the last 12 months
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <ResponsiveContainer width="100%" height={300}>
                                <LineChart data={trendChartData}>
                                    <CartesianGrid strokeDasharray="3 3" />
                                    <XAxis dataKey="month" />
                                    <YAxis yAxisId="left" />
                                    <YAxis
                                        yAxisId="right"
                                        orientation="right"
                                    />
                                    <Tooltip
                                        formatter={(value) => {
                                            if (typeof value === 'number') {
                                                return value > 100
                                                    ? `GHS ${value.toFixed(2)}`
                                                    : value.toFixed(0);
                                            }
                                            return value;
                                        }}
                                    />
                                    <Legend />
                                    <Line
                                        yAxisId="left"
                                        type="monotone"
                                        dataKey="commission"
                                        stroke="#8b5cf6"
                                        name="Commission (GHS)"
                                    />
                                    <Line
                                        yAxisId="right"
                                        type="monotone"
                                        dataKey="orders"
                                        stroke="#06b6d4"
                                        name="Orders"
                                    />
                                </LineChart>
                            </ResponsiveContainer>
                        </CardContent>
                    </Card>
                )}

                {/* Top Vendors */}
                <Card>
                    <CardHeader>
                        <CardTitle>Top Vendors</CardTitle>
                        <CardDescription>
                            Vendors generating the most commission
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Box sx={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
                            {stats.top_vendors.map((vendor, index) => (
                                <Box
                                    key={index}
                                    sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', borderRadius: 2, border: 1, borderColor: 'divider', p: 1.5 }}
                                >
                                    <Box sx={{ flex: 1 }}>
                                        <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                                            <Box sx={{
                                                display: 'flex',
                                                width: 32,
                                                height: 32,
                                                alignItems: 'center',
                                                justifyContent: 'center',
                                                borderRadius: '50%',
                                                background: 'linear-gradient(135deg, #8b5cf6, #9333ea)',
                                                fontSize: '0.875rem',
                                                fontWeight: 700,
                                                color: '#fff',
                                            }}>
                                                {index + 1}
                                            </Box>
                                            <Box>
                                                <Typography sx={{ fontWeight: 500 }}>
                                                    {vendor.vendor_name}
                                                </Typography>
                                                <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>
                                                    {vendor.order_count} orders
                                                    {' \u2022 '}GHS{' '}
                                                    {parseFloat(
                                                        vendor.total_sales,
                                                    ).toLocaleString('en-US', {
                                                        maximumFractionDigits: 2,
                                                    })}{' '}
                                                    sales
                                                </Typography>
                                            </Box>
                                        </Box>
                                    </Box>
                                    <Box sx={{ textAlign: 'right' }}>
                                        <Box sx={{ fontWeight: 700 }}>
                                            GHS{' '}
                                            {parseFloat(
                                                vendor.commission_generated,
                                            ).toLocaleString('en-US', {
                                                maximumFractionDigits: 2,
                                            })}
                                        </Box>
                                        <Typography sx={{ fontSize: '0.75rem', color: 'text.secondary' }}>
                                            commission
                                        </Typography>
                                    </Box>
                                </Box>
                            ))}
                        </Box>
                    </CardContent>
                </Card>
            </Box>
        </AppLayout>
    );
}

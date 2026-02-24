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

            <div className="space-y-6 p-6">
                {/* Summary Cards */}
                <div className="grid gap-4 md:grid-cols-5">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Total Orders
                            </CardTitle>
                            <ShoppingCart className="h-4 w-4 text-blue-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {stats.summary.total_orders}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                All time orders
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Total Sales
                            </CardTitle>
                            <DollarSign className="h-4 w-4 text-green-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                GHS{' '}
                                {parseFloat(
                                    stats.summary.total_order_value,
                                ).toLocaleString('en-US', {
                                    maximumFractionDigits: 2,
                                })}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Gross revenue
                            </p>
                        </CardContent>
                    </Card>

                    <Card className="border-purple-200 bg-gradient-to-br from-purple-50 to-purple-100">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium text-purple-900">
                                Platform Commission
                            </CardTitle>
                            <TrendingUp className="h-4 w-4 text-purple-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-purple-900">
                                GHS{' '}
                                {parseFloat(
                                    stats.summary.total_commission_earned,
                                ).toLocaleString('en-US', {
                                    maximumFractionDigits: 2,
                                })}
                            </div>
                            <p className="text-xs text-purple-700">
                                {stats.summary.average_commission_rate} average
                                rate
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Vendor Payouts
                            </CardTitle>
                            <Users className="h-4 w-4 text-orange-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                GHS{' '}
                                {parseFloat(
                                    stats.summary.total_vendor_payouts,
                                ).toLocaleString('en-US', {
                                    maximumFractionDigits: 2,
                                })}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Paid to vendors
                            </p>
                        </CardContent>
                    </Card>

                    <Card className="border-emerald-200 bg-gradient-to-br from-emerald-50 to-emerald-100">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium text-emerald-900">
                                Net Income
                            </CardTitle>
                            <TrendingUp className="h-4 w-4 text-emerald-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-emerald-900">
                                GHS{' '}
                                {platformNet.toLocaleString('en-US', {
                                    maximumFractionDigits: 2,
                                })}
                            </div>
                            <p className="text-xs text-emerald-700">
                                Commission - Payouts
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {/* Charts */}
                <div className="grid gap-6 md:grid-cols-2">
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
                            <div className="space-y-4">
                                {stats.tier_breakdown.map((tier, index) => (
                                    <div
                                        key={index}
                                        className="flex items-center justify-between rounded-lg bg-muted p-3"
                                    >
                                        <div>
                                            <p className="font-medium">
                                                {tier.tier_name}
                                            </p>
                                            <p className="text-sm text-muted-foreground">
                                                {tier.order_count} orders
                                            </p>
                                        </div>
                                        <div className="text-right">
                                            <div className="text-lg font-bold">
                                                GHS{' '}
                                                {parseFloat(
                                                    tier.commission_earned,
                                                ).toFixed(2)}
                                            </div>
                                            <div
                                                className="text-xs font-medium"
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
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                </div>

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
                        <div className="space-y-4">
                            {stats.top_vendors.map((vendor, index) => (
                                <div
                                    key={index}
                                    className="flex items-center justify-between rounded-lg border p-3"
                                >
                                    <div className="flex-1">
                                        <div className="flex items-center gap-2">
                                            <div className="flex h-8 w-8 items-center justify-center rounded-full bg-gradient-to-br from-purple-500 to-purple-600 text-sm font-bold text-white">
                                                {index + 1}
                                            </div>
                                            <div>
                                                <p className="font-medium">
                                                    {vendor.vendor_name}
                                                </p>
                                                <p className="text-sm text-muted-foreground">
                                                    {vendor.order_count} orders
                                                    • GHS{' '}
                                                    {parseFloat(
                                                        vendor.total_sales,
                                                    ).toLocaleString('en-US', {
                                                        maximumFractionDigits: 2,
                                                    })}{' '}
                                                    sales
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                    <div className="text-right">
                                        <div className="font-bold">
                                            GHS{' '}
                                            {parseFloat(
                                                vendor.commission_generated,
                                            ).toLocaleString('en-US', {
                                                maximumFractionDigits: 2,
                                            })}
                                        </div>
                                        <p className="text-xs text-muted-foreground">
                                            commission
                                        </p>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}

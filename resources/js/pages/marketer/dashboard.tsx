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

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-6">
                {/* Welcome Section */}
                <div className="relative overflow-hidden rounded-xl bg-gradient-to-br from-primary to-secondary p-6 text-white shadow-lg">
                    <div className="relative z-10">
                        <h2 className="text-2xl font-bold">
                            Welcome back, {auth.user?.name}!
                        </h2>
                        <p className="mt-2 text-white/80">
                            Track your targets and quarterly earnings.
                        </p>
                    </div>
                </div>

                {/* Stats Grid */}
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard
                        title="Active Targets"
                        value={stats.active_targets}
                        icon={Target}
                        bgColor="bg-card"
                        iconBgColor="bg-blue-500"
                    />
                    <StatCard
                        title="Completed"
                        value={stats.completed_targets}
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
                        title={`Q${stats.current_quarter} ${stats.current_year} Earnings`}
                        value={`GHS ${stats.quarterly_earnings.toFixed(2)}`}
                        icon={Calendar}
                        bgColor="bg-card"
                        iconBgColor="bg-orange-500"
                    />
                </div>

                {/* Active Targets */}
                <Card>
                    <CardHeader>
                        <CardTitle>Active Targets</CardTitle>
                        <CardDescription>
                            Your current performance targets
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-6">
                            {active_targets.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    No active targets assigned yet
                                </p>
                            ) : (
                                active_targets.map((target) => (
                                    <div
                                        key={target.id}
                                        className="space-y-2 rounded-lg border p-4"
                                    >
                                        <div className="flex items-center justify-between">
                                            <div>
                                                <p className="font-medium">
                                                    {target.target_type
                                                        .replace('_', ' ')
                                                        .toUpperCase()}
                                                </p>
                                                <p className="text-sm text-muted-foreground">
                                                    Target:{' '}
                                                    {target.target_value} |
                                                    Current:{' '}
                                                    {target.current_value}
                                                </p>
                                            </div>
                                            <Badge variant="default">
                                                {target.completion_percentage.toFixed(
                                                    0,
                                                )}
                                                %
                                            </Badge>
                                        </div>
                                        <div className="h-2 w-full rounded-full bg-muted">
                                            <div
                                                className="h-2 rounded-full bg-primary transition-all"
                                                style={{
                                                    width: `${target.completion_percentage}%`,
                                                }}
                                            />
                                        </div>
                                        <div className="flex justify-between text-sm text-muted-foreground">
                                            <span>
                                                Ends:{' '}
                                                {new Date(
                                                    target.end_date,
                                                ).toLocaleDateString()}
                                            </span>
                                            <span>
                                                Sign-on Bonus: GHS{' '}
                                                {target.base_bonus}
                                            </span>
                                        </div>
                                    </div>
                                ))
                            )}
                        </div>
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
                        <div className="space-y-4">
                            {recent_sign_on_bonuses.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    No sign-on bonuses yet
                                </p>
                            ) : (
                                recent_sign_on_bonuses.map((earning) => (
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
                                                {new Date(
                                                    earning.earned_at,
                                                ).toLocaleDateString()}
                                            </p>
                                        </div>
                                        <Badge
                                            variant={
                                                earning.status === 'paid'
                                                    ? 'default'
                                                    : 'secondary'
                                            }
                                        >
                                            {earning.status}
                                        </Badge>
                                    </div>
                                ))
                            )}
                        </div>
                    </CardContent>
                </Card>

                <Card className="bg-blue-50 dark:bg-blue-950">
                    <CardHeader>
                        <CardTitle>Quarterly Earnings Info</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p className="text-sm text-muted-foreground">
                            Your sign-on bonuses are added to your quarterly
                            salary payment. Current quarter (Q
                            {stats.current_quarter} {stats.current_year})
                            earnings:{' '}
                            <strong>
                                GHS {stats.quarterly_earnings.toFixed(2)}
                            </strong>
                        </p>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}

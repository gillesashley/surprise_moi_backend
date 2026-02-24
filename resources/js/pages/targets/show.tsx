import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

interface Target {
    id: number;
    user: {
        id: number;
        name: string;
        email: string;
        role: string;
    };
    assignedBy?: {
        id: number;
        name: string;
        email: string;
    };
    target_type: string;
    target_value: number;
    current_value: number;
    bonus_amount: number;
    overachievement_rate: number;
    period_type: string;
    start_date: string;
    end_date: string;
    status: string;
    notes?: string;
    created_at: string;
}

interface Props {
    target: Target;
}

const formatTargetType = (type: string) => {
    return type
        .split('_')
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
};

const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('en-GH', {
        style: 'currency',
        currency: 'GHS',
    }).format(amount);
};

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-GH', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
};

const getStatusBadge = (status: string) => {
    const statusStyles = {
        active: 'bg-green-100 text-green-800',
        completed: 'bg-blue-100 text-blue-800',
        expired: 'bg-gray-100 text-gray-800',
        cancelled: 'bg-red-100 text-red-800',
    };
    return (
        <span
            className={`rounded-full px-3 py-1 text-sm font-medium ${
                statusStyles[status as keyof typeof statusStyles] || ''
            }`}
        >
            {status.charAt(0).toUpperCase() + status.slice(1)}
        </span>
    );
};

export default function TargetShow({ target }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Targets',
            href: '/dashboard/targets',
        },
        {
            title: `Target #${target.id}`,
            href: `/dashboard/targets/${target.id}`,
        },
    ];

    const progressPercentage =
        target.target_value > 0
            ? Math.min((target.current_value / target.target_value) * 100, 100)
            : 0;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Target #${target.id}`} />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-3xl font-bold">Target Details</h1>
                    <div className="flex gap-3">
                        <Button variant="outline" asChild>
                            <Link href="/dashboard/targets">
                                <ArrowLeft className="mr-2 size-4" />
                                Back to Targets
                            </Link>
                        </Button>
                        <Button asChild>
                            <Link href={`/dashboard/targets/${target.id}/edit`}>
                                Edit Target
                            </Link>
                        </Button>
                    </div>
                </div>

                <div className="grid gap-6 md:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Target Information</CardTitle>
                            <CardDescription>
                                Basic details about this target
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div>
                                <label className="text-sm font-medium text-muted-foreground">
                                    Target ID
                                </label>
                                <p className="text-lg font-semibold">
                                    #{target.id}
                                </p>
                            </div>
                            <div>
                                <label className="text-sm font-medium text-muted-foreground">
                                    Status
                                </label>
                                <div className="mt-1">
                                    {getStatusBadge(target.status)}
                                </div>
                            </div>
                            <div>
                                <label className="text-sm font-medium text-muted-foreground">
                                    Target Type
                                </label>
                                <p className="text-lg font-semibold">
                                    {formatTargetType(target.target_type)}
                                </p>
                            </div>
                            <div>
                                <label className="text-sm font-medium text-muted-foreground">
                                    Period Type
                                </label>
                                <p className="text-lg font-semibold capitalize">
                                    {target.period_type}
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Assignment Details</CardTitle>
                            <CardDescription>
                                Who this target is assigned to
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div>
                                <label className="text-sm font-medium text-muted-foreground">
                                    Assigned To
                                </label>
                                <p className="text-lg font-semibold">
                                    {target.user.name}
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    {target.user.email}
                                </p>
                                <p className="text-sm text-muted-foreground capitalize">
                                    Role: {target.user.role.replace('_', ' ')}
                                </p>
                            </div>
                            {target.assignedBy && (
                                <div>
                                    <label className="text-sm font-medium text-muted-foreground">
                                        Assigned By
                                    </label>
                                    <p className="text-lg font-semibold">
                                        {target.assignedBy.name}
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        {target.assignedBy.email}
                                    </p>
                                </div>
                            )}
                            <div>
                                <label className="text-sm font-medium text-muted-foreground">
                                    Created
                                </label>
                                <p className="text-sm">
                                    {formatDate(target.created_at)}
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Progress & Performance</CardTitle>
                            <CardDescription>
                                Current progress towards target
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div>
                                <label className="text-sm font-medium text-muted-foreground">
                                    Target Value
                                </label>
                                <p className="text-2xl font-bold">
                                    {target.target_type === 'revenue_generated'
                                        ? formatCurrency(target.target_value)
                                        : `${target.target_value} signups`}
                                </p>
                            </div>
                            <div>
                                <label className="text-sm font-medium text-muted-foreground">
                                    Current Value
                                </label>
                                <p className="text-2xl font-bold">
                                    {target.target_type === 'revenue_generated'
                                        ? formatCurrency(target.current_value)
                                        : `${target.current_value} signups`}
                                </p>
                            </div>
                            <div>
                                <label className="text-sm font-medium text-muted-foreground">
                                    Progress
                                </label>
                                <div className="mt-1 space-y-2">
                                    <div className="flex justify-between text-sm">
                                        <span className="font-medium">
                                            {progressPercentage.toFixed(1)}%
                                        </span>
                                        <span className="text-muted-foreground">
                                            {target.current_value} /{' '}
                                            {target.target_value}
                                        </span>
                                    </div>
                                    <div className="h-2 overflow-hidden rounded-full bg-gray-200">
                                        <div
                                            className="h-full bg-primary transition-all"
                                            style={{
                                                width: `${progressPercentage}%`,
                                            }}
                                        />
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Rewards & Timeline</CardTitle>
                            <CardDescription>
                                Bonus structure and dates
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div>
                                <label className="text-sm font-medium text-muted-foreground">
                                    Bonus Amount
                                </label>
                                <p className="text-2xl font-bold">
                                    {formatCurrency(target.bonus_amount)}
                                </p>
                            </div>
                            <div>
                                <label className="text-sm font-medium text-muted-foreground">
                                    Overachievement Rate
                                </label>
                                <p className="text-lg font-semibold">
                                    {target.overachievement_rate}%
                                </p>
                            </div>
                            <div>
                                <label className="text-sm font-medium text-muted-foreground">
                                    Start Date
                                </label>
                                <p className="text-sm">
                                    {formatDate(target.start_date)}
                                </p>
                            </div>
                            <div>
                                <label className="text-sm font-medium text-muted-foreground">
                                    End Date
                                </label>
                                <p className="text-sm">
                                    {formatDate(target.end_date)}
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    {target.notes && (
                        <Card className="md:col-span-2">
                            <CardHeader>
                                <CardTitle>Notes</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <p className="text-sm whitespace-pre-wrap">
                                    {target.notes}
                                </p>
                            </CardContent>
                        </Card>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}

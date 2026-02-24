import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { Calendar, Target, TrendingUp } from 'lucide-react';

interface TargetItem {
    id: number;
    target_type: string;
    target_value: number;
    current_value: number;
    period: string;
    start_date: string;
    end_date: string;
    status: string;
}

interface Props {
    targets: {
        data: TargetItem[];
        current_page: number;
        last_page: number;
        total: number;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'My Targets',
        href: '/marketer/targets',
    },
];

const getStatusBadge = (status: string) => {
    const statusConfig = {
        active: { label: 'Active', variant: 'default' as const },
        completed: { label: 'Completed', variant: 'default' as const },
        pending: { label: 'Pending', variant: 'secondary' as const },
        expired: { label: 'Expired', variant: 'destructive' as const },
    };

    const config = statusConfig[status as keyof typeof statusConfig] || {
        label: status,
        variant: 'outline' as const,
    };

    return <Badge variant={config.variant}>{config.label}</Badge>;
};

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-GH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

const formatTargetType = (type: string) => {
    return type
        .split('_')
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
};

const calculateProgress = (current: number, target: number) => {
    return Math.min(100, Math.round((current / target) * 100));
};

const getQuarterFromPeriod = (period: string): string => {
    const match = period.match(/Q(\d)/);
    return match ? `Q${match[1]}` : period;
};

export default function MarketerTargets({ targets }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="My Targets" />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-6">
                <div>
                    <h1 className="text-3xl font-bold">My Quarterly Targets</h1>
                    <p className="mt-1 text-muted-foreground">
                        Track your quarterly targets and progress
                    </p>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Target List</CardTitle>
                        <CardDescription>
                            Total {targets.total} target
                            {targets.total !== 1 ? 's' : ''}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {targets.data.length === 0 ? (
                            <div className="py-12 text-center">
                                <p className="text-muted-foreground">
                                    No targets assigned yet. Check back later
                                    for new quarterly assignments!
                                </p>
                            </div>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Quarter</TableHead>
                                        <TableHead>Target Type</TableHead>
                                        <TableHead>Progress</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Dates</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {targets.data.map((target) => {
                                        const progress = calculateProgress(
                                            target.current_value,
                                            target.target_value,
                                        );
                                        return (
                                            <TableRow key={target.id}>
                                                <TableCell>
                                                    <div className="flex items-center gap-2">
                                                        <TrendingUp className="h-4 w-4 text-muted-foreground" />
                                                        <span className="font-medium">
                                                            {getQuarterFromPeriod(
                                                                target.period,
                                                            )}
                                                        </span>
                                                    </div>
                                                </TableCell>
                                                <TableCell className="font-medium">
                                                    {formatTargetType(
                                                        target.target_type,
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    <div className="space-y-1">
                                                        <div className="flex items-center gap-2">
                                                            <Target className="h-4 w-4 text-muted-foreground" />
                                                            <span className="text-sm font-medium">
                                                                {
                                                                    target.current_value
                                                                }{' '}
                                                                /{' '}
                                                                {
                                                                    target.target_value
                                                                }
                                                            </span>
                                                        </div>
                                                        <div className="h-2 w-full overflow-hidden rounded-full bg-secondary">
                                                            <div
                                                                className="h-full bg-primary transition-all"
                                                                style={{
                                                                    width: `${progress}%`,
                                                                }}
                                                            />
                                                        </div>
                                                        <span className="text-xs text-muted-foreground">
                                                            {progress}%
                                                        </span>
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    {getStatusBadge(
                                                        target.status,
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    <div className="flex flex-col gap-1 text-sm">
                                                        <div className="flex items-center gap-2">
                                                            <Calendar className="h-3 w-3 text-muted-foreground" />
                                                            {formatDate(
                                                                target.start_date,
                                                            )}
                                                        </div>
                                                        <span className="text-muted-foreground">
                                                            to{' '}
                                                            {formatDate(
                                                                target.end_date,
                                                            )}
                                                        </span>
                                                    </div>
                                                </TableCell>
                                            </TableRow>
                                        );
                                    })}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}

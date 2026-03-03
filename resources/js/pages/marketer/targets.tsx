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
import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';
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

            <Box sx={{ display: 'flex', height: '100%', flex: 1, flexDirection: 'column', gap: 3, overflowX: 'auto', p: 3 }}>
                <Box>
                    <Typography variant="h4" fontWeight={700}>My Quarterly Targets</Typography>
                    <Typography color="text.secondary" sx={{ mt: 0.5 }}>
                        Track your quarterly targets and progress
                    </Typography>
                </Box>

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
                            <Box sx={{ py: 6, textAlign: 'center' }}>
                                <Typography color="text.secondary">
                                    No targets assigned yet. Check back later
                                    for new quarterly assignments!
                                </Typography>
                            </Box>
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
                                                    <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                                                        <TrendingUp style={{ width: 16, height: 16, color: 'gray' }} />
                                                        <Typography component="span" fontWeight={500}>
                                                            {getQuarterFromPeriod(
                                                                target.period,
                                                            )}
                                                        </Typography>
                                                    </Box>
                                                </TableCell>
                                                <TableCell sx={{ fontWeight: 500 }}>
                                                    {formatTargetType(
                                                        target.target_type,
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    <Box sx={{ display: 'flex', flexDirection: 'column', gap: 0.5 }}>
                                                        <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                                                            <Target style={{ width: 16, height: 16, color: 'gray' }} />
                                                            <Typography variant="body2" fontWeight={500}>
                                                                {
                                                                    target.current_value
                                                                }{' '}
                                                                /{' '}
                                                                {
                                                                    target.target_value
                                                                }
                                                            </Typography>
                                                        </Box>
                                                        <Box
                                                            sx={{
                                                                height: 8,
                                                                width: '100%',
                                                                overflow: 'hidden',
                                                                borderRadius: 4,
                                                                bgcolor: 'secondary.main',
                                                            }}
                                                        >
                                                            <Box
                                                                sx={{
                                                                    height: '100%',
                                                                    bgcolor: 'primary.main',
                                                                    transition: 'all 0.2s',
                                                                }}
                                                                style={{
                                                                    width: `${progress}%`,
                                                                }}
                                                            />
                                                        </Box>
                                                        <Typography variant="caption" color="text.secondary">
                                                            {progress}%
                                                        </Typography>
                                                    </Box>
                                                </TableCell>
                                                <TableCell>
                                                    {getStatusBadge(
                                                        target.status,
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    <Box sx={{ display: 'flex', flexDirection: 'column', gap: 0.5, fontSize: '0.875rem' }}>
                                                        <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                                                            <Calendar style={{ width: 12, height: 12, color: 'gray' }} />
                                                            {formatDate(
                                                                target.start_date,
                                                            )}
                                                        </Box>
                                                        <Typography variant="body2" color="text.secondary">
                                                            to{' '}
                                                            {formatDate(
                                                                target.end_date,
                                                            )}
                                                        </Typography>
                                                    </Box>
                                                </TableCell>
                                            </TableRow>
                                        );
                                    })}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>
            </Box>
        </AppLayout>
    );
}

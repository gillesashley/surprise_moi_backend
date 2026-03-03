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
import Box from '@mui/material/Box';
import LinearProgress from '@mui/material/LinearProgress';
import Typography from '@mui/material/Typography';
import { Head } from '@inertiajs/react';
import { Calendar, Target } from 'lucide-react';

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
        href: '/field-agent/targets',
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

export default function FieldAgentTargets({ targets }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="My Targets" />

            <Box sx={{ display: 'flex', flex: 1, flexDirection: 'column', gap: 3, overflowX: 'auto', p: 3 }}>
                <Box>
                    <Typography variant="h4" fontWeight={700}>
                        My Targets
                    </Typography>
                    <Typography variant="body2" color="text.secondary" sx={{ mt: 0.5 }}>
                        Track your assigned targets and progress
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
                                    for new assignments!
                                </Typography>
                            </Box>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Target Type</TableHead>
                                        <TableHead>Progress</TableHead>
                                        <TableHead>Period</TableHead>
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
                                                <TableCell style={{ fontWeight: 500 }}>
                                                    {formatTargetType(
                                                        target.target_type,
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    <Box sx={{ display: 'flex', flexDirection: 'column', gap: 0.5 }}>
                                                        <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                                                            <Target style={{ width: 16, height: 16, color: 'gray' }} />
                                                            <Typography variant="body2" fontWeight={500}>
                                                                {target.current_value} / {target.target_value}
                                                            </Typography>
                                                        </Box>
                                                        <LinearProgress
                                                            variant="determinate"
                                                            value={progress}
                                                            sx={{ borderRadius: 1, height: 8 }}
                                                        />
                                                        <Typography variant="caption" color="text.secondary">
                                                            {progress}%
                                                        </Typography>
                                                    </Box>
                                                </TableCell>
                                                <TableCell>
                                                    {target.period}
                                                </TableCell>
                                                <TableCell>
                                                    {getStatusBadge(
                                                        target.status,
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    <Box sx={{ display: 'flex', flexDirection: 'column', gap: 0.5 }}>
                                                        <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                                                            <Calendar style={{ width: 12, height: 12, color: 'gray' }} />
                                                            <Typography variant="body2">
                                                                {formatDate(target.start_date)}
                                                            </Typography>
                                                        </Box>
                                                        <Typography variant="body2" color="text.secondary">
                                                            to {formatDate(target.end_date)}
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

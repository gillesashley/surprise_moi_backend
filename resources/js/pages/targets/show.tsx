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
import Box from '@mui/material/Box';
import Chip from '@mui/material/Chip';
import Typography from '@mui/material/Typography';
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

const getStatusChipColor = (status: string): 'success' | 'info' | 'default' | 'error' => {
    const statusMap: Record<string, 'success' | 'info' | 'default' | 'error'> = {
        active: 'success',
        completed: 'info',
        expired: 'default',
        cancelled: 'error',
    };
    return statusMap[status] || 'default';
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

            <Box sx={{ display: 'flex', height: '100%', flex: 1, flexDirection: 'column', gap: 2, p: 2 }}>
                <Box sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                    <Typography variant="h4" sx={{ fontWeight: 700 }}>Target Details</Typography>
                    <Box sx={{ display: 'flex', gap: 1.5 }}>
                        <Button variant="outline" asChild>
                            <Link href="/dashboard/targets">
                                <ArrowLeft style={{ marginRight: 8, width: 16, height: 16 }} />
                                Back to Targets
                            </Link>
                        </Button>
                        <Button asChild>
                            <Link href={`/dashboard/targets/${target.id}/edit`}>
                                Edit Target
                            </Link>
                        </Button>
                    </Box>
                </Box>

                <Box sx={{ display: 'grid', gap: 3, gridTemplateColumns: { md: 'repeat(2, 1fr)' } }}>
                    <Card>
                        <CardHeader>
                            <CardTitle>Target Information</CardTitle>
                            <CardDescription>
                                Basic details about this target
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Box sx={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
                                <Box>
                                    <Typography component="label" sx={{ fontSize: '0.875rem', fontWeight: 500, color: 'text.secondary' }}>
                                        Target ID
                                    </Typography>
                                    <Typography sx={{ fontSize: '1.125rem', fontWeight: 600 }}>
                                        #{target.id}
                                    </Typography>
                                </Box>
                                <Box>
                                    <Typography component="label" sx={{ fontSize: '0.875rem', fontWeight: 500, color: 'text.secondary' }}>
                                        Status
                                    </Typography>
                                    <Box sx={{ mt: 0.5 }}>
                                        <Chip
                                            label={target.status.charAt(0).toUpperCase() + target.status.slice(1)}
                                            color={getStatusChipColor(target.status)}
                                            size="small"
                                            variant="outlined"
                                        />
                                    </Box>
                                </Box>
                                <Box>
                                    <Typography component="label" sx={{ fontSize: '0.875rem', fontWeight: 500, color: 'text.secondary' }}>
                                        Target Type
                                    </Typography>
                                    <Typography sx={{ fontSize: '1.125rem', fontWeight: 600 }}>
                                        {formatTargetType(target.target_type)}
                                    </Typography>
                                </Box>
                                <Box>
                                    <Typography component="label" sx={{ fontSize: '0.875rem', fontWeight: 500, color: 'text.secondary' }}>
                                        Period Type
                                    </Typography>
                                    <Typography sx={{ fontSize: '1.125rem', fontWeight: 600, textTransform: 'capitalize' }}>
                                        {target.period_type}
                                    </Typography>
                                </Box>
                            </Box>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Assignment Details</CardTitle>
                            <CardDescription>
                                Who this target is assigned to
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Box sx={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
                                <Box>
                                    <Typography component="label" sx={{ fontSize: '0.875rem', fontWeight: 500, color: 'text.secondary' }}>
                                        Assigned To
                                    </Typography>
                                    <Typography sx={{ fontSize: '1.125rem', fontWeight: 600 }}>
                                        {target.user.name}
                                    </Typography>
                                    <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>
                                        {target.user.email}
                                    </Typography>
                                    <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary', textTransform: 'capitalize' }}>
                                        Role: {target.user.role.replace('_', ' ')}
                                    </Typography>
                                </Box>
                                {target.assignedBy && (
                                    <Box>
                                        <Typography component="label" sx={{ fontSize: '0.875rem', fontWeight: 500, color: 'text.secondary' }}>
                                            Assigned By
                                        </Typography>
                                        <Typography sx={{ fontSize: '1.125rem', fontWeight: 600 }}>
                                            {target.assignedBy.name}
                                        </Typography>
                                        <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>
                                            {target.assignedBy.email}
                                        </Typography>
                                    </Box>
                                )}
                                <Box>
                                    <Typography component="label" sx={{ fontSize: '0.875rem', fontWeight: 500, color: 'text.secondary' }}>
                                        Created
                                    </Typography>
                                    <Typography sx={{ fontSize: '0.875rem' }}>
                                        {formatDate(target.created_at)}
                                    </Typography>
                                </Box>
                            </Box>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Progress & Performance</CardTitle>
                            <CardDescription>
                                Current progress towards target
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Box sx={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
                                <Box>
                                    <Typography component="label" sx={{ fontSize: '0.875rem', fontWeight: 500, color: 'text.secondary' }}>
                                        Target Value
                                    </Typography>
                                    <Typography sx={{ fontSize: '1.5rem', fontWeight: 700 }}>
                                        {target.target_type === 'revenue_generated'
                                            ? formatCurrency(target.target_value)
                                            : `${target.target_value} signups`}
                                    </Typography>
                                </Box>
                                <Box>
                                    <Typography component="label" sx={{ fontSize: '0.875rem', fontWeight: 500, color: 'text.secondary' }}>
                                        Current Value
                                    </Typography>
                                    <Typography sx={{ fontSize: '1.5rem', fontWeight: 700 }}>
                                        {target.target_type === 'revenue_generated'
                                            ? formatCurrency(target.current_value)
                                            : `${target.current_value} signups`}
                                    </Typography>
                                </Box>
                                <Box>
                                    <Typography component="label" sx={{ fontSize: '0.875rem', fontWeight: 500, color: 'text.secondary' }}>
                                        Progress
                                    </Typography>
                                    <Box sx={{ mt: 0.5, display: 'flex', flexDirection: 'column', gap: 1 }}>
                                        <Box sx={{ display: 'flex', justifyContent: 'space-between', fontSize: '0.875rem' }}>
                                            <Box component="span" sx={{ fontWeight: 500 }}>
                                                {progressPercentage.toFixed(1)}%
                                            </Box>
                                            <Box component="span" sx={{ color: 'text.secondary' }}>
                                                {target.current_value} /{' '}
                                                {target.target_value}
                                            </Box>
                                        </Box>
                                        <Box sx={{ height: 8, overflow: 'hidden', borderRadius: 4, bgcolor: 'action.hover' }}>
                                            <Box
                                                sx={{ height: '100%', bgcolor: 'primary.main', transition: 'all 0.3s' }}
                                                style={{
                                                    width: `${progressPercentage}%`,
                                                }}
                                            />
                                        </Box>
                                    </Box>
                                </Box>
                            </Box>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Rewards & Timeline</CardTitle>
                            <CardDescription>
                                Bonus structure and dates
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Box sx={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
                                <Box>
                                    <Typography component="label" sx={{ fontSize: '0.875rem', fontWeight: 500, color: 'text.secondary' }}>
                                        Bonus Amount
                                    </Typography>
                                    <Typography sx={{ fontSize: '1.5rem', fontWeight: 700 }}>
                                        {formatCurrency(target.bonus_amount)}
                                    </Typography>
                                </Box>
                                <Box>
                                    <Typography component="label" sx={{ fontSize: '0.875rem', fontWeight: 500, color: 'text.secondary' }}>
                                        Overachievement Rate
                                    </Typography>
                                    <Typography sx={{ fontSize: '1.125rem', fontWeight: 600 }}>
                                        {target.overachievement_rate}%
                                    </Typography>
                                </Box>
                                <Box>
                                    <Typography component="label" sx={{ fontSize: '0.875rem', fontWeight: 500, color: 'text.secondary' }}>
                                        Start Date
                                    </Typography>
                                    <Typography sx={{ fontSize: '0.875rem' }}>
                                        {formatDate(target.start_date)}
                                    </Typography>
                                </Box>
                                <Box>
                                    <Typography component="label" sx={{ fontSize: '0.875rem', fontWeight: 500, color: 'text.secondary' }}>
                                        End Date
                                    </Typography>
                                    <Typography sx={{ fontSize: '0.875rem' }}>
                                        {formatDate(target.end_date)}
                                    </Typography>
                                </Box>
                            </Box>
                        </CardContent>
                    </Card>

                    {target.notes && (
                        <Card sx={{ gridColumn: { md: 'span 2' } }}>
                            <CardHeader>
                                <CardTitle>Notes</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <Typography sx={{ fontSize: '0.875rem', whiteSpace: 'pre-wrap' }}>
                                    {target.notes}
                                </Typography>
                            </CardContent>
                        </Card>
                    )}
                </Box>
            </Box>
        </AppLayout>
    );
}

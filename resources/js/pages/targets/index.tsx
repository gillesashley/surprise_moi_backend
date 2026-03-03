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
import { Head, Link, router } from '@inertiajs/react';
import Box from '@mui/material/Box';
import Chip from '@mui/material/Chip';
import Typography from '@mui/material/Typography';
import { Eye, Pencil, Plus, Trash2 } from 'lucide-react';

interface Target {
    id: number;
    user: {
        id: number;
        name: string;
        email: string;
    };
    target_type: string;
    target_value: number;
    current_value: number;
    bonus_amount: number;
    period_type: string;
    start_date: string;
    end_date: string;
    status: string;
}

interface PaginatedTargets {
    data: Target[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

interface Props {
    targets: PaginatedTargets;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Targets',
        href: '/dashboard/targets',
    },
];

const formatTargetType = (type: string) => {
    return type
        .split('_')
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
};

const getStatusChipColor = (status: string): 'success' | 'info' | 'error' | 'default' => {
    switch (status) {
        case 'active':
            return 'success';
        case 'completed':
            return 'info';
        case 'expired':
            return 'error';
        case 'cancelled':
            return 'default';
        default:
            return 'default';
    }
};

export default function TargetsIndex({ targets }: Props) {
    const handleDelete = (targetId: number) => {
        if (
            confirm(
                'Are you sure you want to delete this target? This action cannot be undone.',
            )
        ) {
            router.delete(`/dashboard/targets/${targetId}`, {
                preserveScroll: true,
            });
        }
    };

    const handlePageChange = (page: number) => {
        router.get(
            '/dashboard/targets',
            { page },
            {
                preserveState: true,
                preserveScroll: true,
            },
        );
    };

    const progressPercentage = (current: number, target: number) => {
        return Math.min((current / target) * 100, 100).toFixed(1);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Targets Management" />
            <Box sx={{ display: 'flex', height: '100%', flex: 1, flexDirection: 'column', gap: 2, p: 2 }}>
                <Box sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                    <Box>
                        <Typography variant="h5" sx={{ fontWeight: 700 }}>
                            Targets Management
                        </Typography>
                        <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>
                            Assign and manage targets for field agents and
                            marketers
                        </Typography>
                    </Box>
                    <Button asChild>
                        <Link href="/dashboard/targets/create">
                            <Plus style={{ marginRight: 8, width: 16, height: 16 }} />
                            Create Target
                        </Link>
                    </Button>
                </Box>

                <Card>
                    <CardHeader>
                        <CardTitle>All Targets</CardTitle>
                        <CardDescription>
                            View and manage all assigned targets
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Box sx={{ overflowX: 'auto' }}>
                            <Box component="table" sx={{ width: '100%' }}>
                                <thead>
                                    <Box component="tr" sx={{ borderBottom: 1, borderColor: 'divider' }}>
                                        <Box component="th" sx={{ p: 1, textAlign: 'left', fontSize: '0.875rem', fontWeight: 500 }}>
                                            User
                                        </Box>
                                        <Box component="th" sx={{ p: 1, textAlign: 'left', fontSize: '0.875rem', fontWeight: 500 }}>
                                            Target Type
                                        </Box>
                                        <Box component="th" sx={{ p: 1, textAlign: 'left', fontSize: '0.875rem', fontWeight: 500 }}>
                                            Progress
                                        </Box>
                                        <Box component="th" sx={{ p: 1, textAlign: 'left', fontSize: '0.875rem', fontWeight: 500 }}>
                                            Bonus
                                        </Box>
                                        <Box component="th" sx={{ p: 1, textAlign: 'left', fontSize: '0.875rem', fontWeight: 500 }}>
                                            Period
                                        </Box>
                                        <Box component="th" sx={{ p: 1, textAlign: 'left', fontSize: '0.875rem', fontWeight: 500 }}>
                                            Status
                                        </Box>
                                        <Box component="th" sx={{ p: 1, textAlign: 'left', fontSize: '0.875rem', fontWeight: 500 }}>
                                            Actions
                                        </Box>
                                    </Box>
                                </thead>
                                <tbody>
                                    {targets.data.map((target) => (
                                        <Box
                                            component="tr"
                                            key={target.id}
                                            sx={{ borderBottom: 1, borderColor: 'divider', '&:hover': { bgcolor: 'action.hover' } }}
                                        >
                                            <Box component="td" sx={{ p: 1 }}>
                                                <Box>
                                                    <Typography sx={{ fontWeight: 500 }}>
                                                        {target.user.name}
                                                    </Typography>
                                                    <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>
                                                        {target.user.email}
                                                    </Typography>
                                                </Box>
                                            </Box>
                                            <Box component="td" sx={{ p: 1 }}>
                                                {formatTargetType(
                                                    target.target_type,
                                                )}
                                            </Box>
                                            <Box component="td" sx={{ p: 1 }}>
                                                <Box>
                                                    <Typography sx={{ fontSize: '0.875rem', fontWeight: 500 }}>
                                                        {target.current_value} /{' '}
                                                        {target.target_value}
                                                    </Typography>
                                                    <Box sx={{ mt: 0.5, height: 8, width: 96, overflow: 'hidden', borderRadius: 4, bgcolor: 'action.hover' }}>
                                                        <Box
                                                            sx={{ height: '100%', bgcolor: 'primary.main', transition: 'all 0.3s' }}
                                                            style={{
                                                                width: `${progressPercentage(
                                                                    target.current_value,
                                                                    target.target_value,
                                                                )}%`,
                                                            }}
                                                        />
                                                    </Box>
                                                </Box>
                                            </Box>
                                            <Box component="td" sx={{ p: 1 }}>
                                                GH₵{target.bonus_amount}
                                            </Box>
                                            <Box component="td" sx={{ p: 1, textTransform: 'capitalize' }}>
                                                {target.period_type}
                                            </Box>
                                            <Box component="td" sx={{ p: 1 }}>
                                                <Chip
                                                    label={target.status}
                                                    color={getStatusChipColor(target.status)}
                                                    size="small"
                                                    variant="outlined"
                                                    sx={{ textTransform: 'uppercase' }}
                                                />
                                            </Box>
                                            <Box component="td" sx={{ p: 1 }}>
                                                <Box sx={{ display: 'flex', gap: 1 }}>
                                                    <Link
                                                        href={`/dashboard/targets/${target.id}`}
                                                    >
                                                        <Eye style={{ width: 16, height: 16, color: 'gray' }} />
                                                    </Link>
                                                    <Link
                                                        href={`/dashboard/targets/${target.id}/edit`}
                                                    >
                                                        <Pencil style={{ width: 16, height: 16, color: 'gray' }} />
                                                    </Link>
                                                    <Box
                                                        component="button"
                                                        type="button"
                                                        onClick={() =>
                                                            handleDelete(
                                                                target.id,
                                                            )
                                                        }
                                                        sx={{ color: 'text.secondary', '&:hover': { color: 'error.main' }, background: 'none', border: 'none', cursor: 'pointer', p: 0 }}
                                                    >
                                                        <Trash2 style={{ width: 16, height: 16 }} />
                                                    </Box>
                                                </Box>
                                            </Box>
                                        </Box>
                                    ))}
                                </tbody>
                            </Box>
                        </Box>

                        {targets.data.length === 0 && (
                            <Box sx={{ py: 4, textAlign: 'center', color: 'text.secondary' }}>
                                No targets found. Create one to get started.
                            </Box>
                        )}

                        {targets.last_page > 1 && (
                            <Box sx={{ mt: 2, display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                                <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>
                                    Showing {targets.data.length} of{' '}
                                    {targets.total} targets
                                </Typography>
                                <Box sx={{ display: 'flex', gap: 1 }}>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            handlePageChange(
                                                targets.current_page - 1,
                                            )
                                        }
                                        disabled={targets.current_page === 1}
                                    >
                                        Previous
                                    </Button>
                                    <Box sx={{ display: 'flex', alignItems: 'center', px: 1.5, fontSize: '0.875rem' }}>
                                        Page {targets.current_page} of{' '}
                                        {targets.last_page}
                                    </Box>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            handlePageChange(
                                                targets.current_page + 1,
                                            )
                                        }
                                        disabled={
                                            targets.current_page ===
                                            targets.last_page
                                        }
                                    >
                                        Next
                                    </Button>
                                </Box>
                            </Box>
                        )}
                    </CardContent>
                </Card>
            </Box>
        </AppLayout>
    );
}

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
import { Calendar, DollarSign, Gift } from 'lucide-react';

interface Earning {
    id: number;
    amount: number;
    currency: string;
    earning_type: string;
    status: string;
    earned_at: string;
}

interface Props {
    earnings: {
        data: Earning[];
        current_page: number;
        last_page: number;
        total: number;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'My Earnings',
        href: '/marketer/earnings',
    },
];

const getStatusBadge = (status: string) => {
    const statusConfig = {
        pending: { label: 'Pending', variant: 'secondary' as const },
        approved: { label: 'Approved', variant: 'default' as const },
        paid: { label: 'Paid', variant: 'default' as const },
        rejected: { label: 'Rejected', variant: 'destructive' as const },
    };

    const config = statusConfig[status as keyof typeof statusConfig] || {
        label: status,
        variant: 'outline' as const,
    };

    return <Badge variant={config.variant}>{config.label}</Badge>;
};

const formatCurrency = (amount: number, currency: string) => {
    return new Intl.NumberFormat('en-GH', {
        style: 'currency',
        currency: 'GHS',
    }).format(amount);
};

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-GH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

const formatEarningType = (type: string) => {
    return type
        .split('_')
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
};

const isSignOnBonus = (type: string) => {
    return (
        type.toLowerCase().includes('sign_on') ||
        type.toLowerCase().includes('bonus')
    );
};

export default function MarketerEarnings({ earnings }: Props) {
    const totalEarnings = earnings.data.reduce((sum, e) => sum + e.amount, 0);
    const signOnBonuses = earnings.data.filter((e) =>
        isSignOnBonus(e.earning_type),
    );
    const commissions = earnings.data.filter(
        (e) => !isSignOnBonus(e.earning_type),
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="My Earnings" />

            <Box sx={{ display: 'flex', height: '100%', flex: 1, flexDirection: 'column', gap: 3, overflowX: 'auto', p: 3 }}>
                <Box>
                    <Typography variant="h4" fontWeight={700}>My Earnings</Typography>
                    <Typography color="text.secondary" sx={{ mt: 0.5 }}>
                        Track sign-on bonuses and commissions from completed
                        targets
                    </Typography>
                </Box>

                <Box sx={{ display: 'grid', gap: 2, gridTemplateColumns: { md: 'repeat(4, 1fr)' } }}>
                    <Card>
                        <CardHeader sx={{ pb: 1.5 }}>
                            <CardTitle sx={{ fontSize: '0.875rem', fontWeight: 500 }}>
                                Total Earnings
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                                <DollarSign style={{ width: 20, height: 20, color: 'gray' }} />
                                <Typography variant="h5" fontWeight={700}>
                                    GHS {totalEarnings.toFixed(2)}
                                </Typography>
                            </Box>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader sx={{ pb: 1.5 }}>
                            <CardTitle sx={{ fontSize: '0.875rem', fontWeight: 500 }}>
                                Sign-On Bonuses
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                                <Gift style={{ width: 20, height: 20, color: '#9333ea' }} />
                                <Typography variant="h5" fontWeight={700} sx={{ color: '#9333ea' }}>
                                    {signOnBonuses.length}
                                </Typography>
                            </Box>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader sx={{ pb: 1.5 }}>
                            <CardTitle sx={{ fontSize: '0.875rem', fontWeight: 500 }}>
                                Approved
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Typography variant="h5" fontWeight={700} color="success.main">
                                {
                                    earnings.data.filter(
                                        (e) => e.status === 'approved',
                                    ).length
                                }
                            </Typography>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader sx={{ pb: 1.5 }}>
                            <CardTitle sx={{ fontSize: '0.875rem', fontWeight: 500 }}>
                                Pending
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Typography variant="h5" fontWeight={700} color="warning.main">
                                {
                                    earnings.data.filter(
                                        (e) => e.status === 'pending',
                                    ).length
                                }
                            </Typography>
                        </CardContent>
                    </Card>
                </Box>

                <Card>
                    <CardHeader>
                        <CardTitle>Earnings History</CardTitle>
                        <CardDescription>
                            Total {earnings.total} earning
                            {earnings.total !== 1 ? 's' : ''}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {earnings.data.length === 0 ? (
                            <Box sx={{ py: 6, textAlign: 'center' }}>
                                <Typography color="text.secondary">
                                    No earnings yet. Complete your quarterly
                                    targets to earn commissions!
                                </Typography>
                            </Box>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Type</TableHead>
                                        <TableHead>Amount</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Earned On</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {earnings.data.map((earning) => (
                                        <TableRow key={earning.id}>
                                            <TableCell sx={{ fontWeight: 500 }}>
                                                <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                                                    {isSignOnBonus(
                                                        earning.earning_type,
                                                    ) && (
                                                        <Gift style={{ width: 16, height: 16, color: '#9333ea' }} />
                                                    )}
                                                    {formatEarningType(
                                                        earning.earning_type,
                                                    )}
                                                </Box>
                                            </TableCell>
                                            <TableCell sx={{ fontWeight: 600 }}>
                                                {formatCurrency(
                                                    earning.amount,
                                                    earning.currency,
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                {getStatusBadge(earning.status)}
                                            </TableCell>
                                            <TableCell>
                                                <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                                                    <Calendar style={{ width: 16, height: 16, color: 'gray' }} />
                                                    {formatDate(
                                                        earning.earned_at,
                                                    )}
                                                </Box>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>
            </Box>
        </AppLayout>
    );
}

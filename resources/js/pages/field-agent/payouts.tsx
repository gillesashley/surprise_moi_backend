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
import Typography from '@mui/material/Typography';
import { Head } from '@inertiajs/react';
import { Calendar, DollarSign } from 'lucide-react';

interface PayoutRequest {
    id: number;
    amount: number;
    currency: string;
    status: string;
    payout_method: string;
    created_at: string;
    approved_at?: string;
    paid_at?: string;
}

interface Props {
    payoutRequests: {
        data: PayoutRequest[];
        current_page: number;
        last_page: number;
        total: number;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Payouts',
        href: '/field-agent/payouts',
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
        currency: currency,
    }).format(amount);
};

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-GH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

const formatPayoutMethod = (method: string) => {
    return method
        .split('_')
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
};

export default function FieldAgentPayouts({ payoutRequests }: Props) {
    const totalRequested = payoutRequests.data.reduce(
        (sum, p) => sum + p.amount,
        0,
    );
    const totalPaid = payoutRequests.data
        .filter((p) => p.status === 'paid')
        .reduce((sum, p) => sum + p.amount, 0);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Payouts" />

            <Box sx={{ display: 'flex', flex: 1, flexDirection: 'column', gap: 3, overflowX: 'auto', p: 3 }}>
                <Box>
                    <Typography variant="h4" fontWeight={700}>
                        Payout Requests
                    </Typography>
                    <Typography variant="body2" color="text.secondary" sx={{ mt: 0.5 }}>
                        Manage your withdrawal requests
                    </Typography>
                </Box>

                <Box sx={{ display: 'grid', gap: 2, gridTemplateColumns: { md: 'repeat(3, 1fr)' } }}>
                    <Card>
                        <CardHeader style={{ paddingBottom: 12 }}>
                            <CardTitle style={{ fontSize: '0.875rem', fontWeight: 500 }}>
                                Total Requested
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                                <DollarSign style={{ width: 20, height: 20, color: 'gray' }} />
                                <Typography variant="h5" fontWeight={700}>
                                    GHS {totalRequested.toFixed(2)}
                                </Typography>
                            </Box>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader style={{ paddingBottom: 12 }}>
                            <CardTitle style={{ fontSize: '0.875rem', fontWeight: 500 }}>
                                Total Paid
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                                <DollarSign style={{ width: 20, height: 20, color: '#16a34a' }} />
                                <Typography variant="h5" fontWeight={700} sx={{ color: '#16a34a' }}>
                                    GHS {totalPaid.toFixed(2)}
                                </Typography>
                            </Box>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader style={{ paddingBottom: 12 }}>
                            <CardTitle style={{ fontSize: '0.875rem', fontWeight: 500 }}>
                                Pending Requests
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Typography variant="h5" fontWeight={700} sx={{ color: '#ea580c' }}>
                                {
                                    payoutRequests.data.filter(
                                        (p) => p.status === 'pending',
                                    ).length
                                }
                            </Typography>
                        </CardContent>
                    </Card>
                </Box>

                <Card>
                    <CardHeader>
                        <CardTitle>Payout History</CardTitle>
                        <CardDescription>
                            Total {payoutRequests.total} request
                            {payoutRequests.total !== 1 ? 's' : ''}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {payoutRequests.data.length === 0 ? (
                            <Box sx={{ py: 6, textAlign: 'center' }}>
                                <Typography color="text.secondary">
                                    No payout requests yet. Submit a request
                                    when you have approved earnings!
                                </Typography>
                            </Box>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Amount</TableHead>
                                        <TableHead>Method</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Requested</TableHead>
                                        <TableHead>Paid</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {payoutRequests.data.map((payout) => (
                                        <TableRow key={payout.id}>
                                            <TableCell style={{ fontWeight: 600 }}>
                                                {formatCurrency(
                                                    payout.amount,
                                                    payout.currency,
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                {formatPayoutMethod(
                                                    payout.payout_method,
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                {getStatusBadge(payout.status)}
                                            </TableCell>
                                            <TableCell>
                                                <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                                                    <Calendar style={{ width: 16, height: 16, color: 'gray' }} />
                                                    {formatDate(
                                                        payout.created_at,
                                                    )}
                                                </Box>
                                            </TableCell>
                                            <TableCell>
                                                {payout.paid_at
                                                    ? formatDate(payout.paid_at)
                                                    : '-'}
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

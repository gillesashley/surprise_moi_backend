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
        href: '/marketer/payouts',
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

const formatPayoutMethod = (method: string) => {
    return method
        .split('_')
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
};

export default function MarketerPayouts({ payoutRequests }: Props) {
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

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-6">
                <div>
                    <h1 className="text-3xl font-bold">Payout Requests</h1>
                    <p className="mt-1 text-muted-foreground">
                        Manage your withdrawal requests
                    </p>
                </div>

                <div className="grid gap-4 md:grid-cols-3">
                    <Card>
                        <CardHeader className="pb-3">
                            <CardTitle className="text-sm font-medium">
                                Total Requested
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="flex items-center gap-2">
                                <DollarSign className="h-5 w-5 text-muted-foreground" />
                                <span className="text-2xl font-bold">
                                    GHS {totalRequested.toFixed(2)}
                                </span>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-3">
                            <CardTitle className="text-sm font-medium">
                                Total Paid
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="flex items-center gap-2">
                                <DollarSign className="h-5 w-5 text-green-600" />
                                <span className="text-2xl font-bold text-green-600">
                                    GHS {totalPaid.toFixed(2)}
                                </span>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-3">
                            <CardTitle className="text-sm font-medium">
                                Pending Requests
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <span className="text-2xl font-bold text-orange-600">
                                {
                                    payoutRequests.data.filter(
                                        (p) => p.status === 'pending',
                                    ).length
                                }
                            </span>
                        </CardContent>
                    </Card>
                </div>

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
                            <div className="py-12 text-center">
                                <p className="text-muted-foreground">
                                    No payout requests yet. Submit a request
                                    when you have approved earnings!
                                </p>
                            </div>
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
                                            <TableCell className="font-semibold">
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
                                                <div className="flex items-center gap-2">
                                                    <Calendar className="h-4 w-4 text-muted-foreground" />
                                                    {formatDate(
                                                        payout.created_at,
                                                    )}
                                                </div>
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
            </div>
        </AppLayout>
    );
}

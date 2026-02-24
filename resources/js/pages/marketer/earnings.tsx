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

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-6">
                <div>
                    <h1 className="text-3xl font-bold">My Earnings</h1>
                    <p className="mt-1 text-muted-foreground">
                        Track sign-on bonuses and commissions from completed
                        targets
                    </p>
                </div>

                <div className="grid gap-4 md:grid-cols-4">
                    <Card>
                        <CardHeader className="pb-3">
                            <CardTitle className="text-sm font-medium">
                                Total Earnings
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="flex items-center gap-2">
                                <DollarSign className="h-5 w-5 text-muted-foreground" />
                                <span className="text-2xl font-bold">
                                    GHS {totalEarnings.toFixed(2)}
                                </span>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-3">
                            <CardTitle className="text-sm font-medium">
                                Sign-On Bonuses
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="flex items-center gap-2">
                                <Gift className="h-5 w-5 text-purple-600" />
                                <span className="text-2xl font-bold text-purple-600">
                                    {signOnBonuses.length}
                                </span>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-3">
                            <CardTitle className="text-sm font-medium">
                                Approved
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <span className="text-2xl font-bold text-green-600">
                                {
                                    earnings.data.filter(
                                        (e) => e.status === 'approved',
                                    ).length
                                }
                            </span>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-3">
                            <CardTitle className="text-sm font-medium">
                                Pending
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <span className="text-2xl font-bold text-orange-600">
                                {
                                    earnings.data.filter(
                                        (e) => e.status === 'pending',
                                    ).length
                                }
                            </span>
                        </CardContent>
                    </Card>
                </div>

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
                            <div className="py-12 text-center">
                                <p className="text-muted-foreground">
                                    No earnings yet. Complete your quarterly
                                    targets to earn commissions!
                                </p>
                            </div>
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
                                            <TableCell className="font-medium">
                                                <div className="flex items-center gap-2">
                                                    {isSignOnBonus(
                                                        earning.earning_type,
                                                    ) && (
                                                        <Gift className="h-4 w-4 text-purple-600" />
                                                    )}
                                                    {formatEarningType(
                                                        earning.earning_type,
                                                    )}
                                                </div>
                                            </TableCell>
                                            <TableCell className="font-semibold">
                                                {formatCurrency(
                                                    earning.amount,
                                                    earning.currency,
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                {getStatusBadge(earning.status)}
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex items-center gap-2">
                                                    <Calendar className="h-4 w-4 text-muted-foreground" />
                                                    {formatDate(
                                                        earning.earned_at,
                                                    )}
                                                </div>
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

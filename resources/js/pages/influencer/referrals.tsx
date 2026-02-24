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
import { Calendar } from 'lucide-react';

interface Referral {
    id: number;
    status: string;
    vendor: {
        name: string;
        email: string;
    };
    referral_code: {
        code: string;
    };
    created_at: string;
}

interface Props {
    referrals: {
        data: Referral[];
        current_page: number;
        last_page: number;
        total: number;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'My Referrals',
        href: '/influencer/referrals',
    },
];

const getStatusBadge = (status: string) => {
    const statusConfig = {
        active: { label: 'Active', variant: 'default' as const },
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

export default function InfluencerReferrals({ referrals }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="My Referrals" />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-6">
                <div>
                    <h1 className="text-3xl font-bold">My Referrals</h1>
                    <p className="mt-1 text-muted-foreground">
                        Track all vendors you've referred to the platform
                    </p>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Referral History</CardTitle>
                        <CardDescription>
                            Total {referrals.total} referral
                            {referrals.total !== 1 ? 's' : ''}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {referrals.data.length === 0 ? (
                            <div className="py-12 text-center">
                                <p className="text-muted-foreground">
                                    No referrals yet. Share your referral code
                                    to get started!
                                </p>
                            </div>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Vendor</TableHead>
                                        <TableHead>Email</TableHead>
                                        <TableHead>Code Used</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Referred On</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {referrals.data.map((referral) => (
                                        <TableRow key={referral.id}>
                                            <TableCell className="font-medium">
                                                {referral.vendor.name}
                                            </TableCell>
                                            <TableCell>
                                                {referral.vendor.email}
                                            </TableCell>
                                            <TableCell>
                                                <code className="rounded bg-muted px-2 py-1 text-sm">
                                                    {
                                                        referral.referral_code
                                                            .code
                                                    }
                                                </code>
                                            </TableCell>
                                            <TableCell>
                                                {getStatusBadge(
                                                    referral.status,
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex items-center gap-2">
                                                    <Calendar className="h-4 w-4 text-muted-foreground" />
                                                    {formatDate(
                                                        referral.created_at,
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

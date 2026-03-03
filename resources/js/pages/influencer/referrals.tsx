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

            <Box sx={{ display: 'flex', height: '100%', flex: 1, flexDirection: 'column', gap: 3, overflowX: 'auto', p: 3 }}>
                <Box>
                    <Typography variant="h4" fontWeight={700}>My Referrals</Typography>
                    <Typography color="text.secondary" sx={{ mt: 0.5 }}>
                        Track all vendors you've referred to the platform
                    </Typography>
                </Box>

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
                            <Box sx={{ py: 6, textAlign: 'center' }}>
                                <Typography color="text.secondary">
                                    No referrals yet. Share your referral code
                                    to get started!
                                </Typography>
                            </Box>
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
                                            <TableCell sx={{ fontWeight: 500 }}>
                                                {referral.vendor.name}
                                            </TableCell>
                                            <TableCell>
                                                {referral.vendor.email}
                                            </TableCell>
                                            <TableCell>
                                                <Box
                                                    component="code"
                                                    sx={{
                                                        borderRadius: 1,
                                                        bgcolor: 'action.hover',
                                                        px: 1,
                                                        py: 0.5,
                                                        fontSize: '0.875rem',
                                                    }}
                                                >
                                                    {
                                                        referral.referral_code
                                                            .code
                                                    }
                                                </Box>
                                            </TableCell>
                                            <TableCell>
                                                {getStatusBadge(
                                                    referral.status,
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                                                    <Calendar style={{ width: 16, height: 16, color: 'gray' }} />
                                                    {formatDate(
                                                        referral.created_at,
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

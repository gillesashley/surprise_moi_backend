import { Button } from '@/components/ui/button';
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
import { Head, Link } from '@inertiajs/react';
import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';
import { ArrowLeft, CheckCircle, Copy, XCircle } from 'lucide-react';
import { useState } from 'react';

interface ReferralCode {
    id: number;
    code: string;
    influencer: {
        id: number;
        name: string;
        email: string;
    };
    description?: string;
    registration_bonus: number;
    commission_rate: number;
    commission_duration_months: number;
    discount_percentage: number;
    is_active: boolean;
    usage_count: number;
    max_usages?: number;
    expires_at?: string;
    created_at: string;
    referrals?: Array<{
        id: number;
        vendor: {
            id: number;
            name: string;
            email: string;
        };
        status: string;
        created_at: string;
    }>;
}

interface Props {
    code: ReferralCode;
}

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

export default function ReferralCodeShow({ code }: Props) {
    const [copied, setCopied] = useState(false);

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Referral Codes',
            href: '/dashboard/referral-codes',
        },
        {
            title: code.code,
            href: `/dashboard/referral-codes/${code.id}`,
        },
    ];

    const copyToClipboard = async () => {
        await navigator.clipboard.writeText(code.code);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Referral Code: ${code.code}`} />

            <Box sx={{ display: 'flex', height: '100%', flex: 1, flexDirection: 'column', gap: 2, p: 2 }}>
                <Box sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                    <Typography variant="h4" sx={{ fontWeight: 700 }}>
                        Referral Code Details
                    </Typography>
                    <Box sx={{ display: 'flex', gap: 1.5 }}>
                        <Button variant="outline" asChild>
                            <Link href="/dashboard/referral-codes">
                                <ArrowLeft style={{ marginRight: 8, width: 16, height: 16 }} />
                                Back to Referral Codes
                            </Link>
                        </Button>
                        <Button asChild>
                            <Link
                                href={`/dashboard/referral-codes/${code.id}/edit`}
                            >
                                Edit Code
                            </Link>
                        </Button>
                    </Box>
                </Box>

                <Box sx={{ display: 'grid', gap: 3, gridTemplateColumns: { md: 'repeat(2, 1fr)' } }}>
                    <Card>
                        <CardHeader>
                            <CardTitle>Code Information</CardTitle>
                            <CardDescription>
                                Basic details about this referral code
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Box sx={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
                                <Box>
                                    <Typography component="label" sx={{ fontSize: '0.875rem', fontWeight: 500, color: 'text.secondary' }}>
                                        Referral Code
                                    </Typography>
                                    <Box sx={{ mt: 0.5, display: 'flex', alignItems: 'center', gap: 1 }}>
                                        <Box
                                            component="code"
                                            sx={{ borderRadius: 1, bgcolor: 'action.hover', px: 1.5, py: 1, fontFamily: 'monospace', fontSize: '1.25rem', fontWeight: 700 }}
                                        >
                                            {code.code}
                                        </Box>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            onClick={copyToClipboard}
                                        >
                                            <Copy style={{ width: 16, height: 16 }} />
                                        </Button>
                                        {copied && (
                                            <Box component="span" sx={{ fontSize: '0.875rem', fontWeight: 500, color: 'success.main' }}>
                                                Copied!
                                            </Box>
                                        )}
                                    </Box>
                                </Box>
                                <Box>
                                    <Typography component="label" sx={{ fontSize: '0.875rem', fontWeight: 500, color: 'text.secondary' }}>
                                        Status
                                    </Typography>
                                    <Box sx={{ mt: 0.5, display: 'flex', alignItems: 'center', gap: 1 }}>
                                        {code.is_active ? (
                                            <>
                                                <CheckCircle style={{ width: 20, height: 20, color: '#16a34a' }} />
                                                <Box component="span" sx={{ fontWeight: 600, color: 'success.main' }}>
                                                    Active
                                                </Box>
                                            </>
                                        ) : (
                                            <>
                                                <XCircle style={{ width: 20, height: 20, color: '#6b7280' }} />
                                                <Box component="span" sx={{ fontWeight: 600, color: 'text.secondary' }}>
                                                    Inactive
                                                </Box>
                                            </>
                                        )}
                                    </Box>
                                </Box>
                                {code.description && (
                                    <Box>
                                        <Typography component="label" sx={{ fontSize: '0.875rem', fontWeight: 500, color: 'text.secondary' }}>
                                            Description
                                        </Typography>
                                        <Typography sx={{ mt: 0.5, fontSize: '0.875rem' }}>
                                            {code.description}
                                        </Typography>
                                    </Box>
                                )}
                                <Box>
                                    <Typography component="label" sx={{ fontSize: '0.875rem', fontWeight: 500, color: 'text.secondary' }}>
                                        Created
                                    </Typography>
                                    <Typography sx={{ mt: 0.5, fontSize: '0.875rem' }}>
                                        {formatDate(code.created_at)}
                                    </Typography>
                                </Box>
                            </Box>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Influencer</CardTitle>
                            <CardDescription>
                                Who this code is assigned to
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Box sx={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
                                <Box>
                                    <Typography component="label" sx={{ fontSize: '0.875rem', fontWeight: 500, color: 'text.secondary' }}>
                                        Name
                                    </Typography>
                                    <Typography sx={{ mt: 0.5, fontSize: '1.125rem', fontWeight: 600 }}>
                                        {code.influencer.name}
                                    </Typography>
                                </Box>
                                <Box>
                                    <Typography component="label" sx={{ fontSize: '0.875rem', fontWeight: 500, color: 'text.secondary' }}>
                                        Email
                                    </Typography>
                                    <Typography sx={{ mt: 0.5, fontSize: '0.875rem' }}>
                                        {code.influencer.email}
                                    </Typography>
                                </Box>
                            </Box>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Rewards Structure</CardTitle>
                            <CardDescription>
                                Commission and bonus details
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Box sx={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
                                <Box>
                                    <Typography component="label" sx={{ fontSize: '0.875rem', fontWeight: 500, color: 'text.secondary' }}>
                                        Registration Bonus
                                    </Typography>
                                    <Typography sx={{ mt: 0.5, fontSize: '1.5rem', fontWeight: 700 }}>
                                        {formatCurrency(code.registration_bonus)}
                                    </Typography>
                                </Box>
                                <Box>
                                    <Typography component="label" sx={{ fontSize: '0.875rem', fontWeight: 500, color: 'text.secondary' }}>
                                        Vendor Discount
                                    </Typography>
                                    <Typography sx={{ mt: 0.5, fontSize: '1.5rem', fontWeight: 700, color: 'success.main' }}>
                                        {code.discount_percentage}%
                                    </Typography>
                                </Box>
                                <Box>
                                    <Typography component="label" sx={{ fontSize: '0.875rem', fontWeight: 500, color: 'text.secondary' }}>
                                        Commission Rate
                                    </Typography>
                                    <Typography sx={{ mt: 0.5, fontSize: '1.5rem', fontWeight: 700 }}>
                                        {code.commission_rate}%
                                    </Typography>
                                </Box>
                                <Box>
                                    <Typography component="label" sx={{ fontSize: '0.875rem', fontWeight: 500, color: 'text.secondary' }}>
                                        Commission Duration
                                    </Typography>
                                    <Typography sx={{ mt: 0.5, fontSize: '1.125rem', fontWeight: 600 }}>
                                        {code.commission_duration_months} months
                                    </Typography>
                                </Box>
                            </Box>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Usage Information</CardTitle>
                            <CardDescription>
                                Limits and expiration
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Box sx={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
                                <Box>
                                    <Typography component="label" sx={{ fontSize: '0.875rem', fontWeight: 500, color: 'text.secondary' }}>
                                        Usage Count
                                    </Typography>
                                    <Typography sx={{ mt: 0.5, fontSize: '1.5rem', fontWeight: 700 }}>
                                        {code.usage_count}
                                        {code.max_usages && ` / ${code.max_usages}`}
                                    </Typography>
                                </Box>
                                <Box>
                                    <Typography component="label" sx={{ fontSize: '0.875rem', fontWeight: 500, color: 'text.secondary' }}>
                                        Maximum Usages
                                    </Typography>
                                    <Typography sx={{ mt: 0.5, fontSize: '1.125rem', fontWeight: 600 }}>
                                        {code.max_usages || 'Unlimited'}
                                    </Typography>
                                </Box>
                                <Box>
                                    <Typography component="label" sx={{ fontSize: '0.875rem', fontWeight: 500, color: 'text.secondary' }}>
                                        Expiration Date
                                    </Typography>
                                    <Typography sx={{ mt: 0.5, fontSize: '0.875rem' }}>
                                        {code.expires_at
                                            ? formatDate(code.expires_at)
                                            : 'No expiration'}
                                    </Typography>
                                </Box>
                            </Box>
                        </CardContent>
                    </Card>
                </Box>

                {code.referrals && code.referrals.length > 0 && (
                    <Card sx={{ mt: 3 }}>
                        <CardHeader>
                            <CardTitle>Referrals</CardTitle>
                            <CardDescription>
                                Vendors who used this referral code
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Vendor</TableHead>
                                        <TableHead>Email</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Referred On</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {code.referrals.map((referral) => (
                                        <TableRow key={referral.id}>
                                            <TableCell>
                                                <Typography sx={{ fontWeight: 500 }}>
                                                    {referral.vendor.name}
                                                </Typography>
                                            </TableCell>
                                            <TableCell>
                                                {referral.vendor.email}
                                            </TableCell>
                                            <TableCell>
                                                <Box component="span" sx={{ textTransform: 'capitalize' }}>
                                                    {referral.status}
                                                </Box>
                                            </TableCell>
                                            <TableCell>
                                                {formatDate(
                                                    referral.created_at,
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>
                )}
            </Box>
        </AppLayout>
    );
}

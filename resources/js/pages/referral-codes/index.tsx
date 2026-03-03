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
import { Copy, Eye, Pencil, Plus, Power, PowerOff, Trash2 } from 'lucide-react';

interface ReferralCode {
    id: number;
    code: string;
    influencer: {
        id: number;
        name: string;
        email: string;
    };
    is_active: boolean;
    usage_count: number;
    max_usages: number | null;
    commission_rate: number;
    registration_bonus: number;
    discount_percentage: number;
    expires_at: string | null;
}

interface PaginatedCodes {
    data: ReferralCode[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

interface Props {
    codes: PaginatedCodes;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Referral Codes',
        href: '/dashboard/referral-codes',
    },
];

export default function ReferralCodesIndex({ codes }: Props) {
    const handleDelete = (codeId: number, code: string) => {
        if (
            confirm(
                `Are you sure you want to delete referral code "${code}"? This action cannot be undone.`,
            )
        ) {
            router.delete(`/dashboard/referral-codes/${codeId}`, {
                preserveScroll: true,
            });
        }
    };

    const handleToggle = (codeId: number) => {
        router.post(
            `/dashboard/referral-codes/${codeId}/toggle`,
            {},
            {
                preserveScroll: true,
            },
        );
    };

    const copyToClipboard = (code: string) => {
        navigator.clipboard.writeText(code);
        alert(`Code "${code}" copied to clipboard!`);
    };

    const handlePageChange = (page: number) => {
        router.get(
            '/dashboard/referral-codes',
            { page },
            {
                preserveState: true,
                preserveScroll: true,
            },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Referral Codes" />
            <Box sx={{ display: 'flex', height: '100%', flex: 1, flexDirection: 'column', gap: 2, p: 2 }}>
                <Box sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                    <Box>
                        <Typography variant="h5" sx={{ fontWeight: 700 }}>
                            Referral Codes Management
                        </Typography>
                        <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>
                            Manage referral codes for influencers
                        </Typography>
                    </Box>
                    <Button asChild>
                        <Link href="/dashboard/referral-codes/create">
                            <Plus style={{ marginRight: 8, width: 16, height: 16 }} />
                            Create Code
                        </Link>
                    </Button>
                </Box>

                <Card>
                    <CardHeader>
                        <CardTitle>All Referral Codes</CardTitle>
                        <CardDescription>
                            View and manage all referral codes
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Box sx={{ overflowX: 'auto' }}>
                            <Box component="table" sx={{ width: '100%' }}>
                                <thead>
                                    <Box component="tr" sx={{ borderBottom: 1, borderColor: 'divider' }}>
                                        <Box component="th" sx={{ p: 1, textAlign: 'left', fontSize: '0.875rem', fontWeight: 500 }}>
                                            Code
                                        </Box>
                                        <Box component="th" sx={{ p: 1, textAlign: 'left', fontSize: '0.875rem', fontWeight: 500 }}>
                                            Influencer
                                        </Box>
                                        <Box component="th" sx={{ p: 1, textAlign: 'left', fontSize: '0.875rem', fontWeight: 500 }}>
                                            Discount
                                        </Box>
                                        <Box component="th" sx={{ p: 1, textAlign: 'left', fontSize: '0.875rem', fontWeight: 500 }}>
                                            Usage
                                        </Box>
                                        <Box component="th" sx={{ p: 1, textAlign: 'left', fontSize: '0.875rem', fontWeight: 500 }}>
                                            Commission
                                        </Box>
                                        <Box component="th" sx={{ p: 1, textAlign: 'left', fontSize: '0.875rem', fontWeight: 500 }}>
                                            Bonus
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
                                    {codes.data.map((code) => (
                                        <Box
                                            component="tr"
                                            key={code.id}
                                            sx={{ borderBottom: 1, borderColor: 'divider', '&:hover': { bgcolor: 'action.hover' } }}
                                        >
                                            <Box component="td" sx={{ p: 1 }}>
                                                <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                                                    <Box
                                                        component="code"
                                                        sx={{ borderRadius: 1, bgcolor: 'action.hover', px: 1, py: 0.5, fontFamily: 'monospace', fontSize: '0.875rem', fontWeight: 600 }}
                                                    >
                                                        {code.code}
                                                    </Box>
                                                    <Box
                                                        component="button"
                                                        type="button"
                                                        onClick={() =>
                                                            copyToClipboard(
                                                                code.code,
                                                            )
                                                        }
                                                        sx={{ color: 'text.secondary', '&:hover': { color: 'text.primary' }, background: 'none', border: 'none', cursor: 'pointer', p: 0 }}
                                                    >
                                                        <Copy style={{ width: 16, height: 16 }} />
                                                    </Box>
                                                </Box>
                                            </Box>
                                            <Box component="td" sx={{ p: 1 }}>
                                                <Box>
                                                    <Typography sx={{ fontWeight: 500 }}>
                                                        {code.influencer.name}
                                                    </Typography>
                                                    <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>
                                                        {code.influencer.email}
                                                    </Typography>
                                                </Box>
                                            </Box>
                                            <Box component="td" sx={{ p: 1 }}>
                                                <Box component="span" sx={{ fontWeight: 600, color: 'success.main' }}>
                                                    {code.discount_percentage}%
                                                </Box>
                                            </Box>
                                            <Box component="td" sx={{ p: 1 }}>
                                                {code.usage_count} /{' '}
                                                {code.max_usages || '\u221E'}
                                            </Box>
                                            <Box component="td" sx={{ p: 1 }}>
                                                {code.commission_rate}%
                                            </Box>
                                            <Box component="td" sx={{ p: 1 }}>
                                                GH₵{code.registration_bonus}
                                            </Box>
                                            <Box component="td" sx={{ p: 1 }}>
                                                {code.is_active ? (
                                                    <Chip
                                                        icon={<Power style={{ width: 12, height: 12 }} />}
                                                        label="ACTIVE"
                                                        color="success"
                                                        size="small"
                                                        variant="outlined"
                                                    />
                                                ) : (
                                                    <Chip
                                                        icon={<PowerOff style={{ width: 12, height: 12 }} />}
                                                        label="INACTIVE"
                                                        color="default"
                                                        size="small"
                                                        variant="outlined"
                                                    />
                                                )}
                                            </Box>
                                            <Box component="td" sx={{ p: 1 }}>
                                                <Box sx={{ display: 'flex', gap: 1 }}>
                                                    <Link
                                                        href={`/dashboard/referral-codes/${code.id}`}
                                                    >
                                                        <Eye style={{ width: 16, height: 16, color: 'gray' }} />
                                                    </Link>
                                                    <Link
                                                        href={`/dashboard/referral-codes/${code.id}/edit`}
                                                    >
                                                        <Pencil style={{ width: 16, height: 16, color: 'gray' }} />
                                                    </Link>
                                                    <Box
                                                        component="button"
                                                        type="button"
                                                        onClick={() =>
                                                            handleToggle(
                                                                code.id,
                                                            )
                                                        }
                                                        title={
                                                            code.is_active
                                                                ? 'Deactivate'
                                                                : 'Activate'
                                                        }
                                                        sx={{ color: 'text.secondary', '&:hover': { color: 'text.primary' }, background: 'none', border: 'none', cursor: 'pointer', p: 0 }}
                                                    >
                                                        {code.is_active ? (
                                                            <PowerOff style={{ width: 16, height: 16 }} />
                                                        ) : (
                                                            <Power style={{ width: 16, height: 16 }} />
                                                        )}
                                                    </Box>
                                                    <Box
                                                        component="button"
                                                        type="button"
                                                        onClick={() =>
                                                            handleDelete(
                                                                code.id,
                                                                code.code,
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

                        {codes.data.length === 0 && (
                            <Box sx={{ py: 4, textAlign: 'center', color: 'text.secondary' }}>
                                No referral codes found. Create one to get
                                started.
                            </Box>
                        )}

                        {codes.last_page > 1 && (
                            <Box sx={{ mt: 2, display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                                <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>
                                    Showing {codes.data.length} of {codes.total}{' '}
                                    codes
                                </Typography>
                                <Box sx={{ display: 'flex', gap: 1 }}>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            handlePageChange(
                                                codes.current_page - 1,
                                            )
                                        }
                                        disabled={codes.current_page === 1}
                                    >
                                        Previous
                                    </Button>
                                    <Box sx={{ display: 'flex', alignItems: 'center', px: 1.5, fontSize: '0.875rem' }}>
                                        Page {codes.current_page} of{' '}
                                        {codes.last_page}
                                    </Box>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            handlePageChange(
                                                codes.current_page + 1,
                                            )
                                        }
                                        disabled={
                                            codes.current_page ===
                                            codes.last_page
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

import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import {
    index as couponsIndex,
    create as couponCreate,
    destroy as couponDestroy,
    edit as couponEdit,
} from '@/routes/dashboard/coupons';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import Box from '@mui/material/Box';
import Chip from '@mui/material/Chip';
import Typography from '@mui/material/Typography';
import { Pencil, Plus, Trash2 } from 'lucide-react';

interface Coupon {
    id: number;
    code: string;
    title: string | null;
    type: 'percentage' | 'fixed' | 'cashback';
    value: string;
    usage_limit: number | null;
    used_count: number;
    valid_from: string;
    valid_until: string;
    is_active: boolean;
    applicable_to: string;
}

interface PaginatedCoupons {
    data: Coupon[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

interface Props {
    coupons: PaginatedCoupons;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Coupons',
        href: couponsIndex().url,
    },
];

const typeChipColor = (type: string): 'info' | 'success' | 'warning' => {
    switch (type) {
        case 'percentage': return 'info';
        case 'fixed': return 'success';
        case 'cashback': return 'warning';
        default: return 'info';
    }
};

const formatValue = (coupon: Coupon): string => {
    if (coupon.type === 'percentage') return `${coupon.value}%`;
    return `GHS ${parseFloat(coupon.value).toFixed(2)}`;
};

const formatDate = (dateStr: string): string => {
    return new Date(dateStr).toLocaleDateString('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
};

export default function CouponsIndex({ coupons }: Props) {
    const handleDelete = (couponId: number, couponCode: string) => {
        if (
            confirm(
                `Are you sure you want to delete coupon "${couponCode}"? This action cannot be undone.`,
            )
        ) {
            router.delete(couponDestroy.url(couponId), {
                preserveScroll: true,
            });
        }
    };

    const handlePageChange = (page: number) => {
        router.get(
            couponsIndex.url(),
            { page },
            {
                preserveState: true,
                preserveScroll: true,
            },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Coupons" />
            <Box sx={{ display: 'flex', height: '100%', flex: 1, flexDirection: 'column', gap: 2, p: 2 }}>
                <Card>
                    <CardHeader sx={{ display: 'flex', flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' }}>
                        <Box>
                            <CardTitle>Coupons</CardTitle>
                            <CardDescription>
                                Manage discount codes and promotions
                            </CardDescription>
                        </Box>
                        <Button asChild>
                            <Link href={couponCreate.url()}>
                                <Plus style={{ marginRight: 8, width: 16, height: 16 }} />
                                Add Coupon
                            </Link>
                        </Button>
                    </CardHeader>
                    <CardContent>
                        <Box sx={{ overflowX: 'auto' }}>
                            <Box component="table" sx={{ width: '100%' }}>
                                <thead>
                                    <Box component="tr" sx={{ borderBottom: 1, borderColor: 'divider' }}>
                                        <Box component="th" sx={{ px: 1.5, py: 2, textAlign: 'left', fontSize: '0.875rem', fontWeight: 500 }}>Code</Box>
                                        <Box component="th" sx={{ px: 1.5, py: 2, textAlign: 'left', fontSize: '0.875rem', fontWeight: 500 }}>Title</Box>
                                        <Box component="th" sx={{ px: 1.5, py: 2, textAlign: 'left', fontSize: '0.875rem', fontWeight: 500 }}>Type</Box>
                                        <Box component="th" sx={{ px: 1.5, py: 2, textAlign: 'left', fontSize: '0.875rem', fontWeight: 500 }}>Value</Box>
                                        <Box component="th" sx={{ px: 1.5, py: 2, textAlign: 'left', fontSize: '0.875rem', fontWeight: 500 }}>Usage</Box>
                                        <Box component="th" sx={{ px: 1.5, py: 2, textAlign: 'left', fontSize: '0.875rem', fontWeight: 500 }}>Validity</Box>
                                        <Box component="th" sx={{ px: 1.5, py: 2, textAlign: 'left', fontSize: '0.875rem', fontWeight: 500 }}>Status</Box>
                                        <Box component="th" sx={{ px: 1.5, py: 2, textAlign: 'right', fontSize: '0.875rem', fontWeight: 500 }}>Actions</Box>
                                    </Box>
                                </thead>
                                <tbody>
                                    {coupons.data.map((coupon) => (
                                        <Box
                                            component="tr"
                                            key={coupon.id}
                                            sx={{ borderBottom: 1, borderColor: 'divider', '&:last-child': { border: 0 }, '&:hover': { bgcolor: 'action.hover' } }}
                                        >
                                            <Box component="td" sx={{ px: 1.5, py: 2, fontSize: '0.875rem', fontWeight: 500, fontFamily: 'monospace' }}>
                                                {coupon.code}
                                            </Box>
                                            <Box component="td" sx={{ px: 1.5, py: 2, fontSize: '0.875rem', color: coupon.title ? 'text.primary' : 'text.disabled' }}>
                                                {coupon.title || '—'}
                                            </Box>
                                            <Box component="td" sx={{ px: 1.5, py: 2 }}>
                                                <Chip label={coupon.type} color={typeChipColor(coupon.type)} size="small" variant="outlined" />
                                            </Box>
                                            <Box component="td" sx={{ px: 1.5, py: 2, fontSize: '0.875rem' }}>
                                                {formatValue(coupon)}
                                            </Box>
                                            <Box component="td" sx={{ px: 1.5, py: 2, fontSize: '0.875rem' }}>
                                                {coupon.used_count} / {coupon.usage_limit ?? '∞'}
                                            </Box>
                                            <Box component="td" sx={{ px: 1.5, py: 2, fontSize: '0.875rem', color: 'text.secondary' }}>
                                                {formatDate(coupon.valid_from)} — {formatDate(coupon.valid_until)}
                                            </Box>
                                            <Box component="td" sx={{ px: 1.5, py: 2 }}>
                                                <Chip
                                                    label={coupon.is_active ? 'Active' : 'Inactive'}
                                                    color={coupon.is_active ? 'success' : 'default'}
                                                    size="small"
                                                    variant="outlined"
                                                />
                                            </Box>
                                            <Box component="td" sx={{ px: 1.5, py: 2 }}>
                                                <Box sx={{ display: 'flex', justifyContent: 'flex-end', gap: 1 }}>
                                                    <Button variant="ghost" size="sm" asChild>
                                                        <Link href={couponEdit.url(coupon.id)}>
                                                            <Pencil style={{ width: 16, height: 16 }} />
                                                        </Link>
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => handleDelete(coupon.id, coupon.code)}
                                                    >
                                                        <Trash2 style={{ width: 16, height: 16, color: '#ef4444' }} />
                                                    </Button>
                                                </Box>
                                            </Box>
                                        </Box>
                                    ))}
                                </tbody>
                            </Box>
                        </Box>

                        {coupons.last_page > 1 && (
                            <Box sx={{ mt: 2, display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                                <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>
                                    Showing {coupons.data.length} of {coupons.total} coupons
                                </Typography>
                                <Box sx={{ display: 'flex', gap: 1 }}>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => handlePageChange(coupons.current_page - 1)}
                                        disabled={coupons.current_page === 1}
                                    >
                                        Previous
                                    </Button>
                                    <Box component="span" sx={{ display: 'flex', alignItems: 'center', px: 1, fontSize: '0.875rem' }}>
                                        Page {coupons.current_page} of {coupons.last_page}
                                    </Box>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => handlePageChange(coupons.current_page + 1)}
                                        disabled={coupons.current_page === coupons.last_page}
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

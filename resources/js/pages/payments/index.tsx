import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { useInactivityLock } from '@/hooks/use-inactivity-lock';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';
import { Search } from 'lucide-react';
import { useEffect, useState } from 'react';

interface Payment {
    id: number;
    type: 'order' | 'vendor_onboarding';
    reference: string;
    user_name: string;
    user_email: string;
    amount: string;
    currency: string;
    status: string;
    channel: string | null;
    paid_at: string | null;
    created_at: string;
    related_reference: string | null;
}

interface Props {
    payments: {
        data: Payment[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    statuses: string[];
    filters: {
        search?: string;
        status?: string;
        type?: string;
        from?: string;
        to?: string;
        sort_by?: string;
        sort_order?: string;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Payments', href: '/dashboard/payments' },
];

const statusBadgeColors: Record<string, { bg: string; text: string }> = {
    success: { bg: '#D1FAE5', text: '#065F46' },
    pending: { bg: '#FEF3C7', text: '#92400E' },
    processing: { bg: '#DBEAFE', text: '#1E40AF' },
    failed: { bg: '#FEE2E2', text: '#991B1B' },
    abandoned: { bg: '#F3F4F6', text: '#374151' },
    reversed: { bg: '#FFEDD5', text: '#9A3412' },
    cancelled: { bg: '#E5E7EB', text: '#6B7280' },
};

const formatStatus = (status: string): string => {
    return status.charAt(0).toUpperCase() + status.slice(1);
};

export default function PaymentsIndex({ payments, statuses, filters }: Props) {
    useInactivityLock();
    const [searchTerm, setSearchTerm] = useState(filters.search || '');

    useEffect(() => {
        const delayDebounceFn = setTimeout(() => {
            if (searchTerm !== filters.search) {
                router.get(
                    '/dashboard/payments',
                    { search: searchTerm, status: filters.status, type: filters.type, page: 1 },
                    {
                        preserveState: true,
                        preserveScroll: true,
                    },
                );
            }
        }, 300);

        return () => clearTimeout(delayDebounceFn);
    }, [searchTerm]);

    const handlePageChange = (page: number) => {
        router.get(
            '/dashboard/payments',
            {
                page,
                search: filters.search,
                status: filters.status,
                type: filters.type,
                sort_by: filters.sort_by,
                sort_order: filters.sort_order,
            },
            {
                preserveState: true,
                preserveScroll: true,
            },
        );
    };

    const handleStatusFilter = (status: string) => {
        router.get(
            '/dashboard/payments',
            {
                status: status || undefined,
                search: filters.search,
                type: filters.type,
                page: 1,
            },
            {
                preserveState: true,
                preserveScroll: true,
            },
        );
    };

    const handleTypeFilter = (type: string) => {
        router.get(
            '/dashboard/payments',
            {
                type: type || undefined,
                search: filters.search,
                status: filters.status,
                page: 1,
            },
            {
                preserveState: true,
                preserveScroll: true,
            },
        );
    };

    const handleRowClick = (payment: Payment) => {
        router.visit(`/dashboard/payments/${payment.type === 'order' ? 'order' : 'vendor-onboarding'}/${payment.id}`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Payments" />
            <Box sx={{ display: 'flex', height: '100%', flex: 1, flexDirection: 'column', gap: 2, p: 2 }}>
                <Card>
                    <CardHeader>
                        <CardTitle>Payment Management</CardTitle>
                        <CardDescription>
                            {payments.total} total payments
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {/* Search Bar & Filters */}
                        <Box sx={{ mb: 2, display: 'flex', alignItems: 'center', gap: 1 }}>
                            <Box sx={{ position: 'relative', flex: 1 }}>
                                <Search style={{ position: 'absolute', top: 10, left: 10, width: 16, height: 16, color: 'var(--muted-foreground)' }} />
                                <Input
                                    type="search"
                                    placeholder="Search by reference, user name, or email..."
                                    value={searchTerm}
                                    onChange={(e) => setSearchTerm(e.target.value)}
                                    style={{ paddingLeft: 36 }}
                                />
                            </Box>
                            <Box
                                component="select"
                                value={filters.status || ''}
                                onChange={(e: React.ChangeEvent<HTMLSelectElement>) => handleStatusFilter(e.target.value)}
                                sx={{
                                    height: 36,
                                    borderRadius: 1,
                                    border: 1,
                                    borderColor: 'divider',
                                    px: 1.5,
                                    fontSize: '0.875rem',
                                    bgcolor: 'background.paper',
                                    color: 'text.primary',
                                    cursor: 'pointer',
                                    '&:focus': { outline: 'none', borderColor: 'primary.main' },
                                }}
                            >
                                <option value="">All Statuses</option>
                                {statuses.map((status) => (
                                    <option key={status} value={status}>
                                        {formatStatus(status)}
                                    </option>
                                ))}
                            </Box>
                            <Box
                                component="select"
                                value={filters.type || ''}
                                onChange={(e: React.ChangeEvent<HTMLSelectElement>) => handleTypeFilter(e.target.value)}
                                sx={{
                                    height: 36,
                                    borderRadius: 1,
                                    border: 1,
                                    borderColor: 'divider',
                                    px: 1.5,
                                    fontSize: '0.875rem',
                                    bgcolor: 'background.paper',
                                    color: 'text.primary',
                                    cursor: 'pointer',
                                    '&:focus': { outline: 'none', borderColor: 'primary.main' },
                                }}
                            >
                                <option value="">All Types</option>
                                <option value="order">Order Payments</option>
                                <option value="vendor-onboarding">Vendor Onboarding</option>
                            </Box>
                        </Box>

                        <Box sx={{ overflowX: 'auto' }}>
                            <Box component="table" sx={{ width: '100%' }}>
                                <thead>
                                    <Box component="tr" sx={{ borderBottom: 1, borderColor: 'divider' }}>
                                        <Box component="th" sx={{ p: 1, textAlign: 'left', fontSize: '0.875rem', fontWeight: 500 }}>
                                            Reference
                                        </Box>
                                        <Box component="th" sx={{ p: 1, textAlign: 'left', fontSize: '0.875rem', fontWeight: 500 }}>
                                            User
                                        </Box>
                                        <Box component="th" sx={{ p: 1, textAlign: 'left', fontSize: '0.875rem', fontWeight: 500 }}>
                                            Type
                                        </Box>
                                        <Box component="th" sx={{ p: 1, textAlign: 'left', fontSize: '0.875rem', fontWeight: 500 }}>
                                            Amount
                                        </Box>
                                        <Box component="th" sx={{ p: 1, textAlign: 'left', fontSize: '0.875rem', fontWeight: 500 }}>
                                            Status
                                        </Box>
                                        <Box component="th" sx={{ p: 1, textAlign: 'left', fontSize: '0.875rem', fontWeight: 500 }}>
                                            Channel
                                        </Box>
                                        <Box component="th" sx={{ p: 1, textAlign: 'left', fontSize: '0.875rem', fontWeight: 500 }}>
                                            Date
                                        </Box>
                                    </Box>
                                </thead>
                                <tbody>
                                    {payments.data.map((payment) => {
                                        const statusColors = statusBadgeColors[payment.status] || { bg: '#F3F4F6', text: '#374151' };

                                        return (
                                            <Box
                                                component="tr"
                                                key={payment.id}
                                                onClick={() => handleRowClick(payment)}
                                                sx={{
                                                    borderBottom: 1,
                                                    borderColor: 'divider',
                                                    cursor: 'pointer',
                                                    '&:last-child': { border: 0 },
                                                    '&:hover': { bgcolor: 'action.hover' },
                                                }}
                                            >
                                                <Box component="td" sx={{ p: 1, fontSize: '0.875rem', fontFamily: 'monospace', maxWidth: 200, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                                                    {payment.reference}
                                                </Box>
                                                <Box component="td" sx={{ p: 1, fontSize: '0.875rem' }}>
                                                    {payment.user_name}
                                                </Box>
                                                <Box component="td" sx={{ p: 1, fontSize: '0.875rem' }}>
                                                    {payment.type === 'order' && payment.related_reference
                                                        ? `Order ${payment.related_reference}`
                                                        : 'Vendor Onboarding'}
                                                </Box>
                                                <Box component="td" sx={{ p: 1, fontSize: '0.875rem' }}>
                                                    {payment.currency} {payment.amount}
                                                </Box>
                                                <Box component="td" sx={{ p: 1 }}>
                                                    <Box
                                                        component="span"
                                                        sx={{
                                                            display: 'inline-block',
                                                            px: 1,
                                                            py: 0.25,
                                                            borderRadius: '9999px',
                                                            fontSize: '0.75rem',
                                                            fontWeight: 500,
                                                            bgcolor: statusColors.bg,
                                                            color: statusColors.text,
                                                        }}
                                                    >
                                                        {formatStatus(payment.status)}
                                                    </Box>
                                                </Box>
                                                <Box component="td" sx={{ p: 1, fontSize: '0.875rem' }}>
                                                    {payment.channel || '\u2014'}
                                                </Box>
                                                <Box component="td" sx={{ p: 1, fontSize: '0.875rem' }}>
                                                    {new Date(payment.created_at).toLocaleDateString()}
                                                </Box>
                                            </Box>
                                        );
                                    })}
                                    {payments.data.length === 0 && (
                                        <Box component="tr">
                                            <Box component="td" colSpan={7} sx={{ p: 4, textAlign: 'center', fontSize: '0.875rem', color: 'text.secondary' }}>
                                                No payments found.
                                            </Box>
                                        </Box>
                                    )}
                                </tbody>
                            </Box>
                        </Box>

                        {/* Pagination */}
                        {payments.last_page > 1 && (
                            <Box sx={{ mt: 2, display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                                <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>
                                    Showing {payments.data.length} of {payments.total} payments
                                </Typography>
                                <Box sx={{ display: 'flex', gap: 1 }}>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => handlePageChange(payments.current_page - 1)}
                                        disabled={payments.current_page === 1}
                                    >
                                        Previous
                                    </Button>
                                    <Box component="span" sx={{ display: 'flex', alignItems: 'center', px: 1, fontSize: '0.875rem' }}>
                                        Page {payments.current_page} of {payments.last_page}
                                    </Box>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => handlePageChange(payments.current_page + 1)}
                                        disabled={payments.current_page === payments.last_page}
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

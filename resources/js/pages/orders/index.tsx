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

interface Order {
    id: number;
    order_number: string;
    user: { id: number; name: string; email: string } | null;
    vendor: { id: number; name: string } | null;
    items: { id: number }[];
    total: string;
    currency: string;
    status: string;
    payment_status: string;
    delivery_method: string | null;
    created_at: string;
}

interface Props {
    orders: {
        data: Order[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    statuses: string[];
    deliveryMethods: string[];
    filters: {
        search?: string;
        status?: string;
        delivery_method?: string;
        sort_by?: string;
        sort_order?: string;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Orders', href: '/dashboard/orders' },
];

const statusBadgeColors: Record<string, { bg: string; text: string }> = {
    pending: { bg: '#FEF3C7', text: '#92400E' },
    confirmed: { bg: '#DBEAFE', text: '#1E40AF' },
    processing: { bg: '#EDE9FE', text: '#6B21A8' },
    fulfilled: { bg: '#D1FAE5', text: '#065F46' },
    shipped: { bg: '#E0E7FF', text: '#3730A3' },
    delivered: { bg: '#D1FAE5', text: '#065F46' },
    refunded: { bg: '#FEE2E2', text: '#991B1B' },
};

const paymentBadgeColors: Record<string, { bg: string; text: string }> = {
    unpaid: { bg: '#FEE2E2', text: '#991B1B' },
    pending: { bg: '#FEF3C7', text: '#92400E' },
    paid: { bg: '#D1FAE5', text: '#065F46' },
    failed: { bg: '#FEE2E2', text: '#991B1B' },
    refunded: { bg: '#FFEDD5', text: '#9A3412' },
};

const formatStatus = (status: string): string => {
    return status.charAt(0).toUpperCase() + status.slice(1);
};

const deliveryMethodLabel: Record<string, string> = {
    vendor_self: 'Vendor Self',
    platform_rider: 'Platform Rider',
    third_party_courier: 'Courier',
};

export default function OrdersIndex({ orders, statuses, deliveryMethods, filters }: Props) {
    useInactivityLock();
    const [searchTerm, setSearchTerm] = useState(filters.search || '');

    useEffect(() => {
        const delayDebounceFn = setTimeout(() => {
            if (searchTerm !== filters.search) {
                router.get(
                    '/dashboard/orders',
                    {
                        status: filters.status,
                        delivery_method: filters.delivery_method,
                        search: searchTerm,
                        page: 1,
                    },
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
            '/dashboard/orders',
            {
                page,
                search: filters.search,
                status: filters.status,
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
            '/dashboard/orders',
            {
                status: status || undefined,
                delivery_method: filters.delivery_method,
                search: filters.search,
                sort_by: filters.sort_by,
                sort_order: filters.sort_order,
                page: 1,
            },
            {
                preserveState: true,
                preserveScroll: true,
            },
        );
    };

    const handleDeliveryFilter = (method: string) => {
        router.get(
            '/dashboard/orders',
            {
                status: filters.status,
                delivery_method: method || undefined,
                search: filters.search,
                sort_by: filters.sort_by,
                sort_order: filters.sort_order,
                page: 1,
            },
            {
                preserveState: true,
                preserveScroll: true,
            },
        );
    };

    const handleRowClick = (orderId: number) => {
        router.visit(`/dashboard/orders/${orderId}`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Orders" />
            <Box
                sx={{
                    display: 'flex',
                    height: '100%',
                    flex: 1,
                    flexDirection: 'column',
                    gap: 2,
                    p: 2,
                }}
            >
                <Card>
                    <CardHeader>
                        <CardTitle>Order Management</CardTitle>
                        <CardDescription>
                            {orders.total} total orders
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {/* Search Bar & Status Filter */}
                        <Box
                            sx={{
                                mb: 2,
                                display: 'flex',
                                alignItems: 'center',
                                gap: 1,
                            }}
                        >
                            <Box sx={{ position: 'relative', flex: 1 }}>
                                <Search
                                    style={{
                                        position: 'absolute',
                                        top: 10,
                                        left: 10,
                                        width: 16,
                                        height: 16,
                                        color: 'var(--muted-foreground)',
                                    }}
                                />
                                <Input
                                    type="search"
                                    placeholder="Search by order number or customer name..."
                                    value={searchTerm}
                                    onChange={(e) =>
                                        setSearchTerm(e.target.value)
                                    }
                                    style={{ paddingLeft: 36 }}
                                />
                            </Box>
                            <Box
                                component="select"
                                value={filters.status || ''}
                                onChange={(
                                    e: React.ChangeEvent<HTMLSelectElement>,
                                ) => handleStatusFilter(e.target.value)}
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
                                    '&:focus': {
                                        outline: 'none',
                                        borderColor: 'primary.main',
                                    },
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
                                value={filters.delivery_method || ''}
                                onChange={(
                                    e: React.ChangeEvent<HTMLSelectElement>,
                                ) => handleDeliveryFilter(e.target.value)}
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
                                    '&:focus': {
                                        outline: 'none',
                                        borderColor: 'primary.main',
                                    },
                                }}
                            >
                                <option value="">All Deliveries</option>
                                {deliveryMethods.map((method) => (
                                    <option key={method} value={method}>
                                        {deliveryMethodLabel[method] || formatStatus(method)}
                                    </option>
                                ))}
                                <option value="none">No Delivery</option>
                            </Box>
                        </Box>

                        <Box sx={{ overflowX: 'auto' }}>
                            <Box component="table" sx={{ width: '100%' }}>
                                <thead>
                                    <Box
                                        component="tr"
                                        sx={{
                                            borderBottom: 1,
                                            borderColor: 'divider',
                                        }}
                                    >
                                        <Box
                                            component="th"
                                            sx={{
                                                p: 1,
                                                textAlign: 'left',
                                                fontSize: '0.875rem',
                                                fontWeight: 500,
                                            }}
                                        >
                                            Order #
                                        </Box>
                                        <Box
                                            component="th"
                                            sx={{
                                                p: 1,
                                                textAlign: 'left',
                                                fontSize: '0.875rem',
                                                fontWeight: 500,
                                            }}
                                        >
                                            Customer
                                        </Box>
                                        <Box
                                            component="th"
                                            sx={{
                                                p: 1,
                                                textAlign: 'left',
                                                fontSize: '0.875rem',
                                                fontWeight: 500,
                                            }}
                                        >
                                            Vendor
                                        </Box>
                                        <Box
                                            component="th"
                                            sx={{
                                                p: 1,
                                                textAlign: 'left',
                                                fontSize: '0.875rem',
                                                fontWeight: 500,
                                            }}
                                        >
                                            Items
                                        </Box>
                                        <Box
                                            component="th"
                                            sx={{
                                                p: 1,
                                                textAlign: 'left',
                                                fontSize: '0.875rem',
                                                fontWeight: 500,
                                            }}
                                        >
                                            Total (GHS)
                                        </Box>
                                        <Box
                                            component="th"
                                            sx={{
                                                p: 1,
                                                textAlign: 'left',
                                                fontSize: '0.875rem',
                                                fontWeight: 500,
                                            }}
                                        >
                                            Status
                                        </Box>
                                        <Box
                                            component="th"
                                            sx={{
                                                p: 1,
                                                textAlign: 'left',
                                                fontSize: '0.875rem',
                                                fontWeight: 500,
                                            }}
                                        >
                                            Payment
                                        </Box>
                                        <Box
                                            component="th"
                                            sx={{
                                                p: 1,
                                                textAlign: 'left',
                                                fontSize: '0.875rem',
                                                fontWeight: 500,
                                            }}
                                        >
                                            Delivery
                                        </Box>
                                        <Box
                                            component="th"
                                            sx={{
                                                p: 1,
                                                textAlign: 'left',
                                                fontSize: '0.875rem',
                                                fontWeight: 500,
                                            }}
                                        >
                                            Date
                                        </Box>
                                    </Box>
                                </thead>
                                <tbody>
                                    {orders.data.map((order) => {
                                        const statusColors = statusBadgeColors[
                                            order.status
                                        ] || { bg: '#F3F4F6', text: '#374151' };
                                        const paymentColors =
                                            paymentBadgeColors[
                                                order.payment_status
                                            ] || {
                                                bg: '#F3F4F6',
                                                text: '#374151',
                                            };

                                        return (
                                            <Box
                                                component="tr"
                                                key={order.id}
                                                onClick={() =>
                                                    handleRowClick(order.id)
                                                }
                                                sx={{
                                                    borderBottom: 1,
                                                    borderColor: 'divider',
                                                    cursor: 'pointer',
                                                    '&:last-child': {
                                                        border: 0,
                                                    },
                                                    '&:hover': {
                                                        bgcolor: 'action.hover',
                                                    },
                                                }}
                                            >
                                                <Box
                                                    component="td"
                                                    sx={{
                                                        p: 1,
                                                        fontSize: '0.875rem',
                                                        fontFamily: 'monospace',
                                                    }}
                                                >
                                                    {order.order_number}
                                                </Box>
                                                <Box
                                                    component="td"
                                                    sx={{
                                                        p: 1,
                                                        fontSize: '0.875rem',
                                                    }}
                                                >
                                                    {order.user?.name ||
                                                        'Guest'}
                                                </Box>
                                                <Box
                                                    component="td"
                                                    sx={{
                                                        p: 1,
                                                        fontSize: '0.875rem',
                                                    }}
                                                >
                                                    {order.vendor?.name ||
                                                        'Unknown'}
                                                </Box>
                                                <Box
                                                    component="td"
                                                    sx={{
                                                        p: 1,
                                                        fontSize: '0.875rem',
                                                    }}
                                                >
                                                    {order.items?.length || 0}
                                                </Box>
                                                <Box
                                                    component="td"
                                                    sx={{
                                                        p: 1,
                                                        fontSize: '0.875rem',
                                                    }}
                                                >
                                                    {parseFloat(
                                                        order.total,
                                                    ).toFixed(2)}
                                                </Box>
                                                <Box
                                                    component="td"
                                                    sx={{ p: 1 }}
                                                >
                                                    <Box
                                                        component="span"
                                                        sx={{
                                                            display:
                                                                'inline-block',
                                                            px: 1,
                                                            py: 0.25,
                                                            borderRadius:
                                                                '9999px',
                                                            fontSize: '0.75rem',
                                                            fontWeight: 500,
                                                            bgcolor:
                                                                statusColors.bg,
                                                            color: statusColors.text,
                                                        }}
                                                    >
                                                        {formatStatus(
                                                            order.status,
                                                        )}
                                                    </Box>
                                                </Box>
                                                <Box
                                                    component="td"
                                                    sx={{ p: 1 }}
                                                >
                                                    <Box
                                                        component="span"
                                                        sx={{
                                                            display:
                                                                'inline-block',
                                                            px: 1,
                                                            py: 0.25,
                                                            borderRadius:
                                                                '9999px',
                                                            fontSize: '0.75rem',
                                                            fontWeight: 500,
                                                            bgcolor:
                                                                paymentColors.bg,
                                                            color: paymentColors.text,
                                                        }}
                                                    >
                                                        {formatStatus(
                                                            order.payment_status,
                                                        )}
                                                    </Box>
                                                </Box>
                                                <Box
                                                    component="td"
                                                    sx={{ p: 1 }}
                                                >
                                                    {order.delivery_method ? (
                                                        <Box
                                                            component="span"
                                                            sx={{
                                                                display:
                                                                    'inline-block',
                                                                px: 1,
                                                                py: 0.25,
                                                                borderRadius:
                                                                    '9999px',
                                                                fontSize: '0.75rem',
                                                                fontWeight: 500,
                                                                bgcolor: order.delivery_method === 'platform_rider'
                                                                    ? '#DBEAFE'
                                                                    : order.delivery_method === 'vendor_self'
                                                                        ? '#FEF3C7'
                                                                        : '#E0E7FF',
                                                                color: order.delivery_method === 'platform_rider'
                                                                    ? '#1E40AF'
                                                                    : order.delivery_method === 'vendor_self'
                                                                        ? '#92400E'
                                                                        : '#3730A3',
                                                            }}
                                                        >
                                                            {deliveryMethodLabel[order.delivery_method] ||
                                                                formatStatus(order.delivery_method)}
                                                        </Box>
                                                    ) : (
                                                        <Typography
                                                            sx={{
                                                                fontSize: '0.75rem',
                                                                color: 'text.disabled',
                                                            }}
                                                        >
                                                            —
                                                        </Typography>
                                                    )}
                                                </Box>
                                                <Box
                                                    component="td"
                                                    sx={{
                                                        p: 1,
                                                        fontSize: '0.875rem',
                                                    }}
                                                >
                                                    {new Date(
                                                        order.created_at,
                                                    ).toLocaleDateString()}
                                        </Box>
                                    </Box>
                                );
                            })}
                            {orders.data.length === 0 && (
                                <Box component="tr">
                                    <Box
                                        component="td"
                                        colSpan={9}
                                        sx={{
                                            p: 4,
                                            textAlign: 'center',
                                            fontSize: '0.875rem',
                                            color: 'text.secondary',
                                        }}
                                    >
                                        No orders found.
                                    </Box>
                                </Box>
                            )}
                                </tbody>
                            </Box>
                        </Box>

                        {/* Pagination */}
                        {orders.last_page > 1 && (
                            <Box
                                sx={{
                                    mt: 2,
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'space-between',
                                }}
                            >
                                <Typography
                                    sx={{
                                        fontSize: '0.875rem',
                                        color: 'text.secondary',
                                    }}
                                >
                                    Showing {orders.data.length} of{' '}
                                    {orders.total} orders
                                </Typography>
                                <Box sx={{ display: 'flex', gap: 1 }}>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            handlePageChange(
                                                orders.current_page - 1,
                                            )
                                        }
                                        disabled={orders.current_page === 1}
                                    >
                                        Previous
                                    </Button>
                                    <Box
                                        component="span"
                                        sx={{
                                            display: 'flex',
                                            alignItems: 'center',
                                            px: 1,
                                            fontSize: '0.875rem',
                                        }}
                                    >
                                        Page {orders.current_page} of{' '}
                                        {orders.last_page}
                                    </Box>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            handlePageChange(
                                                orders.current_page + 1,
                                            )
                                        }
                                        disabled={
                                            orders.current_page ===
                                            orders.last_page
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

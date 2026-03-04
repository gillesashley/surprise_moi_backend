import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import Box from '@mui/material/Box';
import Chip from '@mui/material/Chip';
import Typography from '@mui/material/Typography';
import {
    ChevronLeft,
    ChevronRight,
    DollarSign,
    Package,
    ShoppingCart,
    Truck,
    User,
} from 'lucide-react';

interface Address {
    label: string;
    address_line_1: string;
    address_line_2: string | null;
    city: string;
    state: string;
}

interface Customer {
    name: string;
    email: string | null;
    phone: string | null;
}

interface Receiver {
    name: string;
    phone: string | null;
    address: Address | null;
}

interface Vendor {
    name: string;
    email: string | null;
    phone: string | null;
}

interface OrderItem {
    name: string;
    type: string;
    quantity: number;
    unit_price: string;
    subtotal: string;
}

interface Rider {
    name: string;
    phone: string;
}

interface Order {
    id: number;
    type: 'order' | 'payout';
    transaction_number: string;
    customer: Customer;
    receiver: Receiver | null;
    vendor: Vendor | null;
    items: OrderItem[];
    amount: string;
    subtotal: string;
    delivery_fee: string;
    discount_amount: string;
    platform_commission: string;
    vendor_payout: string;
    status: string;
    payment_status: string | null;
    delivery_method: string | null;
    scheduled_datetime: string | null;
    delivered_at: string | null;
    rider: Rider | null;
    created_at: string;
    description: string;
}

interface Pagination {
    current_page: number;
    data: Order[];
    first_page_url: string;
    from: number;
    last_page: number;
    last_page_url: string;
    links: Array<{ url: string | null; label: string; active: boolean }>;
    next_page_url: string | null;
    path: string;
    per_page: number;
    prev_page_url: string | null;
    to: number;
    total: number;
}

interface Statistics {
    total_orders: number;
    total_sales: string;
    total_commission: string;
    pending_orders: number;
    delivered_orders: number;
    total_payouts: string;
    pending_payouts: string;
    net_income: string;
}

interface Props {
    orders: Pagination;
    statistics: Statistics;
    filters: {
        type: string;
        status: string | null;
        date_from: string | null;
        date_to: string | null;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'All Transactions',
        href: '/dashboard/transactions',
    },
];

const getStatusChipColor = (status: string): 'warning' | 'info' | 'secondary' | 'success' | 'error' | 'default' => {
    const colorMap: Record<string, 'warning' | 'info' | 'secondary' | 'success' | 'error' | 'default'> = {
        pending: 'warning',
        confirmed: 'info',
        processing: 'secondary',
        fulfilled: 'info',
        delivered: 'success',
        cancelled: 'error',
        refunded: 'default',
        paid: 'success',
        unpaid: 'default',
        approved: 'info',
        rejected: 'error',
    };
    return colorMap[status] || 'warning';
};

const getStatusBadge = (status: string) => {
    return (
        <Chip
            label={status.charAt(0).toUpperCase() + status.slice(1)}
            color={getStatusChipColor(status)}
            size="small"
            variant="outlined"
        />
    );
};

const formatCurrency = (amount: string) => {
    return new Intl.NumberFormat('en-GH', {
        style: 'currency',
        currency: 'GHS',
    }).format(parseFloat(amount));
};

const formatDate = (dateString: string | null) => {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString('en-GH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

const formatDateTime = (dateString: string | null) => {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString('en-GH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const getDeliveryMethodLabel = (method: string | null) => {
    if (!method) return 'Vendor Self';
    const labels: Record<string, string> = {
        vendor_self: 'Vendor Self',
        platform_rider: 'Platform Rider',
        third_party_courier: 'Third Party Courier',
    };
    return labels[method] || method;
};

export default function TransactionsIndex({
    orders,
    statistics,
    filters,
}: Props) {
    const handleFilterChange = (key: string, value: string | null) => {
        const newFilters = { ...filters, [key]: value };
        router.get('/dashboard/transactions', newFilters, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handlePageChange = (page: number) => {
        router.get(
            '/dashboard/transactions',
            { ...filters, page },
            {
                preserveState: true,
                preserveScroll: true,
            },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="All Transactions" />
            <Box sx={{ display: 'flex', flex: 1, flexDirection: 'column', gap: 2, p: 2, height: '100%' }}>
                {/* Statistics Cards */}
                <Box sx={{ display: 'grid', gap: 2, gridTemplateColumns: { xs: '1fr', md: 'repeat(2, 1fr)', lg: 'repeat(4, 1fr)' }, '& > *': { minWidth: 0 } }}>
                    <Card sx={{ py: 2, gap: 1 }}>
                        <CardHeader sx={{ display: 'flex', flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', px: 3, pb: 0, '& > *': { my: 0 } }}>
                            <CardTitle sx={{ fontSize: '0.875rem', fontWeight: 500 }}>
                                Total Orders
                            </CardTitle>
                            <ShoppingCart style={{ width: 16, height: 16, color: 'var(--muted-foreground)', flexShrink: 0 }} />
                        </CardHeader>
                        <CardContent>
                            <Box sx={{ fontSize: '1.5rem', fontWeight: 700 }}>
                                {statistics.total_orders}
                            </Box>
                            <Typography sx={{ fontSize: '0.75rem', color: 'text.secondary' }}>
                                All time orders
                            </Typography>
                        </CardContent>
                    </Card>

                    <Card sx={{ py: 2, gap: 1 }}>
                        <CardHeader sx={{ display: 'flex', flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', px: 3, pb: 0, '& > *': { my: 0 } }}>
                            <CardTitle sx={{ fontSize: '0.875rem', fontWeight: 500 }}>
                                Total Sales
                            </CardTitle>
                            <DollarSign style={{ width: 16, height: 16, color: 'var(--muted-foreground)', flexShrink: 0 }} />
                        </CardHeader>
                        <CardContent>
                            <Box sx={{ fontSize: '1.5rem', fontWeight: 700, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                                {formatCurrency(statistics.total_sales)}
                            </Box>
                            <Typography sx={{ fontSize: '0.75rem', color: 'text.secondary' }}>
                                Gross revenue
                            </Typography>
                        </CardContent>
                    </Card>

                    <Card sx={{ py: 2, gap: 1 }}>
                        <CardHeader sx={{ display: 'flex', flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', px: 3, pb: 0, '& > *': { my: 0 } }}>
                            <CardTitle sx={{ fontSize: '0.875rem', fontWeight: 500 }}>
                                Pending Orders
                            </CardTitle>
                            <Package style={{ width: 16, height: 16, color: 'var(--muted-foreground)', flexShrink: 0 }} />
                        </CardHeader>
                        <CardContent>
                            <Box sx={{ fontSize: '1.5rem', fontWeight: 700 }}>
                                {statistics.pending_orders}
                            </Box>
                            <Typography sx={{ fontSize: '0.75rem', color: 'text.secondary' }}>
                                Awaiting processing
                            </Typography>
                        </CardContent>
                    </Card>

                    <Card sx={{ py: 2, gap: 1 }}>
                        <CardHeader sx={{ display: 'flex', flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', px: 3, pb: 0, '& > *': { my: 0 } }}>
                            <CardTitle sx={{ fontSize: '0.875rem', fontWeight: 500 }}>
                                Delivered Orders
                            </CardTitle>
                            <Truck style={{ width: 16, height: 16, color: 'var(--muted-foreground)', flexShrink: 0 }} />
                        </CardHeader>
                        <CardContent>
                            <Box sx={{ fontSize: '1.5rem', fontWeight: 700 }}>
                                {statistics.delivered_orders}
                            </Box>
                            <Typography sx={{ fontSize: '0.75rem', color: 'text.secondary' }}>
                                Successfully delivered
                            </Typography>
                        </CardContent>
                    </Card>
                </Box>

                {/* Filters */}
                <Card>
                    <CardHeader>
                        <CardTitle>Filters</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Box sx={{ display: 'flex', flexWrap: 'wrap', gap: 2 }}>
                            <Box sx={{ width: 200 }}>
                                <Select
                                    value={filters.type}
                                    onValueChange={(value) =>
                                        handleFilterChange('type', value)
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Transaction Type" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="order">
                                            Orders
                                        </SelectItem>
                                        <SelectItem value="payout">
                                            Payouts
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </Box>

                            <Box sx={{ width: 200 }}>
                                <Select
                                    value={filters.status || 'all'}
                                    onValueChange={(value) =>
                                        handleFilterChange(
                                            'status',
                                            value === 'all' ? null : value,
                                        )
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Status" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">
                                            All Statuses
                                        </SelectItem>
                                        <SelectItem value="pending">
                                            Pending
                                        </SelectItem>
                                        <SelectItem value="confirmed">
                                            Confirmed
                                        </SelectItem>
                                        <SelectItem value="processing">
                                            Processing
                                        </SelectItem>
                                        <SelectItem value="fulfilled">
                                            Fulfilled
                                        </SelectItem>
                                        <SelectItem value="delivered">
                                            Delivered
                                        </SelectItem>
                                        <SelectItem value="cancelled">
                                            Cancelled
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </Box>

                            <Box sx={{ width: 200 }}>
                                <Input
                                    type="date"
                                    placeholder="From Date"
                                    value={filters.date_from || ''}
                                    onChange={(e) =>
                                        handleFilterChange(
                                            'date_from',
                                            e.target.value || null,
                                        )
                                    }
                                />
                            </Box>

                            <Box sx={{ width: 200 }}>
                                <Input
                                    type="date"
                                    placeholder="To Date"
                                    value={filters.date_to || ''}
                                    onChange={(e) =>
                                        handleFilterChange(
                                            'date_to',
                                            e.target.value || null,
                                        )
                                    }
                                />
                            </Box>
                        </Box>
                    </CardContent>
                </Card>

                {/* Orders Table */}
                <Card>
                    <CardHeader>
                        <Box sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                            <Box>
                                <CardTitle>All Transactions</CardTitle>
                                <CardDescription>
                                    Showing {orders.from || 0} to{' '}
                                    {orders.to || 0} of {orders.total} orders
                                </CardDescription>
                            </Box>
                        </Box>
                    </CardHeader>
                    <CardContent>
                        {orders.data.length === 0 ? (
                            <Box sx={{ py: 4, textAlign: 'center', color: 'text.secondary' }}>
                                No transactions found.
                            </Box>
                        ) : (
                            <>
                                <Box sx={{ overflowX: 'auto' }}>
                                    <Box component="table" sx={{ width: '100%' }}>
                                        <thead>
                                            <Box component="tr" sx={{ borderBottom: 1, borderColor: 'divider' }}>
                                                <Box component="th" sx={{ p: 1, textAlign: 'left', fontSize: '0.875rem', fontWeight: 500 }}>
                                                    Order #
                                                </Box>
                                                <Box component="th" sx={{ p: 1, textAlign: 'left', fontSize: '0.875rem', fontWeight: 500 }}>
                                                    Customer
                                                </Box>
                                                <Box component="th" sx={{ p: 1, textAlign: 'left', fontSize: '0.875rem', fontWeight: 500 }}>
                                                    Product/Service
                                                </Box>
                                                <Box component="th" sx={{ p: 1, textAlign: 'left', fontSize: '0.875rem', fontWeight: 500 }}>
                                                    Receiver
                                                </Box>
                                                <Box component="th" sx={{ p: 1, textAlign: 'left', fontSize: '0.875rem', fontWeight: 500 }}>
                                                    Vendor
                                                </Box>
                                                <Box component="th" sx={{ p: 1, textAlign: 'left', fontSize: '0.875rem', fontWeight: 500 }}>
                                                    Amount
                                                </Box>
                                                <Box component="th" sx={{ p: 1, textAlign: 'left', fontSize: '0.875rem', fontWeight: 500 }}>
                                                    Delivery Date
                                                </Box>
                                                <Box component="th" sx={{ p: 1, textAlign: 'left', fontSize: '0.875rem', fontWeight: 500 }}>
                                                    Delivered By
                                                </Box>
                                                <Box component="th" sx={{ p: 1, textAlign: 'left', fontSize: '0.875rem', fontWeight: 500 }}>
                                                    Status
                                                </Box>
                                            </Box>
                                        </thead>
                                        <tbody>
                                            {orders.data.map((order) => (
                                                <Box
                                                    component="tr"
                                                    key={order.id}
                                                    sx={{
                                                        borderBottom: 1,
                                                        borderColor: 'divider',
                                                        '&:last-child': { borderBottom: 0 },
                                                        '&:hover': { bgcolor: 'action.hover' },
                                                    }}
                                                >
                                                    <Box component="td" sx={{ p: 1 }}>
                                                        <Box sx={{ fontSize: '0.875rem', fontWeight: 500 }}>
                                                            {
                                                                order.transaction_number
                                                            }
                                                        </Box>
                                                        <Box sx={{ fontSize: '0.75rem', color: 'text.secondary' }}>
                                                            {formatDateTime(
                                                                order.created_at,
                                                            )}
                                                        </Box>
                                                    </Box>
                                                    <Box component="td" sx={{ p: 1 }}>
                                                        <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                                                            <User style={{ width: 16, height: 16, color: 'var(--muted-foreground)' }} />
                                                            <Box>
                                                                <Box sx={{ fontSize: '0.875rem', fontWeight: 500 }}>
                                                                    {
                                                                        order
                                                                            .customer
                                                                            .name
                                                                    }
                                                                </Box>
                                                                {order.customer
                                                                    .phone && (
                                                                    <Box sx={{ fontSize: '0.75rem', color: 'text.secondary' }}>
                                                                        {
                                                                            order
                                                                                .customer
                                                                                .phone
                                                                        }
                                                                    </Box>
                                                                )}
                                                            </Box>
                                                        </Box>
                                                    </Box>
                                                    <Box component="td" sx={{ p: 1 }}>
                                                        <Box sx={{ fontSize: '0.875rem' }}>
                                                            {order.items
                                                                .length > 0 ? (
                                                                <Box>
                                                                    <Box sx={{ fontWeight: 500 }}>
                                                                        {
                                                                            order
                                                                                .items[0]
                                                                                .name
                                                                        }
                                                                    </Box>
                                                                    {order.items
                                                                        .length >
                                                                        1 && (
                                                                        <Box sx={{ fontSize: '0.75rem', color: 'text.secondary' }}>
                                                                            +
                                                                            {order
                                                                                .items
                                                                                .length -
                                                                                1}{' '}
                                                                            more
                                                                            items
                                                                        </Box>
                                                                    )}
                                                                </Box>
                                                            ) : (
                                                                <Box component="span" sx={{ color: 'text.secondary' }}>
                                                                    -
                                                                </Box>
                                                            )}
                                                        </Box>
                                                    </Box>
                                                    <Box component="td" sx={{ p: 1 }}>
                                                        {order.receiver ? (
                                                            <Box>
                                                                <Box sx={{ fontSize: '0.875rem', fontWeight: 500 }}>
                                                                    {
                                                                        order
                                                                            .receiver
                                                                            .name
                                                                    }
                                                                </Box>
                                                                {order.receiver
                                                                    .phone && (
                                                                    <Box sx={{ fontSize: '0.75rem', color: 'text.secondary' }}>
                                                                        {
                                                                            order
                                                                                .receiver
                                                                                .phone
                                                                        }
                                                                    </Box>
                                                                )}
                                                                {order.receiver
                                                                    .address && (
                                                                    <Box sx={{ maxWidth: 150, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap', fontSize: '0.75rem', color: 'text.secondary' }}>
                                                                        {
                                                                            order
                                                                                .receiver
                                                                                .address
                                                                                .city
                                                                        }
                                                                        ,{' '}
                                                                        {
                                                                            order
                                                                                .receiver
                                                                                .address
                                                                                .state
                                                                        }
                                                                    </Box>
                                                                )}
                                                            </Box>
                                                        ) : (
                                                            <Box component="span" sx={{ color: 'text.secondary' }}>
                                                                -
                                                            </Box>
                                                        )}
                                                    </Box>
                                                    <Box component="td" sx={{ p: 1 }}>
                                                        {order.vendor ? (
                                                            <Box>
                                                                <Box sx={{ fontSize: '0.875rem', fontWeight: 500 }}>
                                                                    {
                                                                        order
                                                                            .vendor
                                                                            .name
                                                                    }
                                                                </Box>
                                                                {order.vendor
                                                                    .phone && (
                                                                    <Box sx={{ fontSize: '0.75rem', color: 'text.secondary' }}>
                                                                        {
                                                                            order
                                                                                .vendor
                                                                                .phone
                                                                        }
                                                                    </Box>
                                                                )}
                                                            </Box>
                                                        ) : (
                                                            <Box component="span" sx={{ color: 'text.secondary' }}>
                                                                -
                                                            </Box>
                                                        )}
                                                    </Box>
                                                    <Box component="td" sx={{ p: 1 }}>
                                                        <Box sx={{ fontSize: '0.875rem', fontWeight: 500, color: 'success.main' }}>
                                                            {formatCurrency(
                                                                order.amount,
                                                            )}
                                                        </Box>
                                                        <Box sx={{ fontSize: '0.75rem', color: 'text.secondary' }}>
                                                            Commission:{' '}
                                                            {formatCurrency(
                                                                order.platform_commission,
                                                            )}
                                                        </Box>
                                                    </Box>
                                                    <Box component="td" sx={{ p: 1 }}>
                                                        <Box sx={{ fontSize: '0.875rem' }}>
                                                            {order.scheduled_datetime
                                                                ? formatDateTime(
                                                                      order.scheduled_datetime,
                                                                  )
                                                                : '-'}
                                                        </Box>
                                                    </Box>
                                                    <Box component="td" sx={{ p: 1 }}>
                                                        <Box sx={{ fontSize: '0.875rem' }}>
                                                            {order.delivery_method ===
                                                                'platform_rider' &&
                                                            order.rider ? (
                                                                <Box>
                                                                    <Box sx={{ fontWeight: 500, color: 'info.main' }}>
                                                                        {
                                                                            order
                                                                                .rider
                                                                                .name
                                                                        }
                                                                    </Box>
                                                                    <Box sx={{ fontSize: '0.75rem', color: 'text.secondary' }}>
                                                                        {
                                                                            order
                                                                                .rider
                                                                                .phone
                                                                        }
                                                                    </Box>
                                                                </Box>
                                                            ) : (
                                                                <Box component="span" sx={{ color: 'text.secondary' }}>
                                                                    {getDeliveryMethodLabel(
                                                                        order.delivery_method,
                                                                    )}
                                                                </Box>
                                                            )}
                                                        </Box>
                                                    </Box>
                                                    <Box component="td" sx={{ p: 1 }}>
                                                        <Box sx={{ display: 'flex', flexDirection: 'column', gap: 0.5 }}>
                                                            {getStatusBadge(
                                                                order.status,
                                                            )}
                                                            {order.payment_status && (
                                                                <Box component="span" sx={{ fontSize: '0.75rem', color: 'text.secondary' }}>
                                                                    Payment:{' '}
                                                                    {
                                                                        order.payment_status
                                                                    }
                                                                </Box>
                                                            )}
                                                        </Box>
                                                    </Box>
                                                </Box>
                                            ))}
                                        </tbody>
                                    </Box>
                                </Box>

                                {/* Pagination */}
                                {orders.last_page > 1 && (
                                    <Box sx={{ mt: 2, display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                                        <Box sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>
                                            Page {orders.current_page} of{' '}
                                            {orders.last_page}
                                        </Box>
                                        <Box sx={{ display: 'flex', gap: 1 }}>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() =>
                                                    handlePageChange(
                                                        orders.current_page - 1,
                                                    )
                                                }
                                                disabled={!orders.prev_page_url}
                                            >
                                                <ChevronLeft style={{ width: 16, height: 16 }} />
                                                Previous
                                            </Button>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() =>
                                                    handlePageChange(
                                                        orders.current_page + 1,
                                                    )
                                                }
                                                disabled={!orders.next_page_url}
                                            >
                                                Next
                                                <ChevronRight style={{ width: 16, height: 16 }} />
                                            </Button>
                                        </Box>
                                    </Box>
                                )}
                            </>
                        )}
                    </CardContent>
                </Card>
            </Box>
        </AppLayout>
    );
}

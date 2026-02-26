import { Badge } from '@/components/ui/badge';
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

const getStatusBadge = (status: string) => {
    const variants: Record<string, string> = {
        pending:
            'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
        confirmed:
            'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
        processing:
            'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
        fulfilled:
            'bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200',
        delivered:
            'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
        cancelled: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
        refunded:
            'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200',
        paid: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
        unpaid: 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200',
        approved:
            'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
        rejected: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
    };

    const color = variants[status] || variants.pending;

    return (
        <Badge className={color}>
            {status.charAt(0).toUpperCase() + status.slice(1)}
        </Badge>
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
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                {/* Statistics Cards */}
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Total Orders
                            </CardTitle>
                            <ShoppingCart className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {statistics.total_orders}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                All time orders
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Total Sales
                            </CardTitle>
                            <DollarSign className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {formatCurrency(statistics.total_sales)}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Gross revenue
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Pending Orders
                            </CardTitle>
                            <Package className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {statistics.pending_orders}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Awaiting processing
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Delivered Orders
                            </CardTitle>
                            <Truck className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {statistics.delivered_orders}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Successfully delivered
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {/* Filters */}
                <Card>
                    <CardHeader>
                        <CardTitle>Filters</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="flex flex-wrap gap-4">
                            <div className="w-[200px]">
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
                            </div>

                            <div className="w-[200px]">
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
                            </div>

                            <div className="w-[200px]">
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
                            </div>

                            <div className="w-[200px]">
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
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Orders Table */}
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div>
                                <CardTitle>All Transactions</CardTitle>
                                <CardDescription>
                                    Showing {orders.from || 0} to{' '}
                                    {orders.to || 0} of {orders.total} orders
                                </CardDescription>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        {orders.data.length === 0 ? (
                            <div className="py-8 text-center text-muted-foreground">
                                No transactions found.
                            </div>
                        ) : (
                            <>
                                <div className="overflow-x-auto">
                                    <table className="w-full">
                                        <thead>
                                            <tr className="border-b">
                                                <th className="p-2 text-left text-sm font-medium">
                                                    Order #
                                                </th>
                                                <th className="p-2 text-left text-sm font-medium">
                                                    Customer
                                                </th>
                                                <th className="p-2 text-left text-sm font-medium">
                                                    Product/Service
                                                </th>
                                                <th className="p-2 text-left text-sm font-medium">
                                                    Receiver
                                                </th>
                                                <th className="p-2 text-left text-sm font-medium">
                                                    Vendor
                                                </th>
                                                <th className="p-2 text-left text-sm font-medium">
                                                    Amount
                                                </th>
                                                <th className="p-2 text-left text-sm font-medium">
                                                    Delivery Date
                                                </th>
                                                <th className="p-2 text-left text-sm font-medium">
                                                    Delivered By
                                                </th>
                                                <th className="p-2 text-left text-sm font-medium">
                                                    Status
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {orders.data.map((order) => (
                                                <tr
                                                    key={order.id}
                                                    className="border-b last:border-0 hover:bg-muted/50"
                                                >
                                                    <td className="p-2">
                                                        <div className="text-sm font-medium">
                                                            {
                                                                order.transaction_number
                                                            }
                                                        </div>
                                                        <div className="text-xs text-muted-foreground">
                                                            {formatDateTime(
                                                                order.created_at,
                                                            )}
                                                        </div>
                                                    </td>
                                                    <td className="p-2">
                                                        <div className="flex items-center gap-2">
                                                            <User className="h-4 w-4 text-muted-foreground" />
                                                            <div>
                                                                <div className="text-sm font-medium">
                                                                    {
                                                                        order
                                                                            .customer
                                                                            .name
                                                                    }
                                                                </div>
                                                                {order.customer
                                                                    .phone && (
                                                                    <div className="text-xs text-muted-foreground">
                                                                        {
                                                                            order
                                                                                .customer
                                                                                .phone
                                                                        }
                                                                    </div>
                                                                )}
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td className="p-2">
                                                        <div className="text-sm">
                                                            {order.items
                                                                .length > 0 ? (
                                                                <div>
                                                                    <div className="font-medium">
                                                                        {
                                                                            order
                                                                                .items[0]
                                                                                .name
                                                                        }
                                                                    </div>
                                                                    {order.items
                                                                        .length >
                                                                        1 && (
                                                                        <div className="text-xs text-muted-foreground">
                                                                            +
                                                                            {order
                                                                                .items
                                                                                .length -
                                                                                1}{' '}
                                                                            more
                                                                            items
                                                                        </div>
                                                                    )}
                                                                </div>
                                                            ) : (
                                                                <span className="text-muted-foreground">
                                                                    -
                                                                </span>
                                                            )}
                                                        </div>
                                                    </td>
                                                    <td className="p-2">
                                                        {order.receiver ? (
                                                            <div>
                                                                <div className="text-sm font-medium">
                                                                    {
                                                                        order
                                                                            .receiver
                                                                            .name
                                                                    }
                                                                </div>
                                                                {order.receiver
                                                                    .phone && (
                                                                    <div className="text-xs text-muted-foreground">
                                                                        {
                                                                            order
                                                                                .receiver
                                                                                .phone
                                                                        }
                                                                    </div>
                                                                )}
                                                                {order.receiver
                                                                    .address && (
                                                                    <div className="max-w-[150px] truncate text-xs text-muted-foreground">
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
                                                                    </div>
                                                                )}
                                                            </div>
                                                        ) : (
                                                            <span className="text-muted-foreground">
                                                                -
                                                            </span>
                                                        )}
                                                    </td>
                                                    <td className="p-2">
                                                        {order.vendor ? (
                                                            <div>
                                                                <div className="text-sm font-medium">
                                                                    {
                                                                        order
                                                                            .vendor
                                                                            .name
                                                                    }
                                                                </div>
                                                                {order.vendor
                                                                    .phone && (
                                                                    <div className="text-xs text-muted-foreground">
                                                                        {
                                                                            order
                                                                                .vendor
                                                                                .phone
                                                                        }
                                                                    </div>
                                                                )}
                                                            </div>
                                                        ) : (
                                                            <span className="text-muted-foreground">
                                                                -
                                                            </span>
                                                        )}
                                                    </td>
                                                    <td className="p-2">
                                                        <div className="text-sm font-medium text-green-600">
                                                            {formatCurrency(
                                                                order.amount,
                                                            )}
                                                        </div>
                                                        <div className="text-xs text-muted-foreground">
                                                            Commission:{' '}
                                                            {formatCurrency(
                                                                order.platform_commission,
                                                            )}
                                                        </div>
                                                    </td>
                                                    <td className="p-2">
                                                        <div className="text-sm">
                                                            {order.scheduled_datetime
                                                                ? formatDateTime(
                                                                      order.scheduled_datetime,
                                                                  )
                                                                : '-'}
                                                        </div>
                                                    </td>
                                                    <td className="p-2">
                                                        <div className="text-sm">
                                                            {order.delivery_method ===
                                                                'platform_rider' &&
                                                            order.rider ? (
                                                                <div>
                                                                    <div className="font-medium text-blue-600">
                                                                        {
                                                                            order
                                                                                .rider
                                                                                .name
                                                                        }
                                                                    </div>
                                                                    <div className="text-xs text-muted-foreground">
                                                                        {
                                                                            order
                                                                                .rider
                                                                                .phone
                                                                        }
                                                                    </div>
                                                                </div>
                                                            ) : (
                                                                <span className="text-muted-foreground">
                                                                    {getDeliveryMethodLabel(
                                                                        order.delivery_method,
                                                                    )}
                                                                </span>
                                                            )}
                                                        </div>
                                                    </td>
                                                    <td className="p-2">
                                                        <div className="flex flex-col gap-1">
                                                            {getStatusBadge(
                                                                order.status,
                                                            )}
                                                            {order.payment_status && (
                                                                <span className="text-xs text-muted-foreground">
                                                                    Payment:{' '}
                                                                    {
                                                                        order.payment_status
                                                                    }
                                                                </span>
                                                            )}
                                                        </div>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>

                                {/* Pagination */}
                                {orders.last_page > 1 && (
                                    <div className="mt-4 flex items-center justify-between">
                                        <div className="text-sm text-muted-foreground">
                                            Page {orders.current_page} of{' '}
                                            {orders.last_page}
                                        </div>
                                        <div className="flex gap-2">
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
                                                <ChevronLeft className="h-4 w-4" />
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
                                                <ChevronRight className="h-4 w-4" />
                                            </Button>
                                        </div>
                                    </div>
                                )}
                            </>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}

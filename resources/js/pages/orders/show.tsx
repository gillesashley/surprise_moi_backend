import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useInactivityLock } from '@/hooks/use-inactivity-lock';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';
import {
    ArrowLeft,
    Calendar,
    CreditCard,
    MapPin,
    Package,
    ShoppingCart,
    Store,
    User as UserIcon,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';

interface OrderItem {
    id: number;
    name: string;
    thumbnail: string | null;
    quantity: number;
    unit_price: string;
    subtotal: string;
}

interface OrderData {
    id: number;
    order_number: string;
    status: string;
    payment_status: string;
    currency: string;
    subtotal: string;
    discount_amount: string;
    delivery_fee: string;
    total: string;
    platform_commission_amount: string;
    vendor_payout_amount: string;
    customer: { name: string; email: string; phone: string | null } | null;
    vendor: { name: string; email: string; phone: string | null } | null;
    items: OrderItem[];
    delivery_address: {
        address_line_1: string;
        city: string;
        state: string;
        postal_code: string;
        country: string;
    } | null;
    receiver_name: string | null;
    receiver_phone: string | null;
    special_instructions: string | null;
    occasion: string | null;
    payment: {
        reference: string;
        status: string;
        channel: string | null;
        amount: string;
        currency: string;
        paid_at: string | null;
    } | null;
    created_at: string | null;
    confirmed_at: string | null;
    fulfilled_at: string | null;
    shipped_at: string | null;
    delivered_at: string | null;
}

interface Props {
    order: OrderData;
    allowedTransitions: string[];
}

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

const statusActionLabels: Record<string, string> = {
    confirmed: 'Confirm Order',
    processing: 'Mark Processing',
    fulfilled: 'Mark Fulfilled',
    shipped: 'Mark Shipped',
    delivered: 'Mark Delivered',
    refunded: 'Issue Refund',
};

const formatStatus = (status: string): string => {
    return status.charAt(0).toUpperCase() + status.slice(1);
};

export default function OrderShow({ order, allowedTransitions }: Props) {
    useInactivityLock();
    const [confirmDialogOpen, setConfirmDialogOpen] = useState(false);
    const [pendingStatus, setPendingStatus] = useState<string | null>(null);
    const [isUpdating, setIsUpdating] = useState(false);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Orders', href: '/dashboard/orders' },
        { title: `Order #${order.order_number}`, href: `/dashboard/orders/${order.id}` },
    ];

    const handleStatusAction = (status: string) => {
        setPendingStatus(status);
        setConfirmDialogOpen(true);
    };

    const confirmStatusUpdate = () => {
        if (!pendingStatus) {
            return;
        }

        setIsUpdating(true);
        router.post(
            `/dashboard/orders/${order.id}/status`,
            { status: pendingStatus },
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success(`Order status updated to ${pendingStatus}.`);
                    setConfirmDialogOpen(false);
                    setPendingStatus(null);
                    setIsUpdating(false);
                },
                onError: () => {
                    toast.error('Failed to update order status.');
                    setIsUpdating(false);
                },
            },
        );
    };

    const statusColors = statusBadgeColors[order.status] || { bg: '#F3F4F6', text: '#374151' };
    const paymentColors = paymentBadgeColors[order.payment_status] || { bg: '#F3F4F6', text: '#374151' };

    const timelineEvents: { label: string; date: string | null }[] = [
        { label: 'Created', date: order.created_at },
        { label: 'Confirmed', date: order.confirmed_at },
        { label: 'Fulfilled', date: order.fulfilled_at },
        { label: 'Shipped', date: order.shipped_at },
        { label: 'Delivered', date: order.delivered_at },
    ].filter((event) => event.date !== null);

    const commissionAmount = parseFloat(order.platform_commission_amount);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Order #${order.order_number}`} />
            <Box sx={{ display: 'flex', flex: 1, flexDirection: 'column', gap: 2, p: 2, height: '100%' }}>
                {/* Header */}
                <Box sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', flexWrap: 'wrap', gap: 1 }}>
                    <Box sx={{ display: 'flex', alignItems: 'center', gap: 2 }}>
                        <Button asChild variant="ghost" size="sm">
                            <Link href="/dashboard/orders">
                                <ArrowLeft style={{ marginRight: 4, width: 16, height: 16 }} /> Back
                            </Link>
                        </Button>
                        <Typography sx={{ fontSize: '1.5rem', fontWeight: 700, fontFamily: 'monospace' }}>
                            #{order.order_number}
                        </Typography>
                        <Box
                            component="span"
                            sx={{
                                display: 'inline-block',
                                px: 1.5,
                                py: 0.5,
                                borderRadius: '9999px',
                                fontSize: '0.75rem',
                                fontWeight: 600,
                                bgcolor: statusColors.bg,
                                color: statusColors.text,
                            }}
                        >
                            {formatStatus(order.status)}
                        </Box>
                        <Box
                            component="span"
                            sx={{
                                display: 'inline-block',
                                px: 1.5,
                                py: 0.5,
                                borderRadius: '9999px',
                                fontSize: '0.75rem',
                                fontWeight: 600,
                                bgcolor: paymentColors.bg,
                                color: paymentColors.text,
                            }}
                        >
                            {formatStatus(order.payment_status)}
                        </Box>
                    </Box>
                    <Box sx={{ display: 'flex', gap: 1 }}>
                        {allowedTransitions.map((status) => (
                            <Button
                                key={status}
                                variant={status === 'refunded' ? 'destructive' : 'outline'}
                                size="sm"
                                onClick={() => handleStatusAction(status)}
                            >
                                {statusActionLabels[status] || formatStatus(status)}
                            </Button>
                        ))}
                    </Box>
                </Box>

                {/* Two-column grid */}
                <Box sx={{ display: 'grid', gap: 2, gridTemplateColumns: { xs: '1fr', lg: '3fr 2fr' } }}>
                    {/* Left column */}
                    <Box sx={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
                        {/* Order Items */}
                        <Card>
                            <CardHeader>
                                <CardTitle sx={{ display: 'flex', alignItems: 'center', gap: 1, fontSize: '1rem' }}>
                                    <ShoppingCart style={{ width: 16, height: 16 }} /> Order Items ({order.items.length})
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1.5 }}>
                                    {order.items.map((item) => (
                                        <Box
                                            key={item.id}
                                            sx={{
                                                display: 'flex',
                                                alignItems: 'center',
                                                gap: 1.5,
                                                p: 1,
                                                borderRadius: 1,
                                                border: 1,
                                                borderColor: 'divider',
                                            }}
                                        >
                                            {item.thumbnail ? (
                                                <Box
                                                    component="img"
                                                    src={item.thumbnail}
                                                    alt={item.name}
                                                    sx={{
                                                        width: 48,
                                                        height: 48,
                                                        borderRadius: 1,
                                                        objectFit: 'cover',
                                                        flexShrink: 0,
                                                    }}
                                                />
                                            ) : (
                                                <Box
                                                    sx={{
                                                        width: 48,
                                                        height: 48,
                                                        borderRadius: 1,
                                                        bgcolor: 'action.hover',
                                                        display: 'flex',
                                                        alignItems: 'center',
                                                        justifyContent: 'center',
                                                        flexShrink: 0,
                                                    }}
                                                >
                                                    <Package style={{ width: 20, height: 20, color: 'var(--muted-foreground)' }} />
                                                </Box>
                                            )}
                                            <Box sx={{ flex: 1, minWidth: 0 }}>
                                                <Typography sx={{ fontSize: '0.875rem', fontWeight: 500, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                                                    {item.name}
                                                </Typography>
                                                <Typography sx={{ fontSize: '0.75rem', color: 'text.secondary' }}>
                                                    {item.quantity} x GHS {parseFloat(item.unit_price).toFixed(2)}
                                                </Typography>
                                            </Box>
                                            <Typography sx={{ fontSize: '0.875rem', fontWeight: 500, flexShrink: 0 }}>
                                                GHS {parseFloat(item.subtotal).toFixed(2)}
                                            </Typography>
                                        </Box>
                                    ))}
                                </Box>
                            </CardContent>
                        </Card>

                        {/* Financial Summary */}
                        <Card>
                            <CardHeader>
                                <CardTitle sx={{ fontSize: '1rem' }}>Financial Summary</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
                                    <Box sx={{ display: 'flex', justifyContent: 'space-between', fontSize: '0.875rem' }}>
                                        <Typography sx={{ color: 'text.secondary' }}>Subtotal</Typography>
                                        <Typography>GHS {parseFloat(order.subtotal).toFixed(2)}</Typography>
                                    </Box>
                                    {parseFloat(order.discount_amount) > 0 && (
                                        <Box sx={{ display: 'flex', justifyContent: 'space-between', fontSize: '0.875rem' }}>
                                            <Typography sx={{ color: 'text.secondary' }}>Discount</Typography>
                                            <Typography sx={{ color: 'error.main' }}>-GHS {parseFloat(order.discount_amount).toFixed(2)}</Typography>
                                        </Box>
                                    )}
                                    <Box sx={{ display: 'flex', justifyContent: 'space-between', fontSize: '0.875rem' }}>
                                        <Typography sx={{ color: 'text.secondary' }}>Delivery Fee</Typography>
                                        <Typography>GHS {parseFloat(order.delivery_fee).toFixed(2)}</Typography>
                                    </Box>
                                    <Box sx={{ borderTop: 1, borderColor: 'divider', pt: 1, display: 'flex', justifyContent: 'space-between', fontSize: '0.875rem' }}>
                                        <Typography sx={{ fontWeight: 600 }}>Total</Typography>
                                        <Typography sx={{ fontWeight: 600 }}>GHS {parseFloat(order.total).toFixed(2)}</Typography>
                                    </Box>
                                    {commissionAmount > 0 && (
                                        <Box sx={{ borderTop: 1, borderColor: 'divider', pt: 1, display: 'flex', justifyContent: 'space-between', fontSize: '0.875rem' }}>
                                            <Typography sx={{ color: 'text.secondary' }}>Platform Commission</Typography>
                                            <Typography>GHS {commissionAmount.toFixed(2)}</Typography>
                                        </Box>
                                    )}
                                </Box>
                            </CardContent>
                        </Card>
                    </Box>

                    {/* Right column */}
                    <Box sx={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
                        {/* Customer */}
                        {order.customer && (
                            <Card>
                                <CardHeader>
                                    <CardTitle sx={{ display: 'flex', alignItems: 'center', gap: 1, fontSize: '1rem' }}>
                                        <UserIcon style={{ width: 16, height: 16 }} /> Customer
                                    </CardTitle>
                                </CardHeader>
                                <CardContent sx={{ display: 'flex', flexDirection: 'column', gap: 0.5 }}>
                                    <Typography sx={{ fontSize: '0.875rem', fontWeight: 500 }}>{order.customer.name}</Typography>
                                    <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>{order.customer.email}</Typography>
                                    {order.customer.phone && (
                                        <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>{order.customer.phone}</Typography>
                                    )}
                                </CardContent>
                            </Card>
                        )}

                        {/* Vendor */}
                        {order.vendor && (
                            <Card>
                                <CardHeader>
                                    <CardTitle sx={{ display: 'flex', alignItems: 'center', gap: 1, fontSize: '1rem' }}>
                                        <Store style={{ width: 16, height: 16 }} /> Vendor
                                    </CardTitle>
                                </CardHeader>
                                <CardContent sx={{ display: 'flex', flexDirection: 'column', gap: 0.5 }}>
                                    <Typography sx={{ fontSize: '0.875rem', fontWeight: 500 }}>{order.vendor.name}</Typography>
                                    <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>{order.vendor.email}</Typography>
                                </CardContent>
                            </Card>
                        )}

                        {/* Delivery Details */}
                        <Card>
                            <CardHeader>
                                <CardTitle sx={{ display: 'flex', alignItems: 'center', gap: 1, fontSize: '1rem' }}>
                                    <MapPin style={{ width: 16, height: 16 }} /> Delivery Details
                                </CardTitle>
                            </CardHeader>
                            <CardContent sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
                                {order.receiver_name && (
                                    <Box>
                                        <Typography sx={{ fontSize: '0.75rem', color: 'text.secondary', fontWeight: 500 }}>Receiver</Typography>
                                        <Typography sx={{ fontSize: '0.875rem' }}>{order.receiver_name}</Typography>
                                    </Box>
                                )}
                                {order.receiver_phone && (
                                    <Box>
                                        <Typography sx={{ fontSize: '0.75rem', color: 'text.secondary', fontWeight: 500 }}>Phone</Typography>
                                        <Typography sx={{ fontSize: '0.875rem' }}>{order.receiver_phone}</Typography>
                                    </Box>
                                )}
                                {order.delivery_address && (
                                    <Box>
                                        <Typography sx={{ fontSize: '0.75rem', color: 'text.secondary', fontWeight: 500 }}>Address</Typography>
                                        <Typography sx={{ fontSize: '0.875rem' }}>
                                            {[
                                                order.delivery_address.address_line_1,
                                                order.delivery_address.city,
                                                order.delivery_address.state,
                                                order.delivery_address.postal_code,
                                                order.delivery_address.country,
                                            ].filter(Boolean).join(', ')}
                                        </Typography>
                                    </Box>
                                )}
                                {order.special_instructions && (
                                    <Box>
                                        <Typography sx={{ fontSize: '0.75rem', color: 'text.secondary', fontWeight: 500 }}>Special Instructions</Typography>
                                        <Typography sx={{ fontSize: '0.875rem' }}>{order.special_instructions}</Typography>
                                    </Box>
                                )}
                                {order.occasion && (
                                    <Box>
                                        <Typography sx={{ fontSize: '0.75rem', color: 'text.secondary', fontWeight: 500 }}>Occasion</Typography>
                                        <Typography sx={{ fontSize: '0.875rem' }}>{formatStatus(order.occasion)}</Typography>
                                    </Box>
                                )}
                            </CardContent>
                        </Card>

                        {/* Payment */}
                        {order.payment && (
                            <Card>
                                <CardHeader>
                                    <CardTitle sx={{ display: 'flex', alignItems: 'center', gap: 1, fontSize: '1rem' }}>
                                        <CreditCard style={{ width: 16, height: 16 }} /> Payment
                                    </CardTitle>
                                </CardHeader>
                                <CardContent sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
                                    <Box>
                                        <Typography sx={{ fontSize: '0.75rem', color: 'text.secondary', fontWeight: 500 }}>Reference</Typography>
                                        <Typography sx={{ fontSize: '0.875rem', fontFamily: 'monospace' }}>{order.payment.reference}</Typography>
                                    </Box>
                                    <Box>
                                        <Typography sx={{ fontSize: '0.75rem', color: 'text.secondary', fontWeight: 500 }}>Status</Typography>
                                        <Typography sx={{ fontSize: '0.875rem' }}>{formatStatus(order.payment.status)}</Typography>
                                    </Box>
                                    {order.payment.channel && (
                                        <Box>
                                            <Typography sx={{ fontSize: '0.75rem', color: 'text.secondary', fontWeight: 500 }}>Channel</Typography>
                                            <Typography sx={{ fontSize: '0.875rem' }}>{order.payment.channel}</Typography>
                                        </Box>
                                    )}
                                    <Box>
                                        <Typography sx={{ fontSize: '0.75rem', color: 'text.secondary', fontWeight: 500 }}>Amount</Typography>
                                        <Typography sx={{ fontSize: '0.875rem' }}>
                                            {order.payment.currency} {parseFloat(order.payment.amount).toFixed(2)}
                                        </Typography>
                                    </Box>
                                    {order.payment.paid_at && (
                                        <Box>
                                            <Typography sx={{ fontSize: '0.75rem', color: 'text.secondary', fontWeight: 500 }}>Paid At</Typography>
                                            <Typography sx={{ fontSize: '0.875rem' }}>
                                                {new Date(order.payment.paid_at).toLocaleString()}
                                            </Typography>
                                        </Box>
                                    )}
                                </CardContent>
                            </Card>
                        )}

                        {/* Timeline */}
                        {timelineEvents.length > 0 && (
                            <Card>
                                <CardHeader>
                                    <CardTitle sx={{ display: 'flex', alignItems: 'center', gap: 1, fontSize: '1rem' }}>
                                        <Calendar style={{ width: 16, height: 16 }} /> Timeline
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1.5 }}>
                                        {timelineEvents.map((event) => (
                                            <Box key={event.label} sx={{ display: 'flex', alignItems: 'center', gap: 1.5 }}>
                                                <Box
                                                    sx={{
                                                        width: 8,
                                                        height: 8,
                                                        borderRadius: '50%',
                                                        bgcolor: 'primary.main',
                                                        flexShrink: 0,
                                                    }}
                                                />
                                                <Box sx={{ flex: 1 }}>
                                                    <Typography sx={{ fontSize: '0.875rem', fontWeight: 500 }}>
                                                        {event.label}
                                                    </Typography>
                                                    <Typography sx={{ fontSize: '0.75rem', color: 'text.secondary' }}>
                                                        {new Date(event.date!).toLocaleString()}
                                                    </Typography>
                                                </Box>
                                            </Box>
                                        ))}
                                    </Box>
                                </CardContent>
                            </Card>
                        )}
                    </Box>
                </Box>

                {/* Status Update Confirmation Dialog */}
                <Dialog open={confirmDialogOpen} onOpenChange={(open) => {
                    if (!open) {
                        setConfirmDialogOpen(false);
                        setPendingStatus(null);
                    }
                }}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Confirm Status Update</DialogTitle>
                            <DialogDescription>
                                Are you sure you want to update the order status from{' '}
                                <strong>{formatStatus(order.status)}</strong> to{' '}
                                <strong>{pendingStatus ? formatStatus(pendingStatus) : ''}</strong>?
                                This action cannot be undone.
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter>
                            <Button
                                variant="outline"
                                onClick={() => {
                                    setConfirmDialogOpen(false);
                                    setPendingStatus(null);
                                }}
                                disabled={isUpdating}
                            >
                                Cancel
                            </Button>
                            <Button
                                variant={pendingStatus === 'refunded' ? 'destructive' : 'default'}
                                onClick={confirmStatusUpdate}
                                disabled={isUpdating}
                            >
                                {isUpdating ? 'Updating...' : 'Confirm'}
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </Box>
        </AppLayout>
    );
}

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
import axios from 'axios';
import {
    AlertTriangle,
    ArrowLeft,
    CheckCircle,
    CreditCard,
    Globe,
    RefreshCw,
    Shield,
    Smartphone,
    User as UserIcon,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';

interface PaymentData {
    id: number;
    type: 'order' | 'vendor_onboarding';
    reference: string;
    paystack_reference: string | null;
    amount: string;
    currency: string;
    status: string;
    channel: string | null;
    payment_method_type: string | null;
    card_last4: string | null;
    card_type: string | null;
    card_exp_month: string | null;
    card_exp_year: string | null;
    card_bank: string | null;
    mobile_money_number: string | null;
    mobile_money_provider: string | null;
    gateway_response: string | null;
    failure_reason: string | null;
    ip_address: string | null;
    metadata: any;
    paid_at: string | null;
    verified_at: string | null;
    created_at: string;
    user: { id: number; name: string; email: string; phone: string | null } | null;
    related: {
        order_number?: string;
        order_status?: string;
        order_id?: number;
        application_id?: number;
        application_status?: string;
        current_step?: number;
        completed_step?: number;
    } | null;
}

interface Props {
    payment: PaymentData;
}

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

export default function PaymentShow({ payment }: Props) {
    useInactivityLock();

    const [verifying, setVerifying] = useState(false);
    const [paystackData, setPaystackData] = useState<any>(null);
    const [verifyError, setVerifyError] = useState<string | null>(null);
    const [showSyncDialog, setShowSyncDialog] = useState(false);
    const [syncing, setSyncing] = useState(false);
    const [showMetadata, setShowMetadata] = useState(false);
    const [showPaystackResponse, setShowPaystackResponse] = useState(false);

    const typeSlug = payment.type === 'order' ? 'order' : 'vendor-onboarding';

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Payments', href: '/dashboard/payments' },
        { title: payment.reference, href: '#' },
    ];

    const statusColors = statusBadgeColors[payment.status] || { bg: '#F3F4F6', text: '#374151' };

    const handleVerify = async () => {
        setVerifying(true);
        setVerifyError(null);
        setPaystackData(null);

        try {
            const response = await axios.post(`/dashboard/payments/${typeSlug}/${payment.id}/verify`);
            setPaystackData(response.data);
        } catch (error: any) {
            setVerifyError(error.response?.data?.message ?? 'Could not reach Paystack. Try again later.');
        } finally {
            setVerifying(false);
        }
    };

    const handleSync = () => {
        setSyncing(true);
        router.post(
            `/dashboard/payments/${typeSlug}/${payment.id}/sync`,
            {},
            {
                onSuccess: () => {
                    toast.success('Payment synced successfully');
                    setShowSyncDialog(false);
                    setPaystackData(null);
                },
                onError: () => toast.error('Failed to sync payment'),
                onFinish: () => setSyncing(false),
            },
        );
    };

    const renderChannelDetails = () => {
        if (payment.channel === 'mobile_money') {
            return (
                <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                    <Smartphone style={{ width: 16, height: 16, color: '#6B7280' }} />
                    <Typography sx={{ fontSize: '0.875rem' }}>
                        Mobile Money — {payment.mobile_money_provider}, {payment.mobile_money_number}
                    </Typography>
                </Box>
            );
        }

        if (payment.channel === 'card') {
            return (
                <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                    <CreditCard style={{ width: 16, height: 16, color: '#6B7280' }} />
                    <Typography sx={{ fontSize: '0.875rem' }}>
                        Card — {payment.card_type} ****{payment.card_last4}, {payment.card_bank}
                    </Typography>
                </Box>
            );
        }

        return (
            <Typography sx={{ fontSize: '0.875rem' }}>
                {payment.channel || '\u2014'}
            </Typography>
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Payment ${payment.reference}`} />
            <Box sx={{ display: 'flex', height: '100%', flex: 1, flexDirection: 'column', gap: 2, p: 2 }}>
                <Box
                    sx={{
                        display: 'grid',
                        gridTemplateColumns: { xs: '1fr', md: '2fr 1fr' },
                        gap: 2,
                    }}
                >
                    {/* Left Column */}
                    <Box sx={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
                        {/* Card 1: Payment Overview */}
                        <Card>
                            <CardHeader>
                                <Box sx={{ display: 'flex', alignItems: 'center', gap: 1, mb: 1 }}>
                                    <Link href="/dashboard/payments">
                                        <Button variant="ghost" size="sm">
                                            <ArrowLeft style={{ width: 16, height: 16, marginRight: 4 }} />
                                            Back
                                        </Button>
                                    </Link>
                                </Box>
                                <CardTitle>Payment Overview</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <Box sx={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
                                    {/* Reference & Status */}
                                    <Box sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', flexWrap: 'wrap', gap: 1 }}>
                                        <Typography sx={{ fontFamily: 'monospace', fontSize: '0.875rem', fontWeight: 500 }}>
                                            {payment.reference}
                                        </Typography>
                                        <Box
                                            component="span"
                                            sx={{
                                                display: 'inline-block',
                                                px: 1.5,
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

                                    {/* Amount */}
                                    <Box>
                                        <Typography sx={{ fontSize: '0.75rem', color: 'text.secondary', mb: 0.5 }}>Amount</Typography>
                                        <Typography sx={{ fontSize: '1.25rem', fontWeight: 600 }}>
                                            {payment.currency} {payment.amount}
                                        </Typography>
                                    </Box>

                                    {/* Dates */}
                                    <Box sx={{ display: 'flex', gap: 4, flexWrap: 'wrap' }}>
                                        <Box>
                                            <Typography sx={{ fontSize: '0.75rem', color: 'text.secondary', mb: 0.5 }}>Created At</Typography>
                                            <Typography sx={{ fontSize: '0.875rem' }}>
                                                {new Date(payment.created_at).toLocaleString()}
                                            </Typography>
                                        </Box>
                                        {payment.paid_at && (
                                            <Box>
                                                <Typography sx={{ fontSize: '0.75rem', color: 'text.secondary', mb: 0.5 }}>Paid At</Typography>
                                                <Typography sx={{ fontSize: '0.875rem' }}>
                                                    {new Date(payment.paid_at).toLocaleString()}
                                                </Typography>
                                            </Box>
                                        )}
                                    </Box>

                                    {/* Channel & Method */}
                                    <Box>
                                        <Typography sx={{ fontSize: '0.75rem', color: 'text.secondary', mb: 0.5 }}>Payment Method</Typography>
                                        {renderChannelDetails()}
                                    </Box>
                                </Box>
                            </CardContent>
                        </Card>

                        {/* Card 3: Technical Details */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Technical Details</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <Box sx={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
                                    <Box>
                                        <Typography sx={{ fontSize: '0.75rem', color: 'text.secondary', mb: 0.5 }}>Gateway Response</Typography>
                                        <Typography sx={{ fontSize: '0.875rem' }}>
                                            {payment.gateway_response || '\u2014'}
                                        </Typography>
                                    </Box>

                                    {payment.failure_reason && (
                                        <Box>
                                            <Typography sx={{ fontSize: '0.75rem', color: 'text.secondary', mb: 0.5 }}>Failure Reason</Typography>
                                            <Typography sx={{ fontSize: '0.875rem', color: '#991B1B' }}>
                                                {payment.failure_reason}
                                            </Typography>
                                        </Box>
                                    )}

                                    <Box>
                                        <Typography sx={{ fontSize: '0.75rem', color: 'text.secondary', mb: 0.5 }}>IP Address</Typography>
                                        <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                                            <Globe style={{ width: 14, height: 14, color: '#6B7280' }} />
                                            <Typography sx={{ fontSize: '0.875rem' }}>
                                                {payment.ip_address || '\u2014'}
                                            </Typography>
                                        </Box>
                                    </Box>

                                    <Box>
                                        <Typography sx={{ fontSize: '0.75rem', color: 'text.secondary', mb: 0.5 }}>Paystack Reference</Typography>
                                        <Typography sx={{ fontSize: '0.875rem', fontFamily: 'monospace' }}>
                                            {payment.paystack_reference || '\u2014'}
                                        </Typography>
                                    </Box>

                                    {/* Metadata */}
                                    <Box>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() => setShowMetadata(!showMetadata)}
                                        >
                                            {showMetadata ? 'Hide Metadata' : 'Show Metadata'}
                                        </Button>
                                        {showMetadata && (
                                            <Box
                                                sx={{
                                                    mt: 1,
                                                    maxHeight: 300,
                                                    overflow: 'auto',
                                                    bgcolor: '#F3F4F6',
                                                    borderRadius: 1,
                                                    p: 2,
                                                }}
                                            >
                                                <pre style={{ margin: 0, fontFamily: 'monospace', fontSize: '0.8rem', whiteSpace: 'pre-wrap' }}>
                                                    {JSON.stringify(payment.metadata, null, 2)}
                                                </pre>
                                            </Box>
                                        )}
                                    </Box>
                                </Box>
                            </CardContent>
                        </Card>

                        {/* Card 4: Paystack Verification */}
                        <Card>
                            <CardHeader>
                                <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                                    <Shield style={{ width: 20, height: 20, color: '#6B7280' }} />
                                    <CardTitle>Paystack Verification</CardTitle>
                                </Box>
                            </CardHeader>
                            <CardContent>
                                <Box sx={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
                                    {/* Verify Button */}
                                    {!paystackData && !verifyError && (
                                        <Button
                                            onClick={handleVerify}
                                            disabled={verifying}
                                        >
                                            <RefreshCw
                                                style={{
                                                    width: 16,
                                                    height: 16,
                                                    marginRight: 8,
                                                    animation: verifying ? 'spin 1s linear infinite' : 'none',
                                                }}
                                            />
                                            {verifying ? 'Verifying...' : 'Verify on Paystack'}
                                        </Button>
                                    )}

                                    {/* Error Display */}
                                    {verifyError && (
                                        <Box>
                                            <Box
                                                sx={{
                                                    display: 'flex',
                                                    alignItems: 'center',
                                                    gap: 1,
                                                    bgcolor: '#FEE2E2',
                                                    color: '#991B1B',
                                                    px: 2,
                                                    py: 1.5,
                                                    borderRadius: 1,
                                                }}
                                            >
                                                <AlertTriangle style={{ width: 16, height: 16, flexShrink: 0 }} />
                                                <Typography sx={{ fontSize: '0.875rem', flex: 1 }}>
                                                    {verifyError}
                                                </Typography>
                                            </Box>
                                            <Box sx={{ mt: 1 }}>
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() => {
                                                        setVerifyError(null);
                                                        handleVerify();
                                                    }}
                                                >
                                                    Try Again
                                                </Button>
                                            </Box>
                                        </Box>
                                    )}

                                    {/* Paystack Data Results */}
                                    {paystackData && (
                                        <Box sx={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
                                            {/* Status Mismatch or Match */}
                                            {paystackData.status_mismatch ? (
                                                <Box>
                                                    <Box
                                                        sx={{
                                                            display: 'flex',
                                                            alignItems: 'center',
                                                            gap: 1,
                                                            bgcolor: '#FEF3C7',
                                                            color: '#92400E',
                                                            px: 2,
                                                            py: 1.5,
                                                            borderRadius: 1,
                                                        }}
                                                    >
                                                        <AlertTriangle style={{ width: 16, height: 16, flexShrink: 0 }} />
                                                        <Typography sx={{ fontSize: '0.875rem' }}>
                                                            Status Mismatch Detected — Paystack reports <strong>{paystackData.paystack_status}</strong> but local status is <strong>{paystackData.local_status}</strong>
                                                        </Typography>
                                                    </Box>
                                                    <Box sx={{ mt: 1 }}>
                                                        <Button
                                                            variant="outline"
                                                            size="sm"
                                                            onClick={() => setShowSyncDialog(true)}
                                                        >
                                                            Sync Local Records
                                                        </Button>
                                                    </Box>
                                                </Box>
                                            ) : (
                                                <Box
                                                    sx={{
                                                        display: 'flex',
                                                        alignItems: 'center',
                                                        gap: 1,
                                                        bgcolor: '#D1FAE5',
                                                        color: '#065F46',
                                                        px: 2,
                                                        py: 1.5,
                                                        borderRadius: 1,
                                                    }}
                                                >
                                                    <CheckCircle style={{ width: 16, height: 16, flexShrink: 0 }} />
                                                    <Typography sx={{ fontSize: '0.875rem' }}>
                                                        Paystack status matches local status: <strong>{paystackData.local_status}</strong>
                                                    </Typography>
                                                </Box>
                                            )}

                                            {/* Paystack Response Data */}
                                            <Box>
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() => setShowPaystackResponse(!showPaystackResponse)}
                                                >
                                                    {showPaystackResponse ? 'Hide Paystack Response' : 'Show Paystack Response'}
                                                </Button>
                                                {showPaystackResponse && (
                                                    <Box
                                                        sx={{
                                                            mt: 1,
                                                            maxHeight: 300,
                                                            overflow: 'auto',
                                                            bgcolor: '#F3F4F6',
                                                            borderRadius: 1,
                                                            p: 2,
                                                        }}
                                                    >
                                                        <pre style={{ margin: 0, fontFamily: 'monospace', fontSize: '0.8rem', whiteSpace: 'pre-wrap' }}>
                                                            {JSON.stringify(paystackData, null, 2)}
                                                        </pre>
                                                    </Box>
                                                )}
                                            </Box>
                                        </Box>
                                    )}
                                </Box>
                            </CardContent>
                        </Card>
                    </Box>

                    {/* Right Column */}
                    <Box sx={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
                        {/* Card 2: User & Related Entity */}
                        <Card>
                            <CardHeader>
                                <CardTitle>User & Related Entity</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <Box sx={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
                                    {/* User Info */}
                                    <Box>
                                        <Box sx={{ display: 'flex', alignItems: 'center', gap: 1, mb: 1 }}>
                                            <UserIcon style={{ width: 16, height: 16, color: '#6B7280' }} />
                                            <Typography sx={{ fontSize: '0.75rem', color: 'text.secondary', fontWeight: 500 }}>User</Typography>
                                        </Box>
                                        {payment.user ? (
                                            <Box sx={{ display: 'flex', flexDirection: 'column', gap: 0.5, pl: 3 }}>
                                                <Typography sx={{ fontSize: '0.875rem', fontWeight: 500 }}>
                                                    {payment.user.name}
                                                </Typography>
                                                <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>
                                                    {payment.user.email}
                                                </Typography>
                                                <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>
                                                    {payment.user.phone || '\u2014'}
                                                </Typography>
                                            </Box>
                                        ) : (
                                            <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary', pl: 3 }}>
                                                No user associated
                                            </Typography>
                                        )}
                                    </Box>

                                    {/* Related Entity */}
                                    <Box>
                                        <Typography sx={{ fontSize: '0.75rem', color: 'text.secondary', fontWeight: 500, mb: 1 }}>
                                            Related Entity
                                        </Typography>
                                        {payment.type === 'order' && payment.related ? (
                                            <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
                                                <Link
                                                    href={`/dashboard/orders/${payment.related.order_id}`}
                                                    style={{ fontSize: '0.875rem', color: '#1E40AF', textDecoration: 'underline' }}
                                                >
                                                    Order #{payment.related.order_number}
                                                </Link>
                                                {payment.related.order_status && (
                                                    <Box
                                                        component="span"
                                                        sx={{
                                                            display: 'inline-block',
                                                            px: 1,
                                                            py: 0.25,
                                                            borderRadius: '9999px',
                                                            fontSize: '0.75rem',
                                                            fontWeight: 500,
                                                            bgcolor: (statusBadgeColors[payment.related.order_status] || { bg: '#F3F4F6' }).bg,
                                                            color: (statusBadgeColors[payment.related.order_status] || { text: '#374151' }).text,
                                                            width: 'fit-content',
                                                        }}
                                                    >
                                                        {formatStatus(payment.related.order_status)}
                                                    </Box>
                                                )}
                                            </Box>
                                        ) : payment.type === 'vendor_onboarding' && payment.related ? (
                                            <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
                                                <Link
                                                    href={`/dashboard/vendor-applications/${payment.related.application_id}`}
                                                    style={{ fontSize: '0.875rem', color: '#1E40AF', textDecoration: 'underline' }}
                                                >
                                                    Application #{payment.related.application_id}
                                                </Link>
                                                {payment.related.application_status && (
                                                    <Box
                                                        component="span"
                                                        sx={{
                                                            display: 'inline-block',
                                                            px: 1,
                                                            py: 0.25,
                                                            borderRadius: '9999px',
                                                            fontSize: '0.75rem',
                                                            fontWeight: 500,
                                                            bgcolor: (statusBadgeColors[payment.related.application_status] || { bg: '#F3F4F6' }).bg,
                                                            color: (statusBadgeColors[payment.related.application_status] || { text: '#374151' }).text,
                                                            width: 'fit-content',
                                                        }}
                                                    >
                                                        {formatStatus(payment.related.application_status)}
                                                    </Box>
                                                )}
                                                {payment.related.current_step !== undefined && payment.related.completed_step !== undefined && (
                                                    <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>
                                                        Steps: {payment.related.completed_step} / {payment.related.current_step} completed
                                                    </Typography>
                                                )}
                                            </Box>
                                        ) : (
                                            <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>
                                                No related entity
                                            </Typography>
                                        )}
                                    </Box>
                                </Box>
                            </CardContent>
                        </Card>
                    </Box>
                </Box>
            </Box>

            {/* Sync Confirmation Dialog */}
            <Dialog open={showSyncDialog} onOpenChange={setShowSyncDialog}>
                <DialogContent aria-describedby={undefined}>
                    <DialogHeader>
                        <DialogTitle>Sync Local Records</DialogTitle>
                        <DialogDescription>
                            This will update the local payment status and related records to match Paystack. The existing verification logic will run, which may trigger notifications.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setShowSyncDialog(false)} disabled={syncing}>
                            Cancel
                        </Button>
                        <Button onClick={handleSync} disabled={syncing}>
                            {syncing ? 'Syncing...' : 'Confirm Sync'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}

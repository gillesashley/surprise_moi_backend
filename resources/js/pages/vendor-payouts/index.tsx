import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
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
import { Head, router } from '@inertiajs/react';
import Box from '@mui/material/Box';
import Chip from '@mui/material/Chip';
import Typography from '@mui/material/Typography';
import {
    AlertCircleIcon,
    CheckCircle,
    ChevronRight,
    Filter,
    RefreshCw,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';

interface PayoutRequest {
    id: number;
    request_number: string;
    user_id: number;
    user_name: string;
    user_email: string;
    user_phone: string;
    user_role: string;
    amount: string;
    currency: string;
    status: 'pending' | 'approved' | 'paid' | 'rejected';
    payout_method: string;
    mobile_money_number: string;
    mobile_money_provider: string;
    created_at: string;
    processed_by_name: string | null;
    processed_at: string | null;
    vendor_payout_details: VendorPayoutDetail[];
}

interface VendorPayoutDetail {
    id: number;
    payout_method: 'mobile_money' | 'bank_transfer';
    account_name: string;
    account_number: string;
    bank_code: string;
    bank_name: string;
    provider: string | null;
    is_verified: boolean;
    is_default: boolean;
}

interface ApiResponse {
    success: boolean;
    payouts: {
        current_page: number;
        data: PayoutRequest[];
        total: number;
        per_page: number;
    };
    statistics: {
        total_pending: number;
        total_approved: number;
        total_paid: number;
        total_rejected: number;
        pending_amount: string;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'Vendor Payouts',
        href: '/dashboard/vendor-payouts',
    },
];

function getStatusBadge(status: string) {
    switch (status) {
        case 'pending':
            return (
                <Chip
                    label={
                        <Box sx={{ display: 'flex', alignItems: 'center' }}>
                            <AlertCircleIcon style={{ marginRight: 4, width: 12, height: 12 }} />
                            Pending
                        </Box>
                    }
                    color="warning"
                    size="small"
                    variant="outlined"
                />
            );
        case 'approved':
            return (
                <Chip
                    label={
                        <Box sx={{ display: 'flex', alignItems: 'center' }}>
                            <CheckCircle style={{ marginRight: 4, width: 12, height: 12 }} />
                            Approved
                        </Box>
                    }
                    color="info"
                    size="small"
                    variant="outlined"
                />
            );
        case 'paid':
            return (
                <Chip
                    label={
                        <Box sx={{ display: 'flex', alignItems: 'center' }}>
                            <CheckCircle style={{ marginRight: 4, width: 12, height: 12 }} />
                            Paid
                        </Box>
                    }
                    color="success"
                    size="small"
                    variant="outlined"
                />
            );
        case 'rejected':
            return (
                <Chip
                    label={
                        <Box sx={{ display: 'flex', alignItems: 'center' }}>
                            <XCircle style={{ marginRight: 4, width: 12, height: 12 }} />
                            Rejected
                        </Box>
                    }
                    color="error"
                    size="small"
                    variant="outlined"
                />
            );
        default:
            return <Badge>{status}</Badge>;
    }
}

export default function VendorPayouts({
    initialData,
}: {
    initialData: ApiResponse;
}) {
    const [data, setData] = useState<ApiResponse>(initialData);
    const [statusFilter, setStatusFilter] = useState<string>('pending');
    const [selectedPayout, setSelectedPayout] = useState<PayoutRequest | null>(
        null,
    );
    const [showApproveDialog, setShowApproveDialog] = useState(false);
    const [showMarkPaidDialog, setShowMarkPaidDialog] = useState(false);
    const [showRejectDialog, setShowRejectDialog] = useState(false);
    const [approvalNotes, setApprovalNotes] = useState('');
    const [paymentReference, setPaymentReference] = useState('');
    const [rejectionReason, setRejectionReason] = useState('');
    const [loading, setLoading] = useState(false);

    const handleFilterChange = (status: string) => {
        setStatusFilter(status);
        setLoading(true);
        router.get(
            `/dashboard/vendor-payouts`,
            { status },
            {
                preserveScroll: true,
                onFinish: () => setLoading(false),
            },
        );
    };

    const handleApprovePayout = async () => {
        if (!selectedPayout) return;

        setLoading(true);
        try {
            const response = await fetch(
                `/api/v1/admin/payouts/${selectedPayout.id}/approve`,
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                    },
                    body: JSON.stringify({
                        admin_notes: approvalNotes,
                    }),
                },
            );

            const result = await response.json();

            if (result.success) {
                setShowApproveDialog(false);
                setApprovalNotes('');
                setSelectedPayout(null);
                // Refresh data
                router.get('/dashboard/vendor-payouts', {
                    status: statusFilter,
                });
            } else {
                alert(
                    'Error: ' + (result.message || 'Failed to approve payout'),
                );
            }
        } catch (error) {
            alert('Error: ' + String(error));
        } finally {
            setLoading(false);
        }
    };

    const handleMarkAsPaid = async () => {
        if (!selectedPayout) return;

        if (!paymentReference.trim()) {
            alert('Please enter payment reference');
            return;
        }

        setLoading(true);
        try {
            const response = await fetch(
                `/api/v1/admin/payouts/${selectedPayout.id}/mark-paid`,
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                    },
                    body: JSON.stringify({
                        payment_reference: paymentReference,
                    }),
                },
            );

            const result = await response.json();

            if (result.success) {
                setShowMarkPaidDialog(false);
                setPaymentReference('');
                setSelectedPayout(null);
                // Refresh data
                router.get('/dashboard/vendor-payouts', {
                    status: statusFilter,
                });
            } else {
                alert('Error: ' + (result.message || 'Failed to mark as paid'));
            }
        } catch (error) {
            alert('Error: ' + String(error));
        } finally {
            setLoading(false);
        }
    };

    const handleRejectPayout = async () => {
        if (!selectedPayout) return;

        if (!rejectionReason.trim()) {
            alert('Please provide rejection reason');
            return;
        }

        setLoading(true);
        try {
            const response = await fetch(
                `/api/v1/admin/payouts/${selectedPayout.id}/reject`,
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                    },
                    body: JSON.stringify({
                        rejection_reason: rejectionReason,
                    }),
                },
            );

            const result = await response.json();

            if (result.success) {
                setShowRejectDialog(false);
                setRejectionReason('');
                setSelectedPayout(null);
                // Refresh data
                router.get('/dashboard/vendor-payouts', {
                    status: statusFilter,
                });
            } else {
                alert(
                    'Error: ' + (result.message || 'Failed to reject payout'),
                );
            }
        } catch (error) {
            alert('Error: ' + String(error));
        } finally {
            setLoading(false);
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Vendor Payouts" />

            <Box sx={{ display: 'flex', flexDirection: 'column', gap: 3, p: 3 }}>
                {/* Statistics Cards */}
                <Box sx={{ display: 'grid', gap: 2, gridTemplateColumns: { md: 'repeat(4, 1fr)' } }}>
                    <Card>
                        <CardHeader style={{ display: 'flex', flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingBottom: 8 }}>
                            <CardTitle style={{ fontSize: '0.875rem', fontWeight: 500 }}>
                                Pending
                            </CardTitle>
                            <AlertCircleIcon style={{ width: 16, height: 16, color: 'var(--color-orange-600)' }} />
                        </CardHeader>
                        <CardContent>
                            <Box sx={{ fontSize: '1.5rem', fontWeight: 700 }}>
                                {data.statistics.total_pending}
                            </Box>
                            <Typography sx={{ fontSize: '0.75rem', color: 'text.secondary' }}>
                                GHS {data.statistics.pending_amount}
                            </Typography>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader style={{ display: 'flex', flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingBottom: 8 }}>
                            <CardTitle style={{ fontSize: '0.875rem', fontWeight: 500 }}>
                                Approved
                            </CardTitle>
                            <CheckCircle style={{ width: 16, height: 16, color: 'var(--color-blue-600)' }} />
                        </CardHeader>
                        <CardContent>
                            <Box sx={{ fontSize: '1.5rem', fontWeight: 700 }}>
                                {data.statistics.total_approved}
                            </Box>
                            <Typography sx={{ fontSize: '0.75rem', color: 'text.secondary' }}>
                                Awaiting payment
                            </Typography>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader style={{ display: 'flex', flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingBottom: 8 }}>
                            <CardTitle style={{ fontSize: '0.875rem', fontWeight: 500 }}>
                                Paid
                            </CardTitle>
                            <CheckCircle style={{ width: 16, height: 16, color: 'var(--color-green-600)' }} />
                        </CardHeader>
                        <CardContent>
                            <Box sx={{ fontSize: '1.5rem', fontWeight: 700 }}>
                                {data.statistics.total_paid}
                            </Box>
                            <Typography sx={{ fontSize: '0.75rem', color: 'text.secondary' }}>
                                Successfully paid
                            </Typography>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader style={{ display: 'flex', flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingBottom: 8 }}>
                            <CardTitle style={{ fontSize: '0.875rem', fontWeight: 500 }}>
                                Rejected
                            </CardTitle>
                            <XCircle style={{ width: 16, height: 16, color: 'var(--color-red-600)' }} />
                        </CardHeader>
                        <CardContent>
                            <Box sx={{ fontSize: '1.5rem', fontWeight: 700 }}>
                                {data.statistics.total_rejected}
                            </Box>
                            <Typography sx={{ fontSize: '0.75rem', color: 'text.secondary' }}>
                                Requests rejected
                            </Typography>
                        </CardContent>
                    </Card>
                </Box>

                {/* Main Content */}
                <Card>
                    <CardHeader>
                        <Box sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                            <Box>
                                <CardTitle>Payout Requests</CardTitle>
                                <CardDescription>
                                    Manage vendor payout requests
                                </CardDescription>
                            </Box>
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() =>
                                    router.get('/dashboard/vendor-payouts')
                                }
                            >
                                <RefreshCw style={{ marginRight: 8, width: 16, height: 16 }} />
                                Refresh
                            </Button>
                        </Box>
                    </CardHeader>

                    <CardContent style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
                        {/* Filter */}
                        <Box sx={{ display: 'flex', gap: 1 }}>
                            <Filter style={{ marginTop: 10, width: 16, height: 16, color: 'var(--muted-foreground)' }} />
                            <Select
                                value={statusFilter}
                                onValueChange={handleFilterChange}
                            >
                                <SelectTrigger style={{ width: 200 }}>
                                    <SelectValue placeholder="Filter by status" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        All Status
                                    </SelectItem>
                                    <SelectItem value="pending">
                                        Pending
                                    </SelectItem>
                                    <SelectItem value="approved">
                                        Approved
                                    </SelectItem>
                                    <SelectItem value="paid">Paid</SelectItem>
                                    <SelectItem value="rejected">
                                        Rejected
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </Box>

                        {/* Table */}
                        <Box sx={{ borderRadius: 2, border: 1, borderColor: 'divider' }}>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Request #</TableHead>
                                        <TableHead>Vendor</TableHead>
                                        <TableHead>Amount</TableHead>
                                        <TableHead>Provider</TableHead>
                                        <TableHead>Payment Method</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Date</TableHead>
                                        <TableHead style={{ textAlign: 'right' }}>
                                            Actions
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {data.payouts.data.length === 0 ? (
                                        <TableRow>
                                            <TableCell
                                                colSpan={8}
                                                style={{ paddingTop: 32, paddingBottom: 32, textAlign: 'center' }}
                                            >
                                                <Typography sx={{ color: 'text.secondary' }}>
                                                    No payout requests found
                                                </Typography>
                                            </TableCell>
                                        </TableRow>
                                    ) : (
                                        data.payouts.data.map((payout) => (
                                            <TableRow key={payout.id}>
                                                <TableCell style={{ fontFamily: 'monospace', fontSize: '0.875rem' }}>
                                                    {payout.request_number}
                                                </TableCell>
                                                <TableCell>
                                                    <Box>
                                                        <Typography sx={{ fontWeight: 500 }}>
                                                            {payout.user_name}
                                                        </Typography>
                                                        <Typography sx={{ fontSize: '0.75rem', color: 'text.secondary' }}>
                                                            {payout.user_email}
                                                        </Typography>
                                                    </Box>
                                                </TableCell>
                                                <TableCell style={{ fontWeight: 700 }}>
                                                    {payout.currency}{' '}
                                                    {parseFloat(
                                                        payout.amount,
                                                    ).toFixed(2)}
                                                </TableCell>
                                                <TableCell>
                                                    {
                                                        payout.mobile_money_provider
                                                    }
                                                </TableCell>
                                                <TableCell>
                                                    {payout.vendor_payout_details?.length > 0 ? (
                                                        <Box>
                                                            {payout.vendor_payout_details
                                                                .filter((d) => d.is_default)
                                                                .map((d) => (
                                                                    <Box key={d.id} sx={{ display: 'flex', alignItems: 'center', gap: 0.5 }}>
                                                                        <Typography sx={{ fontSize: '0.8125rem', fontWeight: 500 }}>
                                                                            {d.bank_name}
                                                                        </Typography>
                                                                        <Typography sx={{ fontSize: '0.75rem', color: 'text.secondary' }}>
                                                                            {d.account_number}
                                                                        </Typography>
                                                                    </Box>
                                                                ))}
                                                        </Box>
                                                    ) : (
                                                        <Typography sx={{ fontSize: '0.75rem', color: 'text.disabled' }}>
                                                            Not set
                                                        </Typography>
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    {getStatusBadge(
                                                        payout.status,
                                                    )}
                                                </TableCell>
                                                <TableCell style={{ fontSize: '0.75rem', color: 'gray' }}>
                                                    {new Date(
                                                        payout.created_at,
                                                    ).toLocaleDateString()}
                                                </TableCell>
                                                <TableCell style={{ textAlign: 'right' }}>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => {
                                                            setSelectedPayout(
                                                                payout,
                                                            );
                                                            if (
                                                                payout.status ===
                                                                'pending'
                                                            ) {
                                                                setShowApproveDialog(
                                                                    true,
                                                                );
                                                            } else if (
                                                                payout.status ===
                                                                'approved'
                                                            ) {
                                                                setShowMarkPaidDialog(
                                                                    true,
                                                                );
                                                            }
                                                        }}
                                                    >
                                                        <ChevronRight style={{ width: 16, height: 16 }} />
                                                    </Button>
                                                </TableCell>
                                            </TableRow>
                                        ))
                                    )}
                                </TableBody>
                            </Table>
                        </Box>
                    </CardContent>
                </Card>
            </Box>

            {/* Approve Dialog */}
            <Dialog
                open={showApproveDialog}
                onOpenChange={setShowApproveDialog}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Approve Payout Request</DialogTitle>
                        <DialogDescription>
                            {selectedPayout && (
                                <Box sx={{ mt: 2, display: 'flex', flexDirection: 'column', gap: 1 }}>
                                    <Typography>
                                        <strong>Vendor:</strong>{' '}
                                        {selectedPayout.user_name}
                                    </Typography>
                                    <Typography>
                                        <strong>Amount:</strong>{' '}
                                        {selectedPayout.currency}{' '}
                                        {parseFloat(
                                            selectedPayout.amount,
                                        ).toFixed(2)}
                                    </Typography>
                                    <Typography>
                                        <strong>Mobile Money:</strong>{' '}
                                        {selectedPayout.mobile_money_number} (
                                        {selectedPayout.mobile_money_provider})
                                    </Typography>
                                    {selectedPayout.vendor_payout_details && selectedPayout.vendor_payout_details.length > 0 && (
                                        <Box sx={{ mt: 2, p: 1.5, borderRadius: 1, bgcolor: 'action.hover' }}>
                                            <Typography sx={{ fontWeight: 600, fontSize: '0.8125rem', mb: 1 }}>
                                                Saved Payment Methods
                                            </Typography>
                                            {selectedPayout.vendor_payout_details.map((detail) => (
                                                <Box key={detail.id} sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', py: 0.5 }}>
                                                    <Box>
                                                        <Typography sx={{ fontSize: '0.8125rem' }}>
                                                            {detail.bank_name} — {detail.account_number}
                                                        </Typography>
                                                        <Typography sx={{ fontSize: '0.75rem', color: 'text.secondary' }}>
                                                            {detail.account_name} {detail.is_verified ? '(Verified)' : '(Unverified)'}
                                                        </Typography>
                                                    </Box>
                                                    {detail.is_default && (
                                                        <Chip label="Default" size="small" color="primary" variant="outlined" />
                                                    )}
                                                </Box>
                                            ))}
                                        </Box>
                                    )}
                                </Box>
                            )}
                        </DialogDescription>
                    </DialogHeader>

                    <Box sx={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
                        <Box>
                            <Box component="label" sx={{ display: 'block', mb: 1, fontSize: '0.875rem', fontWeight: 500 }}>
                                Admin Notes (Optional)
                            </Box>
                            <Input
                                placeholder="Add notes about this approval..."
                                value={approvalNotes}
                                onChange={(e) =>
                                    setApprovalNotes(e.target.value)
                                }
                            />
                        </Box>

                        <Box sx={{ display: 'flex', justifyContent: 'flex-end', gap: 1.5 }}>
                            <Button
                                variant="outline"
                                onClick={() => setShowApproveDialog(false)}
                                disabled={loading}
                            >
                                Cancel
                            </Button>
                            <Button
                                onClick={handleApprovePayout}
                                disabled={loading}
                            >
                                {loading ? 'Approving...' : 'Approve Payout'}
                            </Button>
                        </Box>
                    </Box>
                </DialogContent>
            </Dialog>

            {/* Mark Paid Dialog */}
            <Dialog
                open={showMarkPaidDialog}
                onOpenChange={setShowMarkPaidDialog}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Mark as Paid</DialogTitle>
                        <DialogDescription>
                            {selectedPayout && (
                                <Box sx={{ mt: 2, display: 'flex', flexDirection: 'column', gap: 1 }}>
                                    <Typography>
                                        <strong>Vendor:</strong>{' '}
                                        {selectedPayout.user_name}
                                    </Typography>
                                    <Typography>
                                        <strong>Amount:</strong>{' '}
                                        {selectedPayout.currency}{' '}
                                        {parseFloat(
                                            selectedPayout.amount,
                                        ).toFixed(2)}
                                    </Typography>
                                    <Typography>
                                        <strong>Mobile Money:</strong>{' '}
                                        {selectedPayout.mobile_money_number} (
                                        {selectedPayout.mobile_money_provider})
                                    </Typography>
                                    {selectedPayout.vendor_payout_details && selectedPayout.vendor_payout_details.length > 0 && (
                                        <Box sx={{ mt: 2, p: 1.5, borderRadius: 1, bgcolor: 'action.hover' }}>
                                            <Typography sx={{ fontWeight: 600, fontSize: '0.8125rem', mb: 1 }}>
                                                Saved Payment Methods
                                            </Typography>
                                            {selectedPayout.vendor_payout_details.map((detail) => (
                                                <Box key={detail.id} sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', py: 0.5 }}>
                                                    <Box>
                                                        <Typography sx={{ fontSize: '0.8125rem' }}>
                                                            {detail.bank_name} — {detail.account_number}
                                                        </Typography>
                                                        <Typography sx={{ fontSize: '0.75rem', color: 'text.secondary' }}>
                                                            {detail.account_name} {detail.is_verified ? '(Verified)' : '(Unverified)'}
                                                        </Typography>
                                                    </Box>
                                                    {detail.is_default && (
                                                        <Chip label="Default" size="small" color="primary" variant="outlined" />
                                                    )}
                                                </Box>
                                            ))}
                                        </Box>
                                    )}
                                </Box>
                            )}
                        </DialogDescription>
                    </DialogHeader>

                    <Box sx={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
                        <Box>
                            <Box component="label" sx={{ display: 'block', mb: 1, fontSize: '0.875rem', fontWeight: 500 }}>
                                Payment Reference *
                            </Box>
                            <Input
                                placeholder="e.g., MOMO-REF-XXXXXXXXXX"
                                value={paymentReference}
                                onChange={(e) =>
                                    setPaymentReference(e.target.value)
                                }
                            />
                            <Typography sx={{ mt: 0.5, fontSize: '0.75rem', color: 'text.secondary' }}>
                                Enter the mobile money transaction reference
                            </Typography>
                        </Box>

                        <Box sx={{ display: 'flex', justifyContent: 'flex-end', gap: 1.5 }}>
                            <Button
                                variant="outline"
                                onClick={() => setShowMarkPaidDialog(false)}
                                disabled={loading}
                            >
                                Cancel
                            </Button>
                            <Button
                                onClick={handleMarkAsPaid}
                                disabled={loading}
                            >
                                {loading ? 'Processing...' : 'Mark as Paid'}
                            </Button>
                        </Box>
                    </Box>
                </DialogContent>
            </Dialog>

            {/* Reject Dialog */}
            <Dialog open={showRejectDialog} onOpenChange={setShowRejectDialog}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Reject Payout Request</DialogTitle>
                        <DialogDescription>
                            This will return the money to vendor's available
                            balance.
                        </DialogDescription>
                    </DialogHeader>

                    <Box sx={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
                        <Box>
                            <Box component="label" sx={{ display: 'block', mb: 1, fontSize: '0.875rem', fontWeight: 500 }}>
                                Rejection Reason *
                            </Box>
                            <Input
                                placeholder="Why is this payout being rejected?"
                                value={rejectionReason}
                                onChange={(e) =>
                                    setRejectionReason(e.target.value)
                                }
                            />
                        </Box>

                        <Box sx={{ display: 'flex', justifyContent: 'flex-end', gap: 1.5 }}>
                            <Button
                                variant="outline"
                                onClick={() => setShowRejectDialog(false)}
                                disabled={loading}
                            >
                                Cancel
                            </Button>
                            <Button
                                variant="destructive"
                                onClick={handleRejectPayout}
                                disabled={loading}
                            >
                                {loading ? 'Rejecting...' : 'Reject Payout'}
                            </Button>
                        </Box>
                    </Box>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}

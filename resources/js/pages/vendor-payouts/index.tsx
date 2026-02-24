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
                <Badge className="bg-orange-100 text-orange-800 hover:bg-orange-100">
                    <AlertCircleIcon className="mr-1 h-3 w-3" />
                    Pending
                </Badge>
            );
        case 'approved':
            return (
                <Badge className="bg-blue-100 text-blue-800 hover:bg-blue-100">
                    <CheckCircle className="mr-1 h-3 w-3" />
                    Approved
                </Badge>
            );
        case 'paid':
            return (
                <Badge className="bg-green-100 text-green-800 hover:bg-green-100">
                    <CheckCircle className="mr-1 h-3 w-3" />
                    Paid
                </Badge>
            );
        case 'rejected':
            return (
                <Badge className="bg-red-100 text-red-800 hover:bg-red-100">
                    <XCircle className="mr-1 h-3 w-3" />
                    Rejected
                </Badge>
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

            <div className="space-y-6 p-6">
                {/* Statistics Cards */}
                <div className="grid gap-4 md:grid-cols-4">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Pending
                            </CardTitle>
                            <AlertCircleIcon className="h-4 w-4 text-orange-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {data.statistics.total_pending}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                GHS {data.statistics.pending_amount}
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Approved
                            </CardTitle>
                            <CheckCircle className="h-4 w-4 text-blue-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {data.statistics.total_approved}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Awaiting payment
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Paid
                            </CardTitle>
                            <CheckCircle className="h-4 w-4 text-green-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {data.statistics.total_paid}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Successfully paid
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Rejected
                            </CardTitle>
                            <XCircle className="h-4 w-4 text-red-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {data.statistics.total_rejected}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Requests rejected
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {/* Main Content */}
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div>
                                <CardTitle>Payout Requests</CardTitle>
                                <CardDescription>
                                    Manage vendor payout requests
                                </CardDescription>
                            </div>
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() =>
                                    router.get('/dashboard/vendor-payouts')
                                }
                            >
                                <RefreshCw className="mr-2 h-4 w-4" />
                                Refresh
                            </Button>
                        </div>
                    </CardHeader>

                    <CardContent className="space-y-4">
                        {/* Filter */}
                        <div className="flex gap-2">
                            <Filter className="mt-2.5 h-4 w-4 text-muted-foreground" />
                            <Select
                                value={statusFilter}
                                onValueChange={handleFilterChange}
                            >
                                <SelectTrigger className="w-[200px]">
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
                        </div>

                        {/* Table */}
                        <div className="rounded-lg border">
                            <Table>
                                <TableHeader className="bg-muted/50">
                                    <TableRow>
                                        <TableHead>Request #</TableHead>
                                        <TableHead>Vendor</TableHead>
                                        <TableHead>Amount</TableHead>
                                        <TableHead>Provider</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Date</TableHead>
                                        <TableHead className="text-right">
                                            Actions
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {data.payouts.data.length === 0 ? (
                                        <TableRow>
                                            <TableCell
                                                colSpan={7}
                                                className="py-8 text-center"
                                            >
                                                <p className="text-muted-foreground">
                                                    No payout requests found
                                                </p>
                                            </TableCell>
                                        </TableRow>
                                    ) : (
                                        data.payouts.data.map((payout) => (
                                            <TableRow key={payout.id}>
                                                <TableCell className="font-mono text-sm">
                                                    {payout.request_number}
                                                </TableCell>
                                                <TableCell>
                                                    <div>
                                                        <p className="font-medium">
                                                            {payout.user_name}
                                                        </p>
                                                        <p className="text-xs text-muted-foreground">
                                                            {payout.user_email}
                                                        </p>
                                                    </div>
                                                </TableCell>
                                                <TableCell className="font-bold">
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
                                                    {getStatusBadge(
                                                        payout.status,
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-xs text-muted-foreground">
                                                    {new Date(
                                                        payout.created_at,
                                                    ).toLocaleDateString()}
                                                </TableCell>
                                                <TableCell className="text-right">
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
                                                        <ChevronRight className="h-4 w-4" />
                                                    </Button>
                                                </TableCell>
                                            </TableRow>
                                        ))
                                    )}
                                </TableBody>
                            </Table>
                        </div>
                    </CardContent>
                </Card>
            </div>

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
                                <div className="mt-4 space-y-2">
                                    <p>
                                        <strong>Vendor:</strong>{' '}
                                        {selectedPayout.user_name}
                                    </p>
                                    <p>
                                        <strong>Amount:</strong>{' '}
                                        {selectedPayout.currency}{' '}
                                        {parseFloat(
                                            selectedPayout.amount,
                                        ).toFixed(2)}
                                    </p>
                                    <p>
                                        <strong>Mobile Money:</strong>{' '}
                                        {selectedPayout.mobile_money_number} (
                                        {selectedPayout.mobile_money_provider})
                                    </p>
                                </div>
                            )}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-4">
                        <div>
                            <label className="mb-2 block text-sm font-medium">
                                Admin Notes (Optional)
                            </label>
                            <Input
                                placeholder="Add notes about this approval..."
                                value={approvalNotes}
                                onChange={(e) =>
                                    setApprovalNotes(e.target.value)
                                }
                            />
                        </div>

                        <div className="flex justify-end gap-3">
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
                        </div>
                    </div>
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
                                <div className="mt-4 space-y-2">
                                    <p>
                                        <strong>Vendor:</strong>{' '}
                                        {selectedPayout.user_name}
                                    </p>
                                    <p>
                                        <strong>Amount:</strong>{' '}
                                        {selectedPayout.currency}{' '}
                                        {parseFloat(
                                            selectedPayout.amount,
                                        ).toFixed(2)}
                                    </p>
                                    <p>
                                        <strong>Mobile Money:</strong>{' '}
                                        {selectedPayout.mobile_money_number} (
                                        {selectedPayout.mobile_money_provider})
                                    </p>
                                </div>
                            )}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-4">
                        <div>
                            <label className="mb-2 block text-sm font-medium">
                                Payment Reference *
                            </label>
                            <Input
                                placeholder="e.g., MOMO-REF-XXXXXXXXXX"
                                value={paymentReference}
                                onChange={(e) =>
                                    setPaymentReference(e.target.value)
                                }
                            />
                            <p className="mt-1 text-xs text-muted-foreground">
                                Enter the mobile money transaction reference
                            </p>
                        </div>

                        <div className="flex justify-end gap-3">
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
                        </div>
                    </div>
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

                    <div className="space-y-4">
                        <div>
                            <label className="mb-2 block text-sm font-medium">
                                Rejection Reason *
                            </label>
                            <Input
                                placeholder="Why is this payout being rejected?"
                                value={rejectionReason}
                                onChange={(e) =>
                                    setRejectionReason(e.target.value)
                                }
                            />
                        </div>

                        <div className="flex justify-end gap-3">
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
                        </div>
                    </div>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}

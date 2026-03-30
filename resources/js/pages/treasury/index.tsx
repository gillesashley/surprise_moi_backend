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
import { Label } from '@/components/ui/label';
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
import { Head, Link, router } from '@inertiajs/react';
import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';
import { Building2, Landmark, RefreshCw, Send } from 'lucide-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Treasury', href: '/dashboard/treasury' },
];

interface TreasuryProps {
    tab: 'overview' | 'transactions' | 'settlements' | 'transfers';
    balance?: {
        success: boolean;
        data: Array<{ balance: number; currency: string }>;
    };
    totals?: { success: boolean; data: Record<string, number> };
    recentTransactions?: {
        success: boolean;
        data: Array<Record<string, unknown>>;
        meta: Record<string, unknown>;
    };
    transactions?: {
        success: boolean;
        data: Array<Record<string, unknown>>;
        meta: Record<string, unknown>;
    };
    settlements?: {
        success: boolean;
        data: Array<Record<string, unknown>>;
        meta: Record<string, unknown>;
    };
    transferHistory?: {
        data: Array<{
            id: number;
            amount: string;
            status: string;
            paystack_reference: string;
            paystack_transfer_code: string;
            created_at: string;
            completed_at: string | null;
            company_bank_account: {
                bank_name: string;
                account_number: string;
            } | null;
            initiated_by: { name: string } | null;
        }>;
        current_page: number;
        last_page: number;
        total: number;
    };
    bankAccount?: {
        id: number;
        account_name: string;
        account_number: string;
        bank_code: string;
        bank_name: string;
        paystack_recipient_code: string;
    } | null;
    banks?: {
        success: boolean;
        data: Array<{ name: string; code: string }>;
    };
    filters?: Record<string, string>;
    showBankForm?: boolean;
}

const tabs = [
    { key: 'overview', label: 'Overview', href: '/dashboard/treasury' },
    {
        key: 'transactions',
        label: 'Transactions',
        href: '/dashboard/treasury/transactions',
    },
    {
        key: 'settlements',
        label: 'Settlements',
        href: '/dashboard/treasury/settlements',
    },
    {
        key: 'transfers',
        label: 'Transfers',
        href: '/dashboard/treasury/transfers',
    },
];

function formatGhs(pesewas: number): string {
    return `GHS ${(pesewas / 100).toLocaleString('en-GH', { minimumFractionDigits: 2 })}`;
}

function formatDate(dateString: string): string {
    return new Date(dateString).toLocaleDateString('en-GH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function StatusBadge({ status }: { status: string }) {
    const variants: Record<
        string,
        'default' | 'secondary' | 'destructive' | 'outline'
    > = {
        success: 'default',
        processing: 'secondary',
        pending: 'outline',
        otp_required: 'outline',
        failed: 'destructive',
        reversed: 'destructive',
        abandoned: 'destructive',
    };

    return <Badge variant={variants[status] ?? 'outline'}>{status}</Badge>;
}

function getCsrfToken(): string {
    return (
        document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
            ?.content ?? ''
    );
}

// ============ OVERVIEW TAB ============
function OverviewTab({
    balance,
    totals,
    recentTransactions,
}: TreasuryProps) {
    const balanceAmount = balance?.data?.[0]?.balance ?? 0;

    const handleRefresh = () => {
        router.post(
            '/dashboard/treasury/refresh',
            {},
            {
                preserveScroll: true,
                onFinish: () => router.reload(),
            },
        );
    };

    return (
        <Box sx={{ display: 'flex', flexDirection: 'column', gap: 3 }}>
            <Card>
                <CardHeader>
                    <Box sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                        <Box>
                            <CardDescription>Paystack Balance</CardDescription>
                            <Typography variant="h4" sx={{ fontWeight: 700 }}>
                                {formatGhs(balanceAmount)}
                            </Typography>
                            <Typography sx={{ fontSize: '0.75rem', color: 'text.secondary', mt: 0.5 }}>
                                Available funds in your Paystack account ready for withdrawal
                            </Typography>
                        </Box>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={handleRefresh}
                        >
                            <RefreshCw style={{ width: 16, height: 16, marginRight: 8 }} /> Refresh
                        </Button>
                    </Box>
                </CardHeader>
            </Card>

            {totals?.success && (
                <Box sx={{ display: 'grid', gap: 2, gridTemplateColumns: { md: 'repeat(3, 1fr)' } }}>
                    <Card>
                        <CardHeader>
                            <CardDescription>Total Transactions</CardDescription>
                            <Typography variant="h5" sx={{ fontWeight: 600 }}>
                                {totals.data?.total_transactions?.toLocaleString() ?? '0'}
                            </Typography>
                            <Typography sx={{ fontSize: '0.75rem', color: 'text.secondary', mt: 0.5 }}>
                                All-time number of payments received
                            </Typography>
                        </CardHeader>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardDescription>Total Volume</CardDescription>
                            <Typography variant="h5" sx={{ fontWeight: 600 }}>
                                {formatGhs(totals.data?.total_volume ?? 0)}
                            </Typography>
                            <Typography sx={{ fontSize: '0.75rem', color: 'text.secondary', mt: 0.5 }}>
                                Total amount collected from all successful payments
                            </Typography>
                        </CardHeader>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardDescription>Pending</CardDescription>
                            <Typography variant="h5" sx={{ fontWeight: 600 }}>
                                {formatGhs(totals.data?.pending_amount ?? 0)}
                            </Typography>
                            <Typography sx={{ fontSize: '0.75rem', color: 'text.secondary', mt: 0.5 }}>
                                Payments initiated but not yet confirmed by Paystack
                            </Typography>
                        </CardHeader>
                    </Card>
                </Box>
            )}

            <Card>
                <CardHeader>
                    <CardTitle>Recent Transactions</CardTitle>
                    <CardDescription>
                        Latest 10 payments from onboarding fees, orders, and other sources
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    {recentTransactions?.data?.length ? (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Date</TableHead>
                                    <TableHead>Reference</TableHead>
                                    <TableHead>Amount</TableHead>
                                    <TableHead>Status</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {recentTransactions.data
                                    .slice(0, 10)
                                    .map((tx, i) => (
                                        <TableRow key={i}>
                                            <TableCell>
                                                {formatDate(tx.created_at as string)}
                                            </TableCell>
                                            <TableCell>
                                                <Typography sx={{ fontFamily: 'monospace', fontSize: '0.75rem' }}>
                                                    {tx.reference as string}
                                                </Typography>
                                            </TableCell>
                                            <TableCell>
                                                {formatGhs(tx.amount as number)}
                                            </TableCell>
                                            <TableCell>
                                                <StatusBadge status={tx.status as string} />
                                            </TableCell>
                                        </TableRow>
                                    ))}
                            </TableBody>
                        </Table>
                    ) : (
                        <Typography sx={{ py: 4, textAlign: 'center', color: 'text.secondary' }}>
                            No recent transactions.
                        </Typography>
                    )}
                </CardContent>
            </Card>
        </Box>
    );
}

// ============ TRANSACTIONS TAB ============
function TransactionsTab({ transactions, filters }: TreasuryProps) {
    const [from, setFrom] = useState(filters?.from ?? '');
    const [to, setTo] = useState(filters?.to ?? '');
    const [status, setStatus] = useState(filters?.status ?? '');

    const handleFilter = () => {
        router.get(
            '/dashboard/treasury/transactions',
            { from, to, status },
            { preserveScroll: true },
        );
    };

    return (
        <Box sx={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
            <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>
                All payments processed through Paystack, including onboarding fees, order payments, and subscriptions. Use filters to narrow by date or status.
            </Typography>
            <Card>
                <CardContent>
                    <Box sx={{ display: 'flex', flexWrap: 'wrap', alignItems: 'flex-end', gap: 2, pt: 1 }}>
                        <Box>
                            <Label>From</Label>
                            <Input
                                type="date"
                                value={from}
                                onChange={(e) => setFrom(e.target.value)}
                            />
                        </Box>
                        <Box>
                            <Label>To</Label>
                            <Input
                                type="date"
                                value={to}
                                onChange={(e) => setTo(e.target.value)}
                            />
                        </Box>
                        <Box>
                            <Label>Status</Label>
                            <Select
                                value={status}
                                onValueChange={setStatus}
                            >
                                <SelectTrigger style={{ width: 150 }}>
                                    <SelectValue placeholder="All" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All</SelectItem>
                                    <SelectItem value="success">Success</SelectItem>
                                    <SelectItem value="failed">Failed</SelectItem>
                                    <SelectItem value="abandoned">Abandoned</SelectItem>
                                </SelectContent>
                            </Select>
                        </Box>
                        <Button onClick={handleFilter}>Filter</Button>
                    </Box>
                </CardContent>
            </Card>

            <Card>
                <CardContent>
                    {transactions?.data?.length ? (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Date</TableHead>
                                    <TableHead>Reference</TableHead>
                                    <TableHead>Customer</TableHead>
                                    <TableHead>Amount</TableHead>
                                    <TableHead>Channel</TableHead>
                                    <TableHead>Status</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {transactions.data.map((tx, i) => (
                                    <TableRow key={i}>
                                        <TableCell>
                                            {formatDate(tx.created_at as string)}
                                        </TableCell>
                                        <TableCell>
                                            <Typography sx={{ fontFamily: 'monospace', fontSize: '0.75rem' }}>
                                                {tx.reference as string}
                                            </Typography>
                                        </TableCell>
                                        <TableCell>
                                            {(tx.customer as Record<string, string>)?.email ?? '-'}
                                        </TableCell>
                                        <TableCell>
                                            {formatGhs(tx.amount as number)}
                                        </TableCell>
                                        <TableCell>
                                            {(tx.channel as string) ?? '-'}
                                        </TableCell>
                                        <TableCell>
                                            <StatusBadge status={tx.status as string} />
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    ) : (
                        <Typography sx={{ py: 4, textAlign: 'center', color: 'text.secondary' }}>
                            No transactions found.
                        </Typography>
                    )}
                </CardContent>
            </Card>
        </Box>
    );
}

// ============ SETTLEMENTS TAB ============
function SettlementsTab({ settlements, filters }: TreasuryProps) {
    const [from, setFrom] = useState(filters?.from ?? '');
    const [to, setTo] = useState(filters?.to ?? '');

    const handleFilter = () => {
        router.get(
            '/dashboard/treasury/settlements',
            { from, to },
            { preserveScroll: true },
        );
    };

    return (
        <Box sx={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
            <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>
                Paystack automatically settles collected funds into your bank account on a schedule. Each row shows a batch of settled payments and the total amount deposited.
            </Typography>
            <Card>
                <CardContent>
                    <Box sx={{ display: 'flex', flexWrap: 'wrap', alignItems: 'flex-end', gap: 2, pt: 1 }}>
                        <Box>
                            <Label>From</Label>
                            <Input
                                type="date"
                                value={from}
                                onChange={(e) => setFrom(e.target.value)}
                            />
                        </Box>
                        <Box>
                            <Label>To</Label>
                            <Input
                                type="date"
                                value={to}
                                onChange={(e) => setTo(e.target.value)}
                            />
                        </Box>
                        <Button onClick={handleFilter}>Filter</Button>
                    </Box>
                </CardContent>
            </Card>

            <Card>
                <CardContent>
                    {settlements?.data?.length ? (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Date</TableHead>
                                    <TableHead>Amount</TableHead>
                                    <TableHead>Status</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {settlements.data.map((s, i) => (
                                    <TableRow key={i}>
                                        <TableCell>
                                            {formatDate(
                                                ((s.settlement_date ?? s.createdAt) as string) ?? '',
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            {formatGhs((s.total_amount as number) ?? 0)}
                                        </TableCell>
                                        <TableCell>
                                            <StatusBadge status={(s.status as string) ?? 'unknown'} />
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    ) : (
                        <Typography sx={{ py: 4, textAlign: 'center', color: 'text.secondary' }}>
                            No settlements found.
                        </Typography>
                    )}
                </CardContent>
            </Card>
        </Box>
    );
}

// ============ TRANSFERS TAB ============
function TransfersTab({
    balance,
    bankAccount,
    transferHistory,
    banks,
}: TreasuryProps) {
    const [amount, setAmount] = useState('');
    const [transferring, setTransferring] = useState(false);
    const [showOtpModal, setShowOtpModal] = useState(false);
    const [transferCode, setTransferCode] = useState('');
    const [otp, setOtp] = useState('');
    const [error, setError] = useState('');
    const [showBankForm, setShowBankForm] = useState(false);
    const [bankFormData, setBankFormData] = useState({
        account_number: '',
        bank_code: '',
        bank_name: '',
    });
    const [verifiedName, setVerifiedName] = useState('');
    const [verifying, setVerifying] = useState(false);

    const balanceAmount = balance?.data?.[0]?.balance ?? 0;
    const balanceGhs = balanceAmount / 100;

    const handleUseFullBalance = () => setAmount(balanceGhs.toFixed(2));

    const handleInitiateTransfer = async () => {
        setTransferring(true);
        setError('');
        try {
            const response = await fetch('/dashboard/treasury/transfer', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
                body: JSON.stringify({ amount: parseFloat(amount) }),
            });
            const result = await response.json();
            if (result.success) {
                setTransferCode(result.transfer_code);
                setShowOtpModal(true);
            } else {
                setError(
                    result.message ||
                        result.errors?.amount?.[0] ||
                        'Transfer failed.',
                );
            }
        } catch {
            setError('Network error. Please try again.');
        } finally {
            setTransferring(false);
        }
    };

    const handleFinalizeTransfer = async () => {
        setTransferring(true);
        setError('');
        try {
            const response = await fetch(
                '/dashboard/treasury/transfer/finalize',
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': getCsrfToken(),
                    },
                    body: JSON.stringify({
                        transfer_code: transferCode,
                        otp,
                    }),
                },
            );
            const result = await response.json();
            if (result.success) {
                setShowOtpModal(false);
                setOtp('');
                setAmount('');
                router.reload();
            } else {
                setError(result.message || 'Failed to finalize transfer.');
            }
        } catch {
            setError('Network error. Please try again.');
        } finally {
            setTransferring(false);
        }
    };

    const handleResendOtp = async () => {
        try {
            await fetch('/dashboard/treasury/transfer/resend-otp', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
                body: JSON.stringify({ transfer_code: transferCode }),
            });
        } catch {
            /* silently handle */
        }
    };

    const handleVerifyAccount = async () => {
        setVerifying(true);
        setVerifiedName('');
        try {
            const response = await fetch(
                '/dashboard/treasury/bank-account/verify',
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': getCsrfToken(),
                    },
                    body: JSON.stringify({
                        account_number: bankFormData.account_number,
                        bank_code: bankFormData.bank_code,
                    }),
                },
            );
            const result = await response.json();
            if (result.success || result.data?.account_name) {
                setVerifiedName(
                    result.data?.account_name ?? result.account_name ?? '',
                );
            }
        } catch {
            /* handle */
        } finally {
            setVerifying(false);
        }
    };

    const handleSaveBankAccount = () => {
        router.post('/dashboard/treasury/bank-account', {
            account_number: bankFormData.account_number,
            bank_code: bankFormData.bank_code,
            bank_name: bankFormData.bank_name,
            account_name: verifiedName,
        });
    };

    return (
        <Box sx={{ display: 'flex', flexDirection: 'column', gap: 3 }}>
            <Card>
                <CardHeader>
                    <Box sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                        <Box>
                            <Box sx={{ display: 'flex', alignItems: 'center', gap: 1, mb: 0.5 }}>
                                <Building2 style={{ width: 20, height: 20 }} />
                                <CardTitle>Company Bank Account</CardTitle>
                            </Box>
                            {bankAccount ? (
                                <CardDescription>
                                    {bankAccount.account_name} -{' '}
                                    {bankAccount.bank_name} (
                                    {bankAccount.account_number})
                                </CardDescription>
                            ) : (
                                <CardDescription>
                                    No bank account configured.
                                </CardDescription>
                            )}
                            <Typography sx={{ fontSize: '0.75rem', color: 'text.secondary', mt: 0.5 }}>
                                The bank account where funds will be sent when you initiate a transfer
                            </Typography>
                        </Box>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => setShowBankForm(true)}
                        >
                            {bankAccount ? 'Change Account' : 'Set Up Account'}
                        </Button>
                    </Box>
                </CardHeader>
            </Card>

            <Card>
                <CardHeader>
                    <Box sx={{ display: 'flex', alignItems: 'center', gap: 1, mb: 0.5 }}>
                        <Send style={{ width: 20, height: 20 }} />
                        <CardTitle>Transfer Funds</CardTitle>
                    </Box>
                    <CardDescription>
                        Current balance: {formatGhs(balanceAmount)}
                    </CardDescription>
                    <Typography sx={{ fontSize: '0.75rem', color: 'text.secondary' }}>
                        Move funds from your Paystack balance to the company bank account. An OTP will be sent to the Paystack account owner for confirmation.
                    </Typography>
                </CardHeader>
                <CardContent>
                    {!bankAccount ? (
                        <Typography sx={{ color: 'text.secondary' }}>
                            Set up a bank account first to make transfers.
                        </Typography>
                    ) : (
                        <Box sx={{ display: 'flex', alignItems: 'flex-end', gap: 2 }}>
                            <Box sx={{ flex: 1 }}>
                                <Label>Amount (GHS)</Label>
                                <Input
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    value={amount}
                                    onChange={(e) => setAmount(e.target.value)}
                                    placeholder="Enter amount"
                                />
                            </Box>
                            <Button
                                variant="outline"
                                onClick={handleUseFullBalance}
                            >
                                Use Full Balance
                            </Button>
                            <Button
                                onClick={handleInitiateTransfer}
                                disabled={transferring || !amount}
                            >
                                {transferring ? 'Processing...' : 'Transfer'}
                            </Button>
                        </Box>
                    )}
                    {error && (
                        <Typography sx={{ mt: 1, fontSize: '0.875rem', color: 'error.main' }}>
                            {error}
                        </Typography>
                    )}
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Transfer History</CardTitle>
                    <CardDescription>
                        Record of all fund transfers from Paystack to the company bank account
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    {transferHistory?.data?.length ? (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Date</TableHead>
                                    <TableHead>Amount</TableHead>
                                    <TableHead>Bank</TableHead>
                                    <TableHead>Reference</TableHead>
                                    <TableHead>Status</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {transferHistory.data.map((t) => (
                                    <TableRow key={t.id}>
                                        <TableCell>
                                            {formatDate(t.created_at)}
                                        </TableCell>
                                        <TableCell>
                                            GHS{' '}
                                            {parseFloat(t.amount).toLocaleString('en-GH', {
                                                minimumFractionDigits: 2,
                                            })}
                                        </TableCell>
                                        <TableCell>
                                            {t.company_bank_account?.bank_name ?? '-'}
                                        </TableCell>
                                        <TableCell>
                                            <Typography sx={{ fontFamily: 'monospace', fontSize: '0.75rem' }}>
                                                {t.paystack_reference}
                                            </Typography>
                                        </TableCell>
                                        <TableCell>
                                            <StatusBadge status={t.status} />
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    ) : (
                        <Typography sx={{ py: 4, textAlign: 'center', color: 'text.secondary' }}>
                            No transfers yet.
                        </Typography>
                    )}
                </CardContent>
            </Card>

            <Dialog open={showOtpModal} onOpenChange={setShowOtpModal}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Enter OTP</DialogTitle>
                        <DialogDescription>
                            An OTP has been sent to the Paystack account
                            owner. Enter it below to complete the transfer.
                        </DialogDescription>
                    </DialogHeader>
                    <Box sx={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
                        <Box>
                            <Label>OTP</Label>
                            <Input
                                value={otp}
                                onChange={(e) => setOtp(e.target.value)}
                                maxLength={6}
                                placeholder="Enter 6-digit OTP"
                            />
                        </Box>
                        {error && (
                            <Typography sx={{ fontSize: '0.875rem', color: 'error.main' }}>
                                {error}
                            </Typography>
                        )}
                        <Box sx={{ display: 'flex', justifyContent: 'space-between' }}>
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={handleResendOtp}
                            >
                                Resend OTP
                            </Button>
                            <Button
                                onClick={handleFinalizeTransfer}
                                disabled={transferring || otp.length !== 6}
                            >
                                {transferring ? 'Confirming...' : 'Confirm Transfer'}
                            </Button>
                        </Box>
                    </Box>
                </DialogContent>
            </Dialog>

            <Dialog open={showBankForm} onOpenChange={setShowBankForm}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Configure Bank Account</DialogTitle>
                        <DialogDescription>
                            Enter the company bank account details. The
                            account will be verified with Paystack.
                        </DialogDescription>
                    </DialogHeader>
                    <Box sx={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
                        <Box>
                            <Label>Account Number</Label>
                            <Input
                                value={bankFormData.account_number}
                                onChange={(e) =>
                                    setBankFormData((prev) => ({
                                        ...prev,
                                        account_number: e.target.value,
                                    }))
                                }
                                placeholder="Enter account number"
                            />
                        </Box>
                        <Box>
                            <Label>Bank</Label>
                            {banks?.data?.length ? (
                                <Select
                                    value={bankFormData.bank_code}
                                    onValueChange={(code) => {
                                        const selected = banks.data.find(
                                            (b) => b.code === code,
                                        );
                                        setBankFormData((prev) => ({
                                            ...prev,
                                            bank_code: code,
                                            bank_name: selected?.name ?? '',
                                        }));
                                    }}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select a bank" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {banks.data.map((bank) => (
                                            <SelectItem
                                                key={bank.code}
                                                value={bank.code}
                                            >
                                                {bank.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            ) : (
                                <Input
                                    value={bankFormData.bank_name}
                                    onChange={(e) =>
                                        setBankFormData((prev) => ({
                                            ...prev,
                                            bank_name: e.target.value,
                                        }))
                                    }
                                    placeholder="Bank name"
                                />
                            )}
                        </Box>
                        {!verifiedName && (
                            <Button
                                onClick={handleVerifyAccount}
                                disabled={
                                    verifying ||
                                    !bankFormData.account_number ||
                                    !bankFormData.bank_code
                                }
                            >
                                {verifying ? 'Verifying...' : 'Verify Account'}
                            </Button>
                        )}
                        {verifiedName && (
                            <Box sx={{
                                borderRadius: 1,
                                border: '1px solid',
                                borderColor: 'success.light',
                                bgcolor: 'success.50',
                                p: 1.5,
                            }}>
                                <Typography sx={{ fontSize: '0.875rem', color: 'success.dark' }}>
                                    Account Name: <strong>{verifiedName}</strong>
                                </Typography>
                                <Button
                                    style={{ marginTop: 8 }}
                                    onClick={handleSaveBankAccount}
                                >
                                    Confirm & Save
                                </Button>
                            </Box>
                        )}
                    </Box>
                </DialogContent>
            </Dialog>
        </Box>
    );
}

// ============ MAIN COMPONENT ============
export default function Treasury(props: TreasuryProps) {
    const { tab } = props;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Treasury" />

            <Box sx={{ display: 'flex', flexDirection: 'column', gap: 3, p: 3 }}>
                <Box sx={{ display: 'flex', alignItems: 'center', gap: 1.5 }}>
                    <Landmark style={{ width: 32, height: 32 }} />
                    <Typography variant="h4" sx={{ fontWeight: 700 }}>
                        Treasury
                    </Typography>
                </Box>

                <Box sx={{ display: 'flex', alignItems: 'center', gap: 3, borderBottom: 1, borderColor: 'divider' }}>
                    {tabs.map((t) => (
                        <Link
                            key={t.key}
                            href={t.href}
                            preserveState
                            style={{
                                display: 'inline-block',
                                padding: '12px 4px',
                                marginBottom: -1,
                                fontSize: '0.875rem',
                                fontWeight: 500,
                                whiteSpace: 'nowrap',
                                textDecoration: 'none',
                                borderBottom: '2px solid',
                                borderColor: tab === t.key ? '#7c3aed' : 'transparent',
                                color: tab === t.key ? '#7c3aed' : '#6b7280',
                                transition: 'color 0.15s, border-color 0.15s',
                            }}
                        >
                            {t.label}
                        </Link>
                    ))}
                </Box>

                {tab === 'overview' && <OverviewTab {...props} />}
                {tab === 'transactions' && <TransactionsTab {...props} />}
                {tab === 'settlements' && <SettlementsTab {...props} />}
                {tab === 'transfers' && <TransfersTab {...props} />}
            </Box>
        </AppLayout>
    );
}

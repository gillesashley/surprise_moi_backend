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
        <div className="space-y-6">
            <Card>
                <CardHeader className="flex flex-row items-center justify-between">
                    <div>
                        <CardDescription>Paystack Balance</CardDescription>
                        <CardTitle className="text-3xl">
                            {formatGhs(balanceAmount)}
                        </CardTitle>
                    </div>
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={handleRefresh}
                    >
                        <RefreshCw className="mr-2 h-4 w-4" /> Refresh
                    </Button>
                </CardHeader>
            </Card>

            {totals?.success && (
                <div className="grid gap-4 md:grid-cols-3">
                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription>
                                Total Transactions
                            </CardDescription>
                            <CardTitle className="text-2xl">
                                {totals.data?.total_transactions?.toLocaleString() ??
                                    '0'}
                            </CardTitle>
                        </CardHeader>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription>Total Volume</CardDescription>
                            <CardTitle className="text-2xl">
                                {formatGhs(
                                    totals.data?.total_volume ?? 0,
                                )}
                            </CardTitle>
                        </CardHeader>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription>Pending</CardDescription>
                            <CardTitle className="text-2xl">
                                {formatGhs(
                                    totals.data?.pending_amount ?? 0,
                                )}
                            </CardTitle>
                        </CardHeader>
                    </Card>
                </div>
            )}

            <Card>
                <CardHeader>
                    <CardTitle>Recent Transactions</CardTitle>
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
                                                {formatDate(
                                                    tx.created_at as string,
                                                )}
                                            </TableCell>
                                            <TableCell className="font-mono text-xs">
                                                {tx.reference as string}
                                            </TableCell>
                                            <TableCell>
                                                {formatGhs(
                                                    tx.amount as number,
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                <StatusBadge
                                                    status={
                                                        tx.status as string
                                                    }
                                                />
                                            </TableCell>
                                        </TableRow>
                                    ))}
                            </TableBody>
                        </Table>
                    ) : (
                        <p className="text-muted-foreground py-8 text-center">
                            No recent transactions.
                        </p>
                    )}
                </CardContent>
            </Card>
        </div>
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
        <div className="space-y-4">
            <Card>
                <CardContent className="pt-6">
                    <div className="flex flex-wrap items-end gap-4">
                        <div>
                            <Label>From</Label>
                            <Input
                                type="date"
                                value={from}
                                onChange={(e) => setFrom(e.target.value)}
                            />
                        </div>
                        <div>
                            <Label>To</Label>
                            <Input
                                type="date"
                                value={to}
                                onChange={(e) => setTo(e.target.value)}
                            />
                        </div>
                        <div>
                            <Label>Status</Label>
                            <Select
                                value={status}
                                onValueChange={setStatus}
                            >
                                <SelectTrigger className="w-[150px]">
                                    <SelectValue placeholder="All" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All</SelectItem>
                                    <SelectItem value="success">
                                        Success
                                    </SelectItem>
                                    <SelectItem value="failed">
                                        Failed
                                    </SelectItem>
                                    <SelectItem value="abandoned">
                                        Abandoned
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <Button onClick={handleFilter}>Filter</Button>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardContent className="pt-6">
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
                                            {formatDate(
                                                tx.created_at as string,
                                            )}
                                        </TableCell>
                                        <TableCell className="font-mono text-xs">
                                            {tx.reference as string}
                                        </TableCell>
                                        <TableCell>
                                            {(
                                                tx.customer as Record<
                                                    string,
                                                    string
                                                >
                                            )?.email ?? '-'}
                                        </TableCell>
                                        <TableCell>
                                            {formatGhs(
                                                tx.amount as number,
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            {(tx.channel as string) ??
                                                '-'}
                                        </TableCell>
                                        <TableCell>
                                            <StatusBadge
                                                status={
                                                    tx.status as string
                                                }
                                            />
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    ) : (
                        <p className="text-muted-foreground py-8 text-center">
                            No transactions found.
                        </p>
                    )}
                </CardContent>
            </Card>
        </div>
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
        <div className="space-y-4">
            <Card>
                <CardContent className="pt-6">
                    <div className="flex flex-wrap items-end gap-4">
                        <div>
                            <Label>From</Label>
                            <Input
                                type="date"
                                value={from}
                                onChange={(e) => setFrom(e.target.value)}
                            />
                        </div>
                        <div>
                            <Label>To</Label>
                            <Input
                                type="date"
                                value={to}
                                onChange={(e) => setTo(e.target.value)}
                            />
                        </div>
                        <Button onClick={handleFilter}>Filter</Button>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardContent className="pt-6">
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
                                                ((s.settled_date ??
                                                    s.created_at) as string) ??
                                                    '',
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            {formatGhs(
                                                (s.total_amount as number) ??
                                                    0,
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            <StatusBadge
                                                status={
                                                    (s.status as string) ??
                                                    'unknown'
                                                }
                                            />
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    ) : (
                        <p className="text-muted-foreground py-8 text-center">
                            No settlements found.
                        </p>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}

// ============ TRANSFERS TAB ============
function TransfersTab({
    balance,
    bankAccount,
    transferHistory,
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
        <div className="space-y-6">
            <Card>
                <CardHeader className="flex flex-row items-center justify-between">
                    <div>
                        <CardTitle className="flex items-center gap-2">
                            <Building2 className="h-5 w-5" /> Company Bank
                            Account
                        </CardTitle>
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
                    </div>
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => setShowBankForm(true)}
                    >
                        {bankAccount ? 'Change Account' : 'Set Up Account'}
                    </Button>
                </CardHeader>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <Send className="h-5 w-5" /> Transfer Funds
                    </CardTitle>
                    <CardDescription>
                        Current balance: {formatGhs(balanceAmount)}
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    {!bankAccount ? (
                        <p className="text-muted-foreground">
                            Set up a bank account first to make transfers.
                        </p>
                    ) : (
                        <div className="flex items-end gap-4">
                            <div className="flex-1">
                                <Label>Amount (GHS)</Label>
                                <Input
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    value={amount}
                                    onChange={(e) =>
                                        setAmount(e.target.value)
                                    }
                                    placeholder="Enter amount"
                                />
                            </div>
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
                        </div>
                    )}
                    {error && (
                        <p className="mt-2 text-sm text-red-600">{error}</p>
                    )}
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Transfer History</CardTitle>
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
                                            {parseFloat(
                                                t.amount,
                                            ).toLocaleString('en-GH', {
                                                minimumFractionDigits: 2,
                                            })}
                                        </TableCell>
                                        <TableCell>
                                            {t.company_bank_account
                                                ?.bank_name ?? '-'}
                                        </TableCell>
                                        <TableCell className="font-mono text-xs">
                                            {t.paystack_reference}
                                        </TableCell>
                                        <TableCell>
                                            <StatusBadge
                                                status={t.status}
                                            />
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    ) : (
                        <p className="text-muted-foreground py-8 text-center">
                            No transfers yet.
                        </p>
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
                    <div className="space-y-4">
                        <div>
                            <Label>OTP</Label>
                            <Input
                                value={otp}
                                onChange={(e) => setOtp(e.target.value)}
                                maxLength={6}
                                placeholder="Enter 6-digit OTP"
                            />
                        </div>
                        {error && (
                            <p className="text-sm text-red-600">{error}</p>
                        )}
                        <div className="flex justify-between">
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={handleResendOtp}
                            >
                                Resend OTP
                            </Button>
                            <Button
                                onClick={handleFinalizeTransfer}
                                disabled={
                                    transferring || otp.length !== 6
                                }
                            >
                                {transferring
                                    ? 'Confirming...'
                                    : 'Confirm Transfer'}
                            </Button>
                        </div>
                    </div>
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
                    <div className="space-y-4">
                        <div>
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
                        </div>
                        <div>
                            <Label>Bank Name</Label>
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
                        </div>
                        <div>
                            <Label>Bank Code</Label>
                            <Input
                                value={bankFormData.bank_code}
                                onChange={(e) =>
                                    setBankFormData((prev) => ({
                                        ...prev,
                                        bank_code: e.target.value,
                                    }))
                                }
                                placeholder="Bank code (e.g., 058)"
                            />
                        </div>
                        {!verifiedName && (
                            <Button
                                onClick={handleVerifyAccount}
                                disabled={
                                    verifying ||
                                    !bankFormData.account_number ||
                                    !bankFormData.bank_code
                                }
                            >
                                {verifying
                                    ? 'Verifying...'
                                    : 'Verify Account'}
                            </Button>
                        )}
                        {verifiedName && (
                            <div className="rounded border border-green-200 bg-green-50 p-3">
                                <p className="text-sm text-green-800">
                                    Account Name:{' '}
                                    <strong>{verifiedName}</strong>
                                </p>
                                <Button
                                    className="mt-2"
                                    onClick={handleSaveBankAccount}
                                >
                                    Confirm & Save
                                </Button>
                            </div>
                        )}
                    </div>
                </DialogContent>
            </Dialog>
        </div>
    );
}

// ============ MAIN COMPONENT ============
export default function Treasury(props: TreasuryProps) {
    const { tab } = props;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Treasury" />

            <div className="flex flex-col gap-6 p-6">
                <div className="flex items-center gap-3">
                    <Landmark className="h-8 w-8" />
                    <h1 className="text-3xl font-bold">Treasury</h1>
                </div>

                <div className="flex items-center gap-6 border-b">
                    {tabs.map((t) => (
                        <Link
                            key={t.key}
                            href={t.href}
                            preserveState
                            className={`-mb-px inline-block border-b-2 px-1 py-3 text-sm font-medium whitespace-nowrap transition-colors ${
                                tab === t.key
                                    ? 'border-primary text-primary'
                                    : 'border-transparent text-muted-foreground hover:border-muted-foreground/30 hover:text-foreground'
                            }`}
                        >
                            {t.label}
                        </Link>
                    ))}
                </div>

                {tab === 'overview' && <OverviewTab {...props} />}
                {tab === 'transactions' && (
                    <TransactionsTab {...props} />
                )}
                {tab === 'settlements' && (
                    <SettlementsTab {...props} />
                )}
                {tab === 'transfers' && <TransfersTab {...props} />}
            </div>
        </AppLayout>
    );
}

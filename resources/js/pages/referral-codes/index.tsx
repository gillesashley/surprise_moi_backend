import { useState } from 'react';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import Box from '@mui/material/Box';
import Chip from '@mui/material/Chip';
import Typography from '@mui/material/Typography';
import { Copy, Eye, Pencil, Plus, Power, PowerOff, Trash2, Users } from 'lucide-react';

interface ReferralCode {
    id: number;
    code: string;
    influencer: {
        id: number;
        name: string;
        email: string;
    };
    is_active: boolean;
    usage_count: number;
    max_usages: number | null;
    registration_bonus: number;
    expires_at: string | null;
}

interface PaginatedCodes {
    data: ReferralCode[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

interface Props {
    codes: PaginatedCodes;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Referral Codes',
        href: '/dashboard/referral-codes',
    },
];

const BULK_CATEGORIES: Array<{ label: string; value: string }> = [
    { label: 'Customer', value: 'customer' },
    { label: 'Vendor', value: 'vendor' },
    { label: 'Influencer', value: 'influencer' },
    { label: 'Field Agent', value: 'field_agent' },
    { label: 'Marketer', value: 'marketer' },
];

interface PreviewData {
    total: number;
    with_code: number;
    without_code: number;
    prefix: string;
}

function BulkGenerateModal({
    open,
    onClose,
}: {
    open: boolean;
    onClose: () => void;
}) {
    const [role, setRole] = useState('');
    const [preview, setPreview] = useState<PreviewData | null>(null);
    const [loading, setLoading] = useState(false);
    const [generating, setGenerating] = useState(false);
    const [result, setResult] = useState<number | null>(null);

    const getCsrfToken = (): string => {
        const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
        return match ? decodeURIComponent(match[1]) : document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '';
    };

    const fetchPreview = async (selectedRole: string) => {
        setLoading(true);
        setPreview(null);
        try {
            const res = await fetch('/dashboard/referral-codes/bulk-generate/preview', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': getCsrfToken(),
                },
                body: JSON.stringify({ role: selectedRole }),
            });
            const data = await res.json();
            setPreview(data);
        } catch {
            // silently fail
        } finally {
            setLoading(false);
        }
    };

    const handleRoleChange = (value: string) => {
        setRole(value);
        setResult(null);
        fetchPreview(value);
    };

    const handleGenerate = async () => {
        setGenerating(true);
        try {
            const res = await fetch('/dashboard/referral-codes/bulk-generate', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': getCsrfToken(),
                },
                body: JSON.stringify({ role }),
            });
            const data = await res.json();
            setResult(data.generated);
        } catch {
            // silently fail
        } finally {
            setGenerating(false);
        }
    };

    const handleClose = () => {
        if (result !== null) {
            router.reload();
        }
        setRole('');
        setPreview(null);
        setResult(null);
        onClose();
    };

    return (
        <Dialog open={open} onOpenChange={handleClose}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Bulk Generate Referral Codes</DialogTitle>
                    <DialogDescription>
                        Generate codes for all users in a category who don't have an active code.
                    </DialogDescription>
                </DialogHeader>

                {result !== null ? (
                    <Box sx={{ textAlign: 'center', py: 3 }}>
                        <Typography variant="h4" sx={{ fontWeight: 700, mb: 1 }}>
                            {result} Codes Generated
                        </Typography>
                        <Typography color="text.secondary" sx={{ mb: 3 }}>
                            All {BULK_CATEGORIES.find((c) => c.value === role)?.label} users without an active code now have a referral code.
                        </Typography>
                        <Button onClick={handleClose}>Done</Button>
                    </Box>
                ) : (
                    <Box sx={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
                        <Box sx={{ display: 'grid', gap: 1 }}>
                            <Typography variant="body2" sx={{ fontWeight: 500 }}>
                                User Category
                            </Typography>
                            <Select value={role} onValueChange={handleRoleChange}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Select a category..." />
                                </SelectTrigger>
                                <SelectContent>
                                    {BULK_CATEGORIES.map((cat) => (
                                        <SelectItem key={cat.value} value={cat.value}>
                                            {cat.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </Box>

                        {loading && (
                            <Typography variant="body2" color="text.secondary">
                                Loading preview...
                            </Typography>
                        )}

                        {preview && !loading && (
                            <Box
                                sx={{
                                    p: 2,
                                    borderRadius: 1,
                                    bgcolor: 'action.hover',
                                    display: 'flex',
                                    flexDirection: 'column',
                                    gap: 1,
                                }}
                            >
                                <Box sx={{ display: 'flex', justifyContent: 'space-between' }}>
                                    <Typography variant="body2" color="text.secondary">
                                        Total users
                                    </Typography>
                                    <Typography variant="body2" sx={{ fontWeight: 600 }}>
                                        {preview.total}
                                    </Typography>
                                </Box>
                                <Box sx={{ display: 'flex', justifyContent: 'space-between' }}>
                                    <Typography variant="body2" color="text.secondary">
                                        Already have active code
                                    </Typography>
                                    <Typography variant="body2" sx={{ fontWeight: 600, color: 'warning.main' }}>
                                        {preview.with_code}
                                    </Typography>
                                </Box>
                                <Box sx={{ borderTop: 1, borderColor: 'divider', pt: 1, display: 'flex', justifyContent: 'space-between' }}>
                                    <Typography variant="body2" sx={{ fontWeight: 600 }}>
                                        Codes to generate
                                    </Typography>
                                    <Typography variant="body2" sx={{ fontWeight: 700, color: 'success.main' }}>
                                        {preview.without_code}
                                    </Typography>
                                </Box>
                                <Typography variant="caption" color="text.secondary">
                                    Code format: {preview.prefix}XXXXXXXX
                                </Typography>
                            </Box>
                        )}

                        <Box sx={{ display: 'flex', justifyContent: 'flex-end', gap: 1 }}>
                            <Button variant="outline" onClick={handleClose}>
                                Cancel
                            </Button>
                            <Button
                                onClick={handleGenerate}
                                disabled={!preview || preview.without_code === 0 || generating}
                            >
                                {generating
                                    ? 'Generating...'
                                    : preview
                                      ? `Generate ${preview.without_code} Codes`
                                      : 'Generate'}
                            </Button>
                        </Box>
                    </Box>
                )}
            </DialogContent>
        </Dialog>
    );
}

export default function ReferralCodesIndex({ codes }: Props) {
    const [bulkModalOpen, setBulkModalOpen] = useState(false);

    const handleDelete = (codeId: number, code: string) => {
        if (
            confirm(
                `Are you sure you want to delete referral code "${code}"? This action cannot be undone.`,
            )
        ) {
            router.delete(`/dashboard/referral-codes/${codeId}`, {
                preserveScroll: true,
            });
        }
    };

    const handleToggle = (codeId: number) => {
        router.post(
            `/dashboard/referral-codes/${codeId}/toggle`,
            {},
            {
                preserveScroll: true,
            },
        );
    };

    const copyToClipboard = (code: string) => {
        navigator.clipboard.writeText(code);
        alert(`Code "${code}" copied to clipboard!`);
    };

    const handlePageChange = (page: number) => {
        router.get(
            '/dashboard/referral-codes',
            { page },
            {
                preserveState: true,
                preserveScroll: true,
            },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Referral Codes" />
            <Box sx={{ display: 'flex', height: '100%', flex: 1, flexDirection: 'column', gap: 2, p: 2 }}>
                <Box sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                    <Box>
                        <Typography variant="h5" sx={{ fontWeight: 700 }}>
                            Referral Codes Management
                        </Typography>
                        <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>
                            Manage referral codes for influencers
                        </Typography>
                    </Box>
                    <Box sx={{ display: 'flex', gap: 1 }}>
                        <Button variant="outline" onClick={() => setBulkModalOpen(true)}>
                            <Users style={{ marginRight: 8, width: 16, height: 16 }} />
                            Bulk Generate
                        </Button>
                        <Button asChild>
                            <Link href="/dashboard/referral-codes/create">
                                <Plus style={{ marginRight: 8, width: 16, height: 16 }} />
                                Create Code
                            </Link>
                        </Button>
                    </Box>
                </Box>

                <Card>
                    <CardHeader>
                        <CardTitle>All Referral Codes</CardTitle>
                        <CardDescription>
                            View and manage all referral codes
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Box sx={{ overflowX: 'auto' }}>
                            <Box component="table" sx={{ width: '100%' }}>
                                <thead>
                                    <Box component="tr" sx={{ borderBottom: 1, borderColor: 'divider' }}>
                                        <Box component="th" sx={{ p: 1, textAlign: 'left', fontSize: '0.875rem', fontWeight: 500 }}>
                                            Code
                                        </Box>
                                        <Box component="th" sx={{ p: 1, textAlign: 'left', fontSize: '0.875rem', fontWeight: 500 }}>
                                            Influencer
                                        </Box>
                                        <Box component="th" sx={{ p: 1, textAlign: 'left', fontSize: '0.875rem', fontWeight: 500 }}>
                                            Usage
                                        </Box>
                                        <Box component="th" sx={{ p: 1, textAlign: 'left', fontSize: '0.875rem', fontWeight: 500 }}>
                                            Bonus
                                        </Box>
                                        <Box component="th" sx={{ p: 1, textAlign: 'left', fontSize: '0.875rem', fontWeight: 500 }}>
                                            Status
                                        </Box>
                                        <Box component="th" sx={{ p: 1, textAlign: 'left', fontSize: '0.875rem', fontWeight: 500 }}>
                                            Actions
                                        </Box>
                                    </Box>
                                </thead>
                                <tbody>
                                    {codes.data.map((code) => (
                                        <Box
                                            component="tr"
                                            key={code.id}
                                            sx={{ borderBottom: 1, borderColor: 'divider', '&:hover': { bgcolor: 'action.hover' } }}
                                        >
                                            <Box component="td" sx={{ p: 1 }}>
                                                <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                                                    <Box
                                                        component="code"
                                                        sx={{ borderRadius: 1, bgcolor: 'action.hover', px: 1, py: 0.5, fontFamily: 'monospace', fontSize: '0.875rem', fontWeight: 600 }}
                                                    >
                                                        {code.code}
                                                    </Box>
                                                    <Box
                                                        component="button"
                                                        type="button"
                                                        onClick={() =>
                                                            copyToClipboard(
                                                                code.code,
                                                            )
                                                        }
                                                        sx={{ color: 'text.secondary', '&:hover': { color: 'text.primary' }, background: 'none', border: 'none', cursor: 'pointer', p: 0 }}
                                                    >
                                                        <Copy style={{ width: 16, height: 16 }} />
                                                    </Box>
                                                </Box>
                                            </Box>
                                            <Box component="td" sx={{ p: 1 }}>
                                                <Box>
                                                    <Typography sx={{ fontWeight: 500 }}>
                                                        {code.influencer.name}
                                                    </Typography>
                                                    <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>
                                                        {code.influencer.email}
                                                    </Typography>
                                                </Box>
                                            </Box>
                                            <Box component="td" sx={{ p: 1 }}>
                                                {code.usage_count} /{' '}
                                                {code.max_usages || '\u221E'}
                                            </Box>
                                            <Box component="td" sx={{ p: 1 }}>
                                                {Number(code.registration_bonus) > 0 ? (
                                                    <>GH₵{code.registration_bonus}</>
                                                ) : (
                                                    <Typography variant="body2" color="text.secondary" sx={{ fontSize: '0.8rem' }}>
                                                        Dynamic
                                                    </Typography>
                                                )}
                                            </Box>
                                            <Box component="td" sx={{ p: 1 }}>
                                                {code.is_active ? (
                                                    <Chip
                                                        icon={<Power style={{ width: 12, height: 12 }} />}
                                                        label="ACTIVE"
                                                        color="success"
                                                        size="small"
                                                        variant="outlined"
                                                    />
                                                ) : (
                                                    <Chip
                                                        icon={<PowerOff style={{ width: 12, height: 12 }} />}
                                                        label="INACTIVE"
                                                        color="default"
                                                        size="small"
                                                        variant="outlined"
                                                    />
                                                )}
                                            </Box>
                                            <Box component="td" sx={{ p: 1 }}>
                                                <Box sx={{ display: 'flex', gap: 1 }}>
                                                    <Link
                                                        href={`/dashboard/referral-codes/${code.id}`}
                                                    >
                                                        <Eye style={{ width: 16, height: 16, color: 'gray' }} />
                                                    </Link>
                                                    <Link
                                                        href={`/dashboard/referral-codes/${code.id}/edit`}
                                                    >
                                                        <Pencil style={{ width: 16, height: 16, color: 'gray' }} />
                                                    </Link>
                                                    <Box
                                                        component="button"
                                                        type="button"
                                                        onClick={() =>
                                                            handleToggle(
                                                                code.id,
                                                            )
                                                        }
                                                        title={
                                                            code.is_active
                                                                ? 'Deactivate'
                                                                : 'Activate'
                                                        }
                                                        sx={{ color: 'text.secondary', '&:hover': { color: 'text.primary' }, background: 'none', border: 'none', cursor: 'pointer', p: 0 }}
                                                    >
                                                        {code.is_active ? (
                                                            <PowerOff style={{ width: 16, height: 16 }} />
                                                        ) : (
                                                            <Power style={{ width: 16, height: 16 }} />
                                                        )}
                                                    </Box>
                                                    <Box
                                                        component="button"
                                                        type="button"
                                                        onClick={() =>
                                                            handleDelete(
                                                                code.id,
                                                                code.code,
                                                            )
                                                        }
                                                        sx={{ color: 'text.secondary', '&:hover': { color: 'error.main' }, background: 'none', border: 'none', cursor: 'pointer', p: 0 }}
                                                    >
                                                        <Trash2 style={{ width: 16, height: 16 }} />
                                                    </Box>
                                                </Box>
                                            </Box>
                                        </Box>
                                    ))}
                                </tbody>
                            </Box>
                        </Box>

                        {codes.data.length === 0 && (
                            <Box sx={{ py: 4, textAlign: 'center', color: 'text.secondary' }}>
                                No referral codes found. Create one to get
                                started.
                            </Box>
                        )}

                        {codes.last_page > 1 && (
                            <Box sx={{ mt: 2, display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                                <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>
                                    Showing {codes.data.length} of {codes.total}{' '}
                                    codes
                                </Typography>
                                <Box sx={{ display: 'flex', gap: 1 }}>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            handlePageChange(
                                                codes.current_page - 1,
                                            )
                                        }
                                        disabled={codes.current_page === 1}
                                    >
                                        Previous
                                    </Button>
                                    <Box sx={{ display: 'flex', alignItems: 'center', px: 1.5, fontSize: '0.875rem' }}>
                                        Page {codes.current_page} of{' '}
                                        {codes.last_page}
                                    </Box>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            handlePageChange(
                                                codes.current_page + 1,
                                            )
                                        }
                                        disabled={
                                            codes.current_page ===
                                            codes.last_page
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
            <BulkGenerateModal
                open={bulkModalOpen}
                onClose={() => setBulkModalOpen(false)}
            />
        </AppLayout>
    );
}

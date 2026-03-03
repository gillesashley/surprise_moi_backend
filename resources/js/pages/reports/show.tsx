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
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import Box from '@mui/material/Box';
import Chip from '@mui/material/Chip';
import Typography from '@mui/material/Typography';
import { ArrowLeft, CheckCircle, Clock, FileImage, Loader2, Package, User as UserIcon } from 'lucide-react';
import { useState } from 'react';

interface Attachment {
    id: number;
    file_name: string;
    file_size: number;
    mime_type: string;
    url: string;
}

interface Report {
    id: number;
    report_number: string;
    category: string;
    description: string;
    status: string;
    user: { id: number; name: string; email: string; phone: string | null };
    order: { id: number; order_number: string } | null;
    attachments: Attachment[];
    resolver: { id: number; name: string } | null;
    resolution_notes: string | null;
    cancellation_reason: string | null;
    resolved_at: string | null;
    created_at: string | null;
    updated_at: string | null;
    can_be_cancelled: boolean;
    is_pending: boolean;
    is_in_progress: boolean;
}

interface Props {
    report: Report;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Reports & Conflicts', href: '/dashboard/reports' },
    { title: 'Report Detail', href: '#' },
];

const statusChipColor = (status: string): 'warning' | 'info' | 'success' | 'default' => {
    const map: Record<string, 'warning' | 'info' | 'success' | 'default'> = {
        pending: 'warning',
        in_progress: 'info',
        resolved: 'success',
        cancelled: 'default',
    };
    return map[status] || 'warning';
};

const statusLabel: Record<string, string> = {
    pending: 'Pending',
    in_progress: 'In Progress',
    resolved: 'Resolved',
    cancelled: 'Cancelled',
};

function formatCategory(value: string): string {
    return value.split('_').map((w) => w.charAt(0).toUpperCase() + w.slice(1)).join(' ');
}

function formatFileSize(bytes: number): string {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1048576) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / 1048576).toFixed(1)} MB`;
}

export default function ReportShow({ report }: Props) {
    const [resolveDialogOpen, setResolveDialogOpen] = useState(false);

    const resolveForm = useForm({ resolution_notes: '' });

    const handleMarkInProgress = () => {
        router.post(`/dashboard/reports/${report.id}/status`, {}, {
            preserveScroll: true,
        });
    };

    const handleResolve = () => {
        resolveForm.post(`/dashboard/reports/${report.id}/resolve`, {
            onSuccess: () => {
                setResolveDialogOpen(false);
                resolveForm.reset();
            },
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Report ${report.report_number}`} />
            <Box sx={{ display: 'flex', flex: 1, flexDirection: 'column', gap: 2, p: 2, height: '100%' }}>
                <Box sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                    <Button asChild variant="ghost" size="sm">
                        <Link href="/dashboard/reports">
                            <ArrowLeft style={{ marginRight: 4, width: 16, height: 16 }} /> Back to Reports
                        </Link>
                    </Button>
                    <Box sx={{ display: 'flex', gap: 1 }}>
                        {report.is_pending && (
                            <Button variant="outline" onClick={handleMarkInProgress}>
                                <Clock style={{ marginRight: 4, width: 16, height: 16 }} /> Mark In Progress
                            </Button>
                        )}
                        {(report.is_pending || report.is_in_progress) && (
                            <Button onClick={() => setResolveDialogOpen(true)}>
                                <CheckCircle style={{ marginRight: 4, width: 16, height: 16 }} /> Resolve Report
                            </Button>
                        )}
                    </Box>
                </Box>

                <Box sx={{ display: 'grid', gap: 2, gridTemplateColumns: { xs: '1fr', lg: '2fr 1fr' } }}>
                    {/* Main info */}
                    <Box sx={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
                        <Card>
                            <CardHeader>
                                <Box sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                                    <Box>
                                        <CardTitle sx={{ fontFamily: 'monospace' }}>{report.report_number}</CardTitle>
                                        <CardDescription>{formatCategory(report.category)}</CardDescription>
                                    </Box>
                                    <Chip
                                        label={statusLabel[report.status] || 'Pending'}
                                        color={statusChipColor(report.status)}
                                        size="small"
                                        variant="outlined"
                                    />
                                </Box>
                            </CardHeader>
                            <CardContent sx={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
                                <Box>
                                    <Typography sx={{ mb: 0.5, fontSize: '0.875rem', fontWeight: 500, color: 'text.secondary' }}>Description</Typography>
                                    <Typography sx={{ fontSize: '0.875rem', lineHeight: 1.7 }}>{report.description}</Typography>
                                </Box>
                                {report.cancellation_reason && (
                                    <Box sx={{ borderRadius: 1, bgcolor: 'action.hover', p: 1.5 }}>
                                        <Typography sx={{ mb: 0.5, fontSize: '0.875rem', fontWeight: 500, color: 'text.secondary' }}>Cancellation Reason</Typography>
                                        <Typography sx={{ fontSize: '0.875rem' }}>{report.cancellation_reason}</Typography>
                                    </Box>
                                )}
                                {report.resolution_notes && (
                                    <Box sx={{ borderRadius: 1, bgcolor: 'success.lighter', p: 1.5 }}>
                                        <Typography sx={{ mb: 0.5, fontSize: '0.875rem', fontWeight: 500, color: 'success.main' }}>Resolution Notes</Typography>
                                        <Typography sx={{ fontSize: '0.875rem' }}>{report.resolution_notes}</Typography>
                                        {report.resolver && (
                                            <Typography sx={{ mt: 0.5, fontSize: '0.75rem', color: 'text.secondary' }}>
                                                Resolved by {report.resolver.name}
                                                {report.resolved_at && ` on ${new Date(report.resolved_at).toLocaleDateString()}`}
                                            </Typography>
                                        )}
                                    </Box>
                                )}
                            </CardContent>
                        </Card>

                        {/* Attachments */}
                        {report.attachments.length > 0 && (
                            <Card>
                                <CardHeader>
                                    <CardTitle sx={{ display: 'flex', alignItems: 'center', gap: 1, fontSize: '1rem' }}>
                                        <FileImage style={{ width: 16, height: 16 }} /> Attachments ({report.attachments.length})
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <Box sx={{ display: 'grid', gridTemplateColumns: { xs: 'repeat(2, 1fr)', sm: 'repeat(3, 1fr)' }, gap: 1.5 }}>
                                        {report.attachments.map((att) => (
                                            <Box
                                                component="a"
                                                key={att.id}
                                                href={att.url}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                sx={{
                                                    display: 'flex',
                                                    flexDirection: 'column',
                                                    alignItems: 'center',
                                                    gap: 0.5,
                                                    borderRadius: 2,
                                                    border: 1,
                                                    borderColor: 'divider',
                                                    p: 1.5,
                                                    textAlign: 'center',
                                                    textDecoration: 'none',
                                                    color: 'inherit',
                                                    '&:hover': { bgcolor: 'action.hover' },
                                                }}
                                            >
                                                <Box
                                                    component="img"
                                                    src={att.url}
                                                    alt={att.file_name}
                                                    sx={{ height: 80, width: '100%', borderRadius: 1, objectFit: 'cover' }}
                                                    onError={(e: React.SyntheticEvent<HTMLImageElement>) => { (e.target as HTMLImageElement).style.display = 'none'; }}
                                                />
                                                <Box component="span" sx={{ mt: 0.5, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap', fontSize: '0.75rem', fontWeight: 500, maxWidth: '100%' }}>{att.file_name}</Box>
                                                <Box component="span" sx={{ fontSize: '0.75rem', color: 'text.secondary' }}>{formatFileSize(att.file_size)}</Box>
                                            </Box>
                                        ))}
                                    </Box>
                                </CardContent>
                            </Card>
                        )}
                    </Box>

                    {/* Sidebar info */}
                    <Box sx={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
                        <Card>
                            <CardHeader>
                                <CardTitle sx={{ display: 'flex', alignItems: 'center', gap: 1, fontSize: '1rem' }}>
                                    <UserIcon style={{ width: 16, height: 16 }} /> Submitted By
                                </CardTitle>
                            </CardHeader>
                            <CardContent sx={{ display: 'flex', flexDirection: 'column', gap: 0.5, fontSize: '0.875rem' }}>
                                <Typography sx={{ fontWeight: 500 }}>{report.user.name}</Typography>
                                <Typography sx={{ color: 'text.secondary' }}>{report.user.email}</Typography>
                                {report.user.phone && <Typography sx={{ color: 'text.secondary' }}>{report.user.phone}</Typography>}
                            </CardContent>
                        </Card>

                        {report.order && (
                            <Card>
                                <CardHeader>
                                    <CardTitle sx={{ display: 'flex', alignItems: 'center', gap: 1, fontSize: '1rem' }}>
                                        <Package style={{ width: 16, height: 16 }} /> Related Order
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <Typography sx={{ fontFamily: 'monospace', fontSize: '0.875rem' }}>{report.order.order_number}</Typography>
                                </CardContent>
                            </Card>
                        )}

                        <Card>
                            <CardHeader>
                                <CardTitle sx={{ fontSize: '1rem' }}>Timeline</CardTitle>
                            </CardHeader>
                            <CardContent sx={{ display: 'flex', flexDirection: 'column', gap: 0.5, fontSize: '0.875rem', color: 'text.secondary' }}>
                                <Typography sx={{ color: 'text.secondary' }}>Created: {report.created_at ? new Date(report.created_at).toLocaleString() : '\u2014'}</Typography>
                                <Typography sx={{ color: 'text.secondary' }}>Updated: {report.updated_at ? new Date(report.updated_at).toLocaleString() : '\u2014'}</Typography>
                            </CardContent>
                        </Card>
                    </Box>
                </Box>

                {/* Resolve Dialog */}
                <Dialog open={resolveDialogOpen} onOpenChange={setResolveDialogOpen}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Resolve Report</DialogTitle>
                            <DialogDescription>
                                Provide resolution notes explaining how this report was handled.
                            </DialogDescription>
                        </DialogHeader>
                        <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
                            <Label htmlFor="resolution_notes">Resolution Notes</Label>
                            <Textarea
                                id="resolution_notes"
                                placeholder="Describe how the issue was resolved..."
                                rows={4}
                                value={resolveForm.data.resolution_notes}
                                onChange={(e) => resolveForm.setData('resolution_notes', e.target.value)}
                            />
                            {resolveForm.errors.resolution_notes && (
                                <Typography sx={{ fontSize: '0.875rem', color: 'error.main' }}>{resolveForm.errors.resolution_notes}</Typography>
                            )}
                        </Box>
                        <DialogFooter>
                            <Button variant="outline" onClick={() => setResolveDialogOpen(false)}>
                                Cancel
                            </Button>
                            <Button onClick={handleResolve} disabled={resolveForm.processing}>
                                {resolveForm.processing && <Loader2 style={{ marginRight: 4, width: 16, height: 16, animation: 'spin 1s linear infinite' }} />}
                                Resolve Report
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </Box>
        </AppLayout>
    );
}
